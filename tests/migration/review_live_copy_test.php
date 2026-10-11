<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Config\Config;
use App\Config\Env;
use App\Config\Transport;
use App\Domain\ReviewPart;
use App\Domain\ReviewRules;
use App\Domain\Role;
use App\Http\Deps;

require_once dirname(__DIR__) . '/support/live_copy.php';
require_once dirname(__DIR__) . '/support/review_fx.php';

/**
 * Internal reviews rehearsed on a migrated temp COPY of data/live-copy.db
 * (skipped when the download is absent). 0014 adds empty tables and the
 * round-limit setting and leaves every existing row alone; then a real
 * in-progress job with real people walks the whole review: hand-in, Request
 * review, the CD rejects the copy only, the Copywriter's round 2, the CD
 * approves everything, the ECD is told and approves (approved_internal),
 * ready for client, sent to client; a reassignment; a rejection after the
 * ECD approval clears it; the round limit warning at round 4; and the legacy
 * approve_client write still moves the job to approved_client.
 */
return [
    'live copy: 0014 is additive and the review flow works on real data' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db, $dir, $before] = $m;
        $after = lc_counts($db);
        foreach ($before as $table => $n) {
            t_eq($n, $after[$table] ?? -1, "rows in $table");
        }
        foreach (['asset_submissions', 'asset_reviews', 'asset_part_makers', 'job_reviews'] as $t) {
            t_eq(0, $after[$t] ?? -1, "$t: new and empty");
        }
        t_eq('3', (string) $db->scalar("SELECT value FROM app_settings WHERE key = 'review_round_limit'"), 'round limit seeded');
        t_eq('ok', (string) $db->scalar('PRAGMA integrity_check'));
        t_eq([], $db->query('PRAGMA foreign_key_check'));
        t_true(lc_migrator($db, $dir)->run()->ok, 'second run is a no-op');
        $db->close();

        app_base_path('/slash301pm');
        $config = new Config(Env::Local, dirname(__DIR__, 2), $dir, $dir . '/live.db', '/slash301pm', 'projects.slash301.com', '', false, Transport::Sse, Config::defaultNewUiRoles());
        $d = Deps::build($config, new FixedClock(new DateTimeImmutable('2026-10-09 09:00:00')));
        $job = (string) $d->db->scalar(
            "SELECT j.id FROM jobs j WHERE j.stage = 'in_progress'
               AND (SELECT COUNT(*) FROM assets a WHERE a.job_id = j.id AND a.status <> 'Cancelled') >= 2
               AND EXISTS (SELECT 1 FROM job_assignments x WHERE x.job_id = j.id AND x.role_on_job = 'CD')
               AND EXISTS (SELECT 1 FROM job_assignments x WHERE x.job_id = j.id AND x.role_on_job = 'Copywriter')
               AND EXISTS (SELECT 1 FROM job_assignments x WHERE x.job_id = j.id AND x.role_on_job = 'Designer')
             ORDER BY j.job_number LIMIT 1",
        );
        if ($job === '') {
            fwrite(STDERR, "note: no in-progress job with two assets and a creative team in live-copy.db; review flow skipped\n");
            return;
        }
        $team = $d->assignments->team($job);
        $cdId = (string) $team->userFor(Role::CD);
        $copyId = (string) $team->userFor(Role::Copywriter);
        $ecdIds = $d->reviews->ecdIds();
        t_true($ecdIds !== [], 'live has an active ECD');
        $amId = (string) $d->db->scalar("SELECT id FROM users WHERE role = 'AM' AND is_active = 1 ORDER BY id LIMIT 1");
        $otherCd = (string) $d->db->scalar("SELECT id FROM users WHERE role = 'CD' AND is_active = 1 AND id <> :c ORDER BY id LIMIT 1", ['c' => $cdId]);
        $cd = bh_session($d, $cdId);
        $copy = bh_session($d, $copyId);
        $ecd = bh_session($d, $ecdIds[0]);
        $am = bh_session($d, $amId);
        // No live job has an AM: the AM claims this one, so AM notifications have a recipient.
        sx_ok(bh_ds($d, $am, 'POST', '/jobs/' . $job . '/claim-am'));
        $posts = rx_posts($d, $job);
        $mediaMaker = static function (string $assetId) use ($d, $job, $team): string {
            return (string) ReviewRules::maker($d->reviews->asset($job, $assetId), ReviewPart::Media, $team);
        };

        // Hand-ins: copy by the Copywriter slot, media by the asset's Designer; Request review on the first post.
        foreach ($posts as $i => $aid) {
            sx_ok(rx_sub($d, $copy, $job, $aid, ['copy_text' => "Live copy $i\nSecond line", 'hashtags' => '#live', 'link_url' => 'https://example.com/live']));
            sx_ok(rx_sub($d, bh_session($d, $mediaMaker($aid)), $job, $aid, ['media_url' => 'https://cdn.example.com/live' . $i . '.jpg'], $i === 0));
            if ($i === 0) {
                t_eq([$cdId], rx_to($d, $job, 'review_requested'), 'Request review tells the CD slot holder');
                t_eq('in_review', rx_stage($d, $job));
            }
        }
        // The CD rejects the copy only of post 1; the Copywriter hands it in again in round 2.
        sx_ok(rx_rev($d, $cd, $job, $posts[0], 'rejected_with_feedback', 'copy', 'Tighter, please.'));
        t_eq([$copyId], rx_to($d, $job, 'asset_part_rejected'));
        t_eq('in_progress', rx_stage($d, $job));
        sx_ok(rx_sub($d, $copy, $job, $posts[0], ['copy_text' => 'Tighter copy.'], true));
        t_eq(2, $d->reviews->asset($job, $posts[0])->round);
        // The CD approves every post, then the job: the ECD is told and approves.
        foreach ($posts as $aid) {
            sx_ok(rx_rev($d, $cd, $job, $aid, 'approved'));
        }
        sx_ok(rx_job($d, $cd, $job, 'approve'));
        t_eq($ecdIds, rx_to($d, $job, 'job_cd_approved'));
        sx_ok(rx_job($d, $ecd, $job, 'ecd-approve'));
        t_eq('approved_internal', rx_stage($d, $job));
        t_eq('Approved (Internal)', sx_status($d, $job));
        t_true(in_array($amId, rx_to($d, $job, 'job_ecd_approved'), true), 'the AM is told of the ECD approval');
        // Ready for client and sent to client by the AM.
        sx_ok(rx_job($d, $am, $job, 'ready'));
        sx_ok(rx_job($d, $am, $job, 'send'));
        t_true($d->reviews->jobReview($job)->sentToClient());

        // A rejection after the ECD approval clears it and sends the job back to work.
        $last = $posts[count($posts) - 1];
        sx_ok(rx_rev($d, $cd, $job, $last, 'rejected', 'media'));
        t_eq([$mediaMaker($last)], rx_to($d, $job, 'asset_part_rejected'), 'media rejection goes to the media maker');
        $jr = $d->reviews->jobReview($job);
        t_true(!$jr->ecdApproved() && !$jr->cdApproved() && !$jr->sentToClient(), 'approvals cleared');
        t_eq('in_progress', rx_stage($d, $job));

        // Reassignment to another CD, who is then told about new hand-ins and reviews.
        if ($otherCd !== '') {
            sx_ok(rx_job($d, $cd, $job, 'reassign', $otherCd));
            t_eq([$otherCd], rx_to($d, $job, 'review_reassigned'));
            $cd = bh_session($d, $otherCd);
        }

        // Round limit (3): the warning comes when the media reaches round 4.
        $maker = bh_session($d, $mediaMaker($last));
        for ($n = 2; $n <= 4; $n++) {
            if ($n > 2) {
                sx_ok(rx_rev($d, $cd, $job, $last, 'rejected_with_feedback', 'media', 'Round ' . $n));
            }
            sx_ok(rx_sub($d, $maker, $job, $last, ['media_url' => 'https://cdn.example.com/live-r' . $n . '.jpg'], true));
            t_eq($n, $d->reviews->asset($job, $last)->round);
        }
        t_eq(1, count($d->activity->listByVerb($job, 'round_limit_warning')), 'one warning, at round 4');
        t_eq([$amId], rx_to($d, $job, 'round_limit_warning'));

        // Approve again (the other CD), the CD of the job approves the job, the ECD approves, then legacy approve_client.
        sx_ok(rx_rev($d, $cd, $job, $last, 'approved'));
        sx_ok(rx_job($d, bh_session($d, $cdId), $job, 'approve'));
        sx_ok(rx_job($d, $ecd, $job, 'ecd-approve'));
        t_eq('approved_internal', rx_stage($d, $job));
        sx_legacy_approve($d, $job);
        t_eq('approved_client', rx_stage($d, $job));
        t_eq('ok', (string) $d->db->scalar('PRAGMA integrity_check'));
    },
];

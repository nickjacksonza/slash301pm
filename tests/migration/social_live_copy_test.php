<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Config\Config;
use App\Config\Env;
use App\Config\Transport;
use App\Http\Deps;

require_once dirname(__DIR__) . '/support/live_copy.php';
require_once dirname(__DIR__) . '/support/social_fx.php';

/**
 * Social publishing rehearsed on a migrated temp COPY of data/live-copy.db
 * (skipped when the download is absent): a live job approved through legacy
 * SQL appears in the queue and walks to Live while legacy keeps seeing
 * 'Approved (External)'; 0012 left every existing table's rows alone.
 */
return [
    'live copy: 0012 is additive and the Social flow works on real data' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db, $dir, $before] = $m;
        $after = lc_counts($db);
        foreach ($before as $table => $n) {
            t_eq($n, $after[$table] ?? -1, "rows in $table");
        }
        t_eq(0, $after['asset_publications'] ?? -1, 'new table, empty');
        t_eq('ok', (string) $db->scalar('PRAGMA integrity_check'));
        $db->close();

        app_base_path('/slash301pm');
        $config = new Config(Env::Local, dirname(__DIR__, 2), $dir, $dir . '/live.db', '/slash301pm', 'projects.slash301.com', '', false, Transport::Sse, Config::defaultNewUiRoles());
        $d = Deps::build($config, new FixedClock(new DateTimeImmutable('2026-10-09 09:00:00')));
        $social = (string) $d->db->scalar("SELECT id FROM users WHERE role = 'Social' AND is_active = 1 ORDER BY id LIMIT 1");
        $am = (string) $d->db->scalar("SELECT id FROM users WHERE role = 'AM' AND is_active = 1 ORDER BY id LIMIT 1");
        t_true($social !== '' && $am !== '', 'live has a Social and an AM user');
        // A job at Approved (Internal) with a social asset; legacy approve_client.
        $jobId = (string) $d->db->scalar("SELECT j.id FROM jobs j JOIN assets a ON a.job_id = j.id WHERE j.status = 'Approved (Internal)' AND a.template_id LIKE 'social-%' LIMIT 1");
        if ($jobId === '') {
            fwrite(STDERR, "note: no Approved (Internal) job with a social asset in live-copy.db; flow part skipped\n");
            return;
        }
        $sol = bh_session($d, $social);
        t_eq(403, (ts_app($sol))(ts_request('GET', '/social/jobs/' . $jobId), $d)->status(), 'not approved yet');
        sx_legacy_approve($d, $jobId);
        t_eq('approved_client', sx_stage($d, $jobId));
        t_contains('/social/jobs/' . $jobId, ts_body(bh_ds($d, $sol, 'GET', '/social/list', ['sq' => ['tab' => 'check']])));
        // The AM takes the empty AM slot (no live job has one) and puts Social on the job.
        $marcus = bh_session($d, $am);
        sx_ok(bh_ds($d, $marcus, 'POST', '/jobs/' . $jobId . '/claim-am'));
        sx_ok(bh_ds($d, $marcus, 'POST', '/jobs/' . $jobId . '/assignments/social', ['team_social' => ['value' => $social]]));
        $assets = [];
        foreach ($d->publications->queue($jobId) as $a) {
            $assets[] = $a->assetId;
        }
        t_true($assets !== [], 'the job has social assets');
        $pids = [];
        foreach ($assets as $aid) {
            $pid = sx_add($d, $sol, $aid, 'instagram');
            sx_tick_all($d, $sol, $pid);
            $pids[] = $pid;
        }
        sx_ok(bh_ds($d, $sol, 'POST', '/social/jobs/' . $jobId . '/ready'));
        t_eq('ready_to_schedule', sx_stage($d, $jobId));
        $to = $d->activity->listByVerb($jobId, 'publication_ready')[0]->data['recipients'];
        t_true(in_array($am, $to, true), 'the AM is told (N34)');
        $pm = $d->db->scalar("SELECT user_id FROM job_assignments WHERE job_id = :j AND role_on_job = 'PM'", ['j' => $jobId]);
        t_eq($pm === null ? [$am] : [$am, (string) $pm], $to, 'and the PM slot holder');
        foreach ($pids as $pid) {
            sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/scheduled', sx_sig($d, $pid, ['scheduled_at' => '2026-10-12T09:30'])));
            sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => 'https://www.instagram.com/p/' . substr($pid, 0, 8) . '/'])));
        }
        sx_ok(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $pids[0] . '/promoted', sx_sig($d, $pids[0], ['promoted' => true])));
        t_eq('live', sx_stage($d, $jobId));
        t_eq('Approved (External)', sx_status($d, $jobId), 'legacy status mirror');
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pids[0] . '/reopen', sx_sig($d, $pids[0], ['reason' => 'Caption typo'])));
        t_eq('scheduled', sx_stage($d, $jobId));
        t_eq('Approved (External)', sx_status($d, $jobId));
        // Legacy writes still succeed on the migrated copy (the CHECK on status is untouched).
        $d->db->exec("UPDATE jobs SET status = 'Done' WHERE id = :j", ['j' => $jobId]);
        t_eq('done', sx_stage($d, $jobId), 'trigger keeps the stage in sync');
        t_eq(1, (int) $d->db->scalar("SELECT COUNT(*) FROM asset_publications WHERE job_id = :j AND promoted = 1", ['j' => $jobId]));
        // A legacy delete of an asset still works (ON DELETE CASCADE).
        $d->db->exec('DELETE FROM assets WHERE id = :a', ['a' => $assets[0]]);
        t_eq(0, (int) $d->db->scalar('SELECT COUNT(*) FROM asset_publications WHERE asset_id = :a', ['a' => $assets[0]]));
    },
];

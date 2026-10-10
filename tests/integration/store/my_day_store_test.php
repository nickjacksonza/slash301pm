<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Domain\Dates;
use App\Domain\MyDay;
use App\Domain\Role;
use App\Domain\Types\ActivityEntry;
use App\Http\Deps;

require_once dirname(__DIR__, 2) . '/support/app.php';

/** A migrated temp copy of data/live-copy.db (a fresh migrated database when the download is absent). */
function mds_deps(): Deps
{
    app_base_path('/slash301pm');
    $src = dirname(__DIR__, 3) . '/data/live-copy.db';
    $dir = ts_temp_dir();
    if (is_file($src)) {
        copy($src, $dir . '/test.db');
        if (is_file($src . '-wal') && filesize($src . '-wal') > 0) {
            copy($src . '-wal', $dir . '/test.db-wal');
        }
    } else {
        fwrite(STDERR, "note: data/live-copy.db not present, MyDayStore runs on an empty migrated database\n");
    }
    $d = Deps::build(ts_config($dir), new FixedClock(new DateTimeImmutable('2026-10-09 09:00:00 +02:00')), 'auto');
    $res = $d->migrator->run();
    t_true($res->ok, $res->error);
    return $d;
}

/** @return array{0:Deps,1:string,2:string,3:array<string,string>} deps, me, other, job ids by handle */
function mds_world(): array
{
    $d = mds_deps();
    $camp = $d->campaigns->listAll();
    if ($camp === []) {
        $b = bd_seed_campaign($d, 'MDAY');
        $camp = $d->campaigns->listAll();
    }
    $me = ts_user($d, 'mds_me', Role::AM);
    $other = ts_user($d, 'mds_other', Role::Traffic);
    $meUser = $d->users->findById($me);
    $now = new DateTimeImmutable('2026-10-09 07:00:00 UTC');
    $jobs = [];
    foreach (['overdue', 'soon', 'later', 'draft', 'waiting', 'unsent', 'done'] as $i => $h) {
        $jobs[$h] = $d->jobs->createDraft($camp[0], 'Job ' . $h, $meUser, $now);
    }
    $set = static function (string $h, string $due, string $stage, ?string $sentAt, int $unsent = 0, ?string $waitingOn = null) use ($d, $jobs): void {
        $d->db->exec("UPDATE jobs SET stage = :s, waiting_on = :w, waiting_reason = 'Need copy' WHERE id = :j", ['s' => $stage, 'w' => $waitingOn, 'j' => $jobs[$h]]);
        $d->db->exec('UPDATE briefs SET due_date = :d, sent_at = :at, has_unsent_changes = :u WHERE job_id = :j', ['d' => $due === '' ? null : $due, 'at' => $sentAt, 'u' => $unsent, 'j' => $jobs[$h]]);
    };
    $set('overdue', '2026-10-08', 'in_progress', '2026-10-01 08:00:00');
    $set('soon', '2026-10-13', 'in_progress', '2026-10-01 08:00:00');
    $set('later', '2026-11-30', 'in_progress', '2026-10-01 08:00:00');
    $set('draft', '', 'draft', null);
    $set('waiting', '', 'waiting', '2026-10-01 08:00:00', 0, 'am');
    $set('unsent', '', 'in_progress', '2026-10-01 08:00:00', 1);
    $set('done', '2026-09-01', 'done', '2026-09-01 08:00:00');
    return [$d, $me, $other, $jobs];
}

return [
    'MyDayStore.ownedOpen: only my open jobs, with due dates, AM flag and waiting_on' => function (): void {
        [$d, $me, , $jobs] = mds_world();
        $rows = $d->myDay->ownedOpen($me);
        $byId = [];
        foreach ($rows as $r) {
            $byId[$r->jobId] = $r;
        }
        t_eq(6, count($rows), 'done job and every legacy job are left out');
        t_true(!isset($byId[$jobs['done']]));
        t_eq('2026-10-08', $byId[$jobs['overdue']]->dueDate);
        t_eq(null, $byId[$jobs['draft']]->dueDate);
        t_true($byId[$jobs['overdue']]->iAmAm);
        t_eq('am', $byId[$jobs['waiting']]->waitingOn?->value);
        t_true($byId[$jobs['unsent']]->hasUnsentChanges);
        t_eq([], $d->myDay->ownedOpen('nobody-' . bin2hex(random_bytes(4))));
    },
    'MyDayStore sections end to end on a migrated live copy' => function (): void {
        [$d, $me, , $jobs] = mds_world();
        $u = $d->users->findById($me);
        $now = $d->clock->now();
        $res = MyDay::build($me, $u->role, $d->myDay->ownedOpen($me), [], [], '2026-10-01 00:00:00', $now);
        t_eq([$jobs['overdue']], array_map(static fn ($i) => $i->jobId, $res->overdue->items));
        t_eq([$jobs['soon']], array_map(static fn ($i) => $i->jobId, $res->dueSoon->items));
        $w = array_map(static fn ($i) => $i->jobId, $res->waiting->items);
        sort($w);
        $exp = [$jobs['waiting'], $jobs['unsent'], $jobs['draft']];
        sort($exp);
        t_eq($exp, $w);
        // the nav badge is the same set the page calls attention
        t_eq($res->attention, $d->myDay->attentionCount($me, Dates::today($now)));
        t_eq(4, $res->attention, 'overdue, waiting, unsent, draft');
    },
    'MyDayStore.attentionCount: uses SAST today, ignores other people and closed jobs' => function (): void {
        [$d, $me, $other] = mds_world();
        t_eq(4, $d->myDay->attentionCount($me, '2026-10-09'));
        t_eq(3, $d->myDay->attentionCount($me, '2026-10-08'), 'the 8th is not yet overdue');
        t_eq(0, $d->myDay->attentionCount($other, '2026-10-09'));
    },
    'MyDayStore.changesSince: other people only, my jobs or my recipient id, after the stamp' => function (): void {
        [$d, $me, $other, $jobs] = mds_world();
        $legacy = (string) $d->db->scalar("SELECT j.id FROM jobs j WHERE NOT EXISTS (SELECT 1 FROM job_assignments a WHERE a.job_id = j.id AND a.user_id = :u) AND j.id NOT IN (SELECT job_id FROM briefs WHERE created_by = :u) LIMIT 1", ['u' => $me]);
        if ($legacy === '') {
            $legacy = $d->jobs->createDraft($d->campaigns->listAll()[0], 'Not mine', $d->users->findById($other), new DateTimeImmutable('2026-10-09 06:00:00 UTC'));
        }
        $log = static function (string $job, string $actor, string $verb, string $at, array $rec) use ($d): void {
            $d->db->txImmediate(function ($tx) use ($d, $job, $actor, $verb, $at, $rec): void {
                $d->activity->append($tx, new ActivityEntry($job, $actor, $verb, 'job', $job, ['recipients' => $rec]), new DateTimeImmutable($at . ' UTC'));
            });
        };
        $log($jobs['overdue'], $other, 'brief_updated', '2026-10-09 06:00:00', []);          // my job, other actor: in
        $log($legacy, $other, 'brief_updated', '2026-10-09 06:10:00', [$me]);                // not my job, I am a recipient: in
        $log($legacy, $other, 'brief_updated', '2026-10-09 06:20:00', ['x' . $me]);          // someone else's id: out
        $log($jobs['soon'], $me, 'brief_updated', '2026-10-09 06:30:00', []);                // my own action: out
        $log($jobs['soon'], $other, 'brief_updated', '2026-10-08 06:30:00', []);             // too old: out
        $rows = $d->myDay->changesSince($me, '2026-10-09 00:00:00');
        $mine = [];
        foreach (MyDay::build($me, Role::AM, [], [], $rows, '2026-10-09 00:00:00', $d->clock->now())->changed as $c) {
            $mine[] = $c->activity->createdAt;
        }
        t_eq(['2026-10-09 06:10:00', '2026-10-09 06:00:00'], $mine);
        t_eq(true, count($rows) >= 2);
    },
    'MyDayStore seen stamp: none, set, replaced, and per user' => function (): void {
        [$d, $me, $other] = mds_world();
        t_eq(null, $d->myDay->seenAt($me));
        $d->myDay->markSeen($me, new DateTimeImmutable('2026-10-09 08:00:00 +02:00'));
        t_eq('2026-10-09 06:00:00', $d->myDay->seenAt($me), 'stored as UTC');
        $d->myDay->markSeen($me, new DateTimeImmutable('2026-10-09 10:00:00 +02:00'));
        t_eq('2026-10-09 08:00:00', $d->myDay->seenAt($me));
        t_eq(null, $d->myDay->seenAt($other));
        t_eq(1, (int) $d->db->scalar('SELECT COUNT(*) FROM user_seen WHERE user_id = :u', ['u' => $me]));
    },
    'MyDayStore binds hostile user ids (no SQL injection through the LIKE prefilter)' => function (): void {
        [$d] = mds_world();
        $evil = "x' OR '1'='1\" %_";
        t_eq([], $d->myDay->changesSince($evil, '2000-01-01 00:00:00'));
        t_eq([], $d->myDay->ownedOpen($evil));
        t_eq(0, $d->myDay->attentionCount($evil, '2026-10-09'));
        t_eq(null, $d->myDay->seenAt($evil));
    },
];

<?php
declare(strict_types=1);

use App\Domain\BriefVersion;
use App\Domain\MyDay;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Types\Activity;
use App\Domain\Types\MyDayChange;
use App\Domain\Types\MyDayJob;
use App\Domain\WaitingOn;

require_once dirname(__DIR__, 2) . '/support/app.php';

/** @param array<string,mixed> $o */
function md_job(string $id, array $o = []): MyDayJob
{
    $o += ['stage' => Stage::InProgress, 'due' => null, 'waitingOn' => null, 'reason' => '', 'sentAt' => '2026-10-01 08:00:00', 'unsent' => false, 'am' => true];
    return new MyDayJob($id, strtoupper($id), 'Title ' . $id, 'Camp', 'Brand', $o['stage'], $o['waitingOn'], $o['reason'], new BriefVersion(1, 0, 0), $o['sentAt'], $o['unsent'], $o['due'], $o['am']);
}

function md_change(string $id, string $actor, string $at, bool $mine, array $recipients, string $verb = 'brief_updated'): MyDayChange
{
    return new MyDayChange(new Activity($id, 'job', $actor, 'Actor ' . $actor, $verb, 'job', 'job', ['recipients' => $recipients], $at), 'J-1', 'T', $mine);
}

function md_now(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-10-09 09:00:00 +02:00');   // Friday
}

return [
    'sections: overdue, due soon and later by SAST date' => function (): void {
        $owned = [
            md_job('a', ['due' => '2026-10-08']), md_job('b', ['due' => '2026-10-09']), md_job('c', ['due' => '2026-10-14']),
            md_job('d', ['due' => '2026-10-15']), md_job('e'), md_job('f', ['due' => '2026-10-01']),
            md_job('done', ['stage' => Stage::Done, 'due' => '2026-09-01']),
        ];
        $r = MyDay::build('me', Role::AM, $owned, [], [], '2026-10-01 00:00:00', md_now());
        t_eq(['F', 'A'], array_map(static fn ($i) => strtoupper($i->jobId), $r->overdue->items));
        t_eq(['B', 'C'], array_map(static fn ($i) => strtoupper($i->jobId), $r->dueSoon->items));
        t_eq(2, $r->overdue->total);
        t_eq(-1, $r->overdue->items[1]->daysToDue);
        t_eq(2, $r->strip->overdue);
        t_eq(1, $r->strip->dueThisWeek, 'only b is due from today to Sunday');
    },
    'waiting on me: am waits, unsent changes, drafts; not client waits; dedupe by priority' => function (): void {
        $owned = [
            md_job('w1', ['stage' => Stage::Waiting, 'waitingOn' => WaitingOn::Am, 'reason' => 'Chase']),
            md_job('w2', ['stage' => Stage::Waiting, 'waitingOn' => WaitingOn::Client]),
            md_job('w3', ['stage' => Stage::Waiting, 'waitingOn' => WaitingOn::Am, 'am' => false]),   // I am PM, not AM
            md_job('u1', ['unsent' => true]),
            md_job('d1', ['stage' => Stage::Draft, 'sentAt' => null]),
            md_job('d2', ['stage' => Stage::Draft, 'sentAt' => null, 'due' => '2026-10-01']),
        ];
        $r = MyDay::build('me', Role::AM, $owned, [], [], '2026-10-01 00:00:00', md_now());
        t_eq(['W1', 'U1', 'D1', 'D2'], array_map(static fn ($i) => strtoupper($i->jobId), $r->waiting->items));
        t_eq('Waiting on you: Chase', $r->waiting->items[0]->reason);
        t_eq(4, $r->strip->waitingOnMe);
        t_eq(4, $r->attention, 'd2 is overdue and a draft but counts once');
    },
    'claimable only for AM, PM and Producer, and never jobs I own' => function (): void {
        $c = [md_job('x1'), md_job('x2'), md_job('mine')];
        $owned = [md_job('mine')];
        $am = MyDay::build('me', Role::AM, $owned, $c, [], '2026-10-01 00:00:00', md_now());
        t_eq(2, $am->claimable->total);
        t_eq(3, MyDay::build('me', Role::Producer, [], $c, [], '2026-10-01 00:00:00', md_now())->claimable->total);
        t_eq(0, MyDay::build('me', Role::Designer, [], $c, [], '2026-10-01 00:00:00', md_now())->claimable->total);
        t_eq(0, MyDay::build('me', Role::AM, $owned, $c, [], '2026-10-01 00:00:00', md_now())->waiting->total, 'claimable is not in waiting');
    },
    'changed: recipients or my job, not mine, newer than last seen, newest first' => function (): void {
        $changes = [
            md_change('c1', 'other', '2026-10-09 07:00:00', false, ['me']),
            md_change('c2', 'other', '2026-10-09 08:00:00', true, []),
            md_change('c3', 'me', '2026-10-09 08:30:00', true, ['me']),          // my own action
            md_change('c4', 'other', '2026-10-09 06:00:00', false, ['someone']),  // not for me, not my job
            md_change('c5', 'other', '2026-10-08 23:59:59', true, []),           // before last seen
            md_change('c6', 'other', '2026-10-09 00:00:00', true, []),           // equal to last seen: not newer
        ];
        $r = MyDay::build('me', Role::AM, [], [], $changes, '2026-10-09 00:00:00', md_now());
        t_eq(['c2', 'c1'], array_map(static fn (MyDayChange $c) => $c->activity->id, $r->changed));
        t_eq(2, $r->changedTotal);
    },
    'changed: hostile recipients JSON is ignored, not trusted' => function (): void {
        $c = new MyDayChange(new Activity('h', 'j', 'other', 'X', 'brief_updated', 'job', 'j', ['recipients' => 'me'], '2026-10-09 08:00:00'), '', '', false);
        t_eq([], $c->recipients());
        $c2 = new MyDayChange(new Activity('h', 'j', 'other', 'X', 'brief_updated', 'job', 'j', ['recipients' => [1, ['me'], 'me']], '2026-10-09 08:00:00'), '', '', false);
        t_eq(['me'], $c2->recipients());
    },
    'strip: due this week and sent this week use SAST dates' => function (): void {
        $owned = [
            md_job('a', ['due' => '2026-10-09']), md_job('b', ['due' => '2026-10-11']), md_job('c', ['due' => '2026-10-12']),
            md_job('s1', ['sentAt' => '2026-10-04 22:00:00']),   // 06:00 Mon 5 Oct SAST: this week
            md_job('s2', ['sentAt' => '2026-10-04 21:59:00']),   // Sunday 23:59 SAST: last week
        ];
        $r = MyDay::build('me', Role::AM, $owned, [], [], '2026-10-01 00:00:00', md_now());
        t_eq(2, $r->strip->dueThisWeek);
        t_eq(1, $r->strip->sentThisWeek, 's1 is Monday 06:00 SAST; s2 is Sunday 23:59 SAST (last week); the rest were sent on 1 Oct');
    },
    'display is capped but totals are full' => function (): void {
        $owned = [];
        for ($i = 0; $i < 12; $i++) {
            $owned[] = md_job('o' . $i, ['due' => '2026-10-01']);
        }
        $r = MyDay::build('me', Role::AM, $owned, [], [], '2026-10-01 00:00:00', md_now());
        t_eq(MyDay::SHOW, count($r->overdue->items));
        t_eq(12, $r->overdue->total);
    },
    'since: saved stamp wins, else a week back' => function (): void {
        t_eq('2026-10-05 10:00:00', MyDay::since('2026-10-05 10:00:00', md_now()));
        t_eq('2026-10-02 07:00:00', MyDay::since(null, md_now()));
    },
    'verbPhrase reads known and unknown verbs' => function (): void {
        t_eq('sent the brief', MyDay::verbPhrase('brief_sent'));
        t_eq('some new thing', MyDay::verbPhrase('some_new_thing'));
    },
];

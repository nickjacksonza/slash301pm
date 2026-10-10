<?php
declare(strict_types=1);

use App\Domain\BriefSend;
use App\Domain\BriefVersion;
use App\Domain\BumpLevel;
use App\Domain\Types\BriefSnapshot;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

function bst_now(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-10-09 10:00:00');
}

return [
    'send: first send plans 1.0.0, briefed, To Do and the assets' => function (): void {
        $team = bf_team(['Traffic' => 't1', 'Designer' => 'd1', 'AM' => 'am1']);
        $r = BriefSend::plan(bf_job('draft'), bf_brief(), [bf_line('l1', 3)], $team, [], null, 'Grand Opening London', 'The Meridian Collection', null, '', bst_now());
        t_true($r->errors->isEmpty(), $r->errors->first());
        $p = $r->plan;
        t_true($p->isFirst);
        t_eq('1.0.0', $p->version->format());
        t_eq('initial', $p->bumpLevel);
        t_eq('briefed', $p->toStage->value);
        t_eq('To Do', $p->legacyStatus);
        t_eq(3, count($p->assets->create));
        t_eq(null, $p->diff);
        t_eq(['t1', 'd1'], $p->recipients, 'Traffic plus pre-selected creatives, not the AM');
        t_eq(3, $p->jobRowVersion);
    },
    'send: refused with the readiness errors and outside draft' => function (): void {
        $r = BriefSend::plan(bf_job('draft'), bf_brief(), [bf_line('l1')], bf_team([]), [], null, 'C', 'B', null, '', bst_now());
        t_eq(null, $r->plan);
        t_eq(['traffic'], array_keys($r->errors->errors));
        $r2 = BriefSend::plan(bf_job('cancelled'), bf_brief(), [bf_line('l1')], bf_team(['Traffic' => 't1']), [], null, 'C', 'B', null, '', bst_now());
        t_eq(['stage'], array_keys($r2->errors->errors));
    },
    'send update: needs a bump and a note, bumps from the last sent version, keeps the stage' => function (): void {
        $sent = bf_brief(['version' => new BriefVersion(1, 0, 0), 'sentAt' => '2026-10-02 10:00:00', 'hasUnsentChanges' => true]);
        $team = bf_team(['Traffic' => 't1']);
        $last = BriefSnapshot::of($sent, [bf_line('l1', 3)], $team, 'C', 'B');
        $job = bf_job('in_progress', ['status' => 'Today']);
        $missing = BriefSend::plan($job, $sent, [bf_line('l1', 5)], $team, [], $last, 'C', 'B', null, '', bst_now());
        t_eq(['bump', 'note'], array_keys($missing->errors->errors));
        $nothing = BriefSend::plan($job, $sent, [bf_line('l1', 3)], $team, [], $last, 'C', 'B', BumpLevel::Patch, 'x', bst_now());
        t_eq(['changes'], array_keys($nothing->errors->errors));
        $ok = BriefSend::plan($job, $sent, [bf_line('l1', 5)], $team, [bf_asset('x1', 'l1'), bf_asset('x2', 'l1'), bf_asset('x3', 'l1', 'Inbox', 2, 'des1')], $last, 'C', 'B', BumpLevel::Minor, 'Two more posts', bst_now());
        t_true($ok->errors->isEmpty(), $ok->errors->first());
        t_eq('1.1.0', $ok->plan->version->format());
        t_eq('minor', $ok->plan->bumpLevel);
        t_eq('in_progress', $ok->plan->toStage->value);
        t_eq('Today', $ok->plan->legacyStatus, 'Today kept on a no-op stage write');
        t_eq(2, count($ok->plan->assets->create));
        t_eq('changed', $ok->plan->diff->lines[0]->kind);
        t_eq(['t1', 'des1'], $ok->plan->recipients, 'every agency assignee and asset assignee');
    },
    'send update: a recalled brief is re-sent as an update and goes back to briefed' => function (): void {
        $sent = bf_brief(['version' => new BriefVersion(1, 0, 0), 'sentAt' => '2026-10-02 10:00:00']);
        $team = bf_team(['Traffic' => 't1']);
        $last = BriefSnapshot::of($sent, [bf_line('l1', 3)], $team, 'C', 'B');
        $r = BriefSend::plan(bf_job('draft'), bf_brief(['version' => new BriefVersion(1, 0, 0), 'sentAt' => '2026-10-02 10:00:00', 'dueDate' => '2026-10-30']), [bf_line('l1', 3)], $team, [], $last, 'C', 'B', BumpLevel::Major, 'Re-brief', bst_now());
        t_true($r->errors->isEmpty(), $r->errors->first());
        t_eq('2.0.0', $r->plan->version->format());
        t_eq('briefed', $r->plan->toStage->value);
        $same = BriefSend::plan(bf_job('draft'), $sent, [bf_line('l1', 3)], $team, [], $last, 'C', 'B', BumpLevel::Patch, 'Re-sent as is', bst_now());
        t_true($same->errors->isEmpty(), 'a recalled brief can be re-sent unchanged: ' . $same->errors->first());
        t_eq('1.0.1', $same->plan->version->format());
    },
];

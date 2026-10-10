<?php
declare(strict_types=1);

use App\Domain\AssetPlan;
use App\Domain\AssetStatus;
use App\Domain\Types\AssetNaming;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

function apt_ctx(): AssetNaming
{
    return new AssetNaming('MERC-004', 'The Meridian Collection', 'Grand Opening London', '2026-10-20', new DateTimeImmutable('2026-10-09 10:00:00'));
}

return [
    'asset plan: first send expands each line into qty named assets' => function (): void {
        $r = AssetPlan::plan([bf_line('l1', 3), bf_line('l2', 1, ['templateId' => 'email-copy', 'label' => 'Email Copy', 'sizeFormat' => '', 'dueDate' => '2026-10-15'])], [], apt_ctx());
        t_eq([], $r->cancel);
        t_eq([], $r->warnings);
        $names = array_map(static fn ($p) => $p->name, $r->create);
        t_eq([
            'MERC-004-TheMeridia-GrandOpeningLon-SocialPostStatic1-v1-20261009_1080x1350',
            'MERC-004-TheMeridia-GrandOpeningLon-SocialPostStatic2-v1-20261009_1080x1350',
            'MERC-004-TheMeridia-GrandOpeningLon-SocialPostStatic3-v1-20261009_1080x1350',
            'MERC-004-TheMeridia-GrandOpeningLon-EmailCopy-v1-20261009',
        ], $names);
        t_eq(['image', 'image', 'image', 'copy'], array_map(static fn ($p) => $p->type, $r->create));
        t_eq(['2026-10-20', '2026-10-20', '2026-10-20', '2026-10-15'], array_map(static fn ($p) => $p->dueDate, $r->create), 'line due date, else the brief due date');
        t_eq([0, 1, 2, 3], array_map(static fn ($p) => $p->sortOrder, $r->create));
    },
    'asset plan: quantity up adds, down cancels newest unstarted, started are kept with a warning (table)' => function (): void {
        $cases = [
            'up 3->5 adds 2 numbered 4 and 5' => [5, ['Inbox', 'Inbox', 'Inbox'], 2, [], 0],
            'same qty does nothing' => [3, ['Inbox', 'In Progress', 'Done'], 0, [], 0],
            'down 3->1 cancels the two newest unstarted' => [1, ['Inbox', 'Inbox', 'Inbox'], 0, ['x3', 'x2'], 0],
            'down 3->1 with two started cancels only the unstarted one' => [1, ['In Progress', 'Inbox', 'Today'], 0, ['x2'], 1],
            'down 3->1 all started: nothing cancelled, warning' => [1, ['Done', 'In Review', 'Live'], 0, [], 2],
            'social statuses count as started' => [0 + 1, ['Ready to Schedule', 'Scheduled', 'Inbox'], 0, ['x3'], 1],
            'cancelled ones do not count and are not reused' => [3, ['Cancelled', 'Inbox', 'Inbox'], 1, [], 0],
        ];
        foreach ($cases as $name => [$qty, $statuses, $adds, $cancels, $kept]) {
            $assets = [];
            foreach ($statuses as $i => $st) {
                $assets[] = bf_asset('x' . ($i + 1), 'l1', $st, $i);
            }
            $r = AssetPlan::plan([bf_line('l1', $qty)], $assets, apt_ctx());
            t_eq($adds, count($r->create), $name . ': adds');
            t_eq($cancels, $r->cancel, $name . ': cancels');
            t_eq($kept, $r->warnings === [] ? 0 : $r->warnings[0]->startedKept, $name . ': kept');
        }
        $up = AssetPlan::plan([bf_line('l1', 5)], [bf_asset('x1', 'l1', 'Inbox', 0), bf_asset('x2', 'l1', 'Inbox', 1), bf_asset('x3', 'l1', 'Inbox', 7)], apt_ctx());
        t_eq(['SocialPostStatic4', 'SocialPostStatic5'], array_map(static fn ($p) => explode('-', $p->name)[4], $up->create));
        t_eq([8, 9], array_map(static fn ($p) => $p->sortOrder, $up->create), 'after the highest sort order');
    },
    'asset plan: removed line cancels unstarted, keeps started, ignores legacy assets without a line' => function (): void {
        $assets = [bf_asset('a', 'gone', 'Inbox'), bf_asset('b', 'gone', 'In Progress'), bf_asset('c', null, 'Inbox'), bf_asset('d', 'gone', 'Cancelled')];
        $r = AssetPlan::plan([], $assets, apt_ctx());
        t_eq([], $r->create);
        t_eq(['a'], $r->cancel);
        t_eq(1, count($r->warnings));
        t_eq('gone', $r->warnings[0]->lineId);
        t_eq(1, $r->warnings[0]->startedKept);
    },
    'asset status: started set' => function (): void {
        foreach (['Inbox' => false, 'To Do' => false, 'Not Started' => false, '' => false, 'Cancelled' => false, 'In Progress' => true, 'Today' => true,
            'This Week' => true, 'Done' => true, 'Ready to Schedule' => true, 'Scheduled' => true, 'Live' => true] as $s => $want) {
            t_eq($want, AssetStatus::isStarted((string) $s), (string) $s);
        }
    },
];

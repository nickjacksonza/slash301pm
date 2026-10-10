<?php
declare(strict_types=1);

use App\Domain\DueWindow;
use App\Domain\JobColumn;
use App\Domain\JobGroupBy;
use App\Domain\JobQuery;
use App\Domain\JobSortField;
use App\Domain\OwnerFilter;
use App\Domain\Role;
use App\Domain\Stage;

return [
    'job query: defaults round-trip to an empty URL query, per screen' => function (): void {
        foreach ([JobQuery::SCREEN_JOBS, JobQuery::SCREEN_BOARD] as $screen) {
            $d = JobQuery::defaults($screen);
            t_eq([], $d->toQuery(), $screen);
            t_eq($d->toState(), JobQuery::fromQuery([], $screen)->toState(), $screen . ' from empty query');
            t_true($d == JobQuery::fromState($d->toState(), $screen), $screen . ' state round trip');
        }
        $vals = static function (array $stages): array {
            $v = array_map(static fn (Stage $s): string => $s->value, $stages);
            sort($v);
            return $v;
        };
        t_eq($vals(Stage::open()), $vals(JobQuery::defaults('jobs')->stages), 'grid default is the open set');
        t_eq('board', JobQuery::defaults('board')->stageToken());
        t_true(!JobQuery::defaults('board')->hasStage(Stage::Live), 'social columns are opt-in on the board');
    },
    'job query: URL query and state round-trip (table)' => function (): void {
        $cases = [
            'everything' => ['stages' => 'draft,waiting', 'brand' => 'brand_merc', 'campaign' => 'camp_1', 'owner' => 'mine', 'assignee' => 'u_1',
                'role' => 'Traffic', 'due' => 'this_week', 'q' => 'launch 2', 'sort' => '-updated,title', 'group' => 'brand', 'cols' => 'job_number,title,due'],
            'named set' => ['stages' => 'closed'],
            'unowned + overdue' => ['owner' => 'unowned', 'due' => 'overdue'],
            'slot empty' => ['assignee' => 'none', 'role' => 'Traffic'],
            'me' => ['assignee' => 'me'],
            'group due' => ['group' => 'due_bucket', 'sort' => 'due'],
        ];
        foreach ($cases as $name => $query) {
            $q = JobQuery::fromQuery($query, 'jobs');
            $got = $q->toQuery();
            ksort($got);
            ksort($query);
            t_eq($query, $got, $name . ': toQuery');
            t_true($q == JobQuery::fromQuery($q->toQuery(), 'jobs'), $name . ': fromQuery(toQuery)');
            t_true($q == JobQuery::fromState($q->toState(), 'jobs'), $name . ': fromState(toState)');
            t_true($q == JobQuery::fromSavedState($q->toJson(), 'jobs'), $name . ': saved JSON');
        }
        $q = JobQuery::fromQuery(['stages' => 'draft,waiting', 'sort' => '-updated,title', 'cols' => 'due,title,job_number'], 'jobs');
        t_eq([Stage::Draft, Stage::Waiting], $q->stages);
        t_eq(JobSortField::Updated, $q->sorts[0]->field);
        t_true($q->sorts[0]->desc);
        t_eq([JobColumn::JobNumber, JobColumn::Title, JobColumn::Due], $q->columns, 'canonical column order');
    },
    'job query: whitelist drops unknown and hostile values (table)' => function (): void {
        $cases = [
            ['stages', 'live; DROP TABLE jobs', 'stages', JobQuery::defaults('jobs')->stages],
            ['stages', 'nope,draft,draft', 'stages', [Stage::Draft]],
            ['brand', "x' OR 1=1 --", 'brandId', ''],
            ['campaign', str_repeat('a', 65), 'campaignId', ''],
            ['owner', 'everyone', 'owner', OwnerFilter::All],
            ['assignee', '../../etc', 'assigneeId', ''],
            ['role', 'COO', 'role', null],
            ['role', 'Wizard', 'role', null],
            ['due', 'yesterday', 'due', null],
            ['group', 'password_hash', 'groupBy', JobGroupBy::None],
            ['q', "  a\x00b\tc  ", 'text', 'a b c'],
            ['q', str_repeat('é', 150), 'text', str_repeat('é', 100)],
        ];
        foreach ($cases as [$key, $value, $prop, $want]) {
            $q = JobQuery::fromQuery([$key => $value], 'jobs');
            t_eq($want, $q->{$prop}, "$key=" . substr((string) $value, 0, 30));
        }
        $q = JobQuery::fromQuery(['sort' => 'budget,password,-due,due,title,stage', 'cols' => 'secret,hours'], 'jobs');
        t_eq('-due,title', $q->sortToken(), 'unknown and duplicate sort keys dropped, at most two');
        t_eq('job_number,title,hours', $q->columnToken(), 'required columns kept, unknown dropped');
        t_eq(null, JobQuery::fromSavedState('[1,2]', 'jobs'));
        t_eq(null, JobQuery::fromSavedState('not json', 'jobs'));
        $odd = JobQuery::fromSavedState('{"stages":[1,{"x":2},"briefed"],"owner":["mine"],"text":{"a":1},"sort":5}', 'jobs');
        t_true($odd !== null);
        t_eq([Stage::Briefed], $odd->stages, 'non-string items ignored');
        t_eq(OwnerFilter::All, $odd->owner);
        t_eq('', $odd->text);
        t_eq('due,job_number', $odd->sortToken());
        t_eq(JobGroupBy::None, JobQuery::fromQuery(['group' => 'brand'], 'board')->groupBy, 'the board does not group');
    },
    'job query: header clicks flip, replace and add sort keys (table)' => function (): void {
        $cases = [
            ['due,job_number', 'due', false, '-due'],
            ['-due', 'due', false, 'due'],
            ['due,job_number', 'title', false, 'title'],
            ['due,job_number', 'title', true, 'due,title'],
            ['due,title', 'title', true, 'due,-title'],
            ['due,title', 'due', true, '-due,title'],
            ['due', 'stage', true, 'due,stage'],
        ];
        foreach ($cases as [$sort, $field, $add, $want]) {
            $q = JobQuery::fromQuery(['sort' => $sort], 'jobs');
            t_eq($want, $q->nextSort(JobSortField::from($field), $add), "$sort + $field" . ($add ? ' (shift)' : ''));
        }
        t_eq([2, true], JobQuery::fromQuery(['sort' => 'due,-title'], 'jobs')->sortPosition(JobSortField::Title));
        t_eq(null, JobQuery::fromQuery(['sort' => 'due'], 'jobs')->sortPosition(JobSortField::Title));
    },
    'job query: due windows in SAST dates (table)' => function (): void {
        // Friday 9 October 2026
        $cases = [
            [DueWindow::Overdue, [null, '2026-10-08']],
            [DueWindow::Today, ['2026-10-09', '2026-10-09']],
            [DueWindow::ThisWeek, ['2026-10-09', '2026-10-11']],
            [DueWindow::Next14, ['2026-10-09', '2026-10-23']],
            [DueWindow::NoDate, [null, null]],
        ];
        foreach ($cases as [$w, $want]) {
            t_eq($want, $w->range('2026-10-09'), $w->value);
        }
        t_eq(['2026-12-28', '2027-01-03'], DueWindow::ThisWeek->range('2026-12-28'), 'week crosses the year');
    },
    'job query: board stage toggles keep pipeline order' => function (): void {
        $q = JobQuery::defaults('board')->withStages([Stage::Cancelled, Stage::Draft, Stage::Done]);
        t_eq([Stage::Draft, Stage::Done, Stage::Cancelled], $q->stages);
        t_eq('draft,done,cancelled', $q->toQuery()['stages']);
        t_eq(JobQuery::defaults('board')->stages, JobQuery::defaults('board')->withStages([])->stages, 'never empty');
        t_eq(Role::Traffic, JobQuery::fromQuery(['role' => 'Traffic'], 'board')->role);
    },
];

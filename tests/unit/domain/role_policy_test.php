<?php
declare(strict_types=1);

use App\Domain\BriefVersion;
use App\Domain\JobAction;
use App\Domain\MyDay;
use App\Domain\MyDayMode;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Types\Activity;
use App\Domain\Types\Assignment;
use App\Domain\Types\MyDayChange;
use App\Domain\Types\MyDayJob;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

/** Role rules on top of the matrix: nav, My day mode, draft visibility, Start work. */
function rp_job(string $id, array $o = []): MyDayJob
{
    $o += ['stage' => Stage::InProgress, 'due' => null, 'sentAt' => '2026-10-01 08:00:00', 'version' => new BriefVersion(1, 0, 0), 'traffic' => false, 'team' => false];
    return new MyDayJob($id, strtoupper($id), 'Title ' . $id, 'Camp', 'Brand', $o['stage'], null, '', $o['version'], $o['sentAt'], false, $o['due'], false, $o['traffic'], $o['team']);
}

return [
    'role policy: nav items per role (table)' => function (): void {
        // role => [today, briefs, jobs, board, campaigns, social, admin-users, admin-system]
        $want = [
            'COO' => '11111111', 'ECD' => '11111111', 'AM' => '11111100', 'PM' => '11111100', 'Producer' => '11111100',
            'Traffic' => '10110000', 'CD' => '10110000', 'Copywriter' => '10110000', 'Designer' => '10110000', 'QA' => '10110000',
            'Developer' => '10110000', 'SEO' => '10110000', 'Social' => '10110100', 'Client' => '00000000',
        ];
        $items = ['today', 'briefs', 'jobs', 'board', 'campaigns', 'social', 'admin-users', 'admin-system'];
        foreach ($want as $role => $bits) {
            $u = bf_user(Role::from($role));
            foreach ($items as $i => $item) {
                t_eq($bits[$i] === '1', Policy::canSeeNav($u, $item), $role . ' ' . $item);
            }
            t_true(!Policy::canSeeNav($u, 'no-such-item'), 'unknown items are hidden');
        }
    },
    'role policy: My day mode per role' => function (): void {
        foreach ([Role::COO, Role::ECD, Role::AM, Role::PM, Role::Producer] as $r) {
            t_eq(MyDayMode::Owner, Policy::myDayMode(bf_user($r)), $r->value);
        }
        t_eq(MyDayMode::Traffic, Policy::myDayMode(bf_user(Role::Traffic)));
        foreach ([Role::CD, Role::Copywriter, Role::Designer, Role::QA, Role::Developer, Role::SEO, Role::Social] as $r) {
            t_eq(MyDayMode::Assigned, Policy::myDayMode(bf_user($r)), $r->value);
        }
    },
    'role policy: unsent drafts are visible only to who may read them' => function (): void {
        $draft = bf_access('draft', ['creatorId' => 'am1']);
        t_true(Policy::canViewJob(bf_user(Role::AM, 'am1'), $draft)->allowed, 'creator');
        t_true(!Policy::canViewJob(bf_user(Role::AM, 'am2'), $draft)->allowed, 'another AM (AM-6)');
        t_true(!Policy::canViewJob(bf_user(Role::Traffic, 't'), $draft)->allowed, 'Traffic (TRF-4)');
        t_true(!Policy::canViewJob(bf_user(Role::CD, 'cd'), $draft)->allowed, 'CD');
        $preselected = bf_access('draft', ['creatorId' => 'am1', 'assignments' => [new Assignment(Role::Designer, 'dz', 'D', Role::Designer)]]);
        t_true(!Policy::canViewJob(bf_user(Role::Designer, 'dz'), $preselected)->allowed, 'a pre-selected Designer does not see the draft');
        t_true(Policy::canViewJob(bf_user(Role::COO), $draft)->allowed, 'COO');
        $claimable = bf_access('draft');
        t_true(Policy::canViewJob(bf_user(Role::PM, 'pm'), $claimable)->allowed, 'a manager may see a draft nobody created (claim)');
        t_true(!Policy::canViewJob(bf_user(Role::Traffic, 't'), $claimable)->allowed);
        t_true(Policy::canViewJob(bf_user(Role::Traffic, 't'), bf_access('briefed'))->allowed, 'Traffic sees every sent job');
        t_true(!Policy::canViewJob(bf_user(Role::Designer, 'dz'), bf_access('briefed'))->allowed, 'makers: assigned only');
        t_true(Policy::canViewJob(bf_user(Role::Designer, 'dz'), bf_access('briefed', ['assetAssigneeIds' => ['dz']]))->allowed, 'asset assignee');
        t_true(Policy::canViewJob(bf_user(Role::CD, 'cd'), bf_access('briefed'))->allowed, 'CD reads all sent jobs (matrix view_all_jobs Y)');
    },
    'role policy: Start work needs a CD or creative and is implicit for makers' => function (): void {
        $traffic = bf_user(Role::Traffic, 't');
        t_eq('Assign the CD or a creative before starting work.', Policy::canTransition($traffic, bf_access('briefed'), JobAction::Start)->reason);
        $teamed = bf_access('briefed', ['assignments' => [new Assignment(Role::CD, 'cd', 'C', Role::CD), new Assignment(Role::Designer, 'dz', 'D', Role::Designer)]]);
        t_true(Policy::canTransition($traffic, $teamed, JobAction::Start)->allowed);
        t_true(Policy::canTransition(bf_user(Role::CD, 'cd'), $teamed, JobAction::Start)->allowed, 'the assigned CD');
        t_true(!Policy::canTransition(bf_user(Role::Designer, 'dz'), $teamed, JobAction::Start)->allowed, 'the Designer starts by starting an asset');
        t_true(!Policy::canTransition(bf_user(Role::AM, 'am1'), bf_access('briefed', ['creatorId' => 'am1', 'assetAssigneeIds' => ['dz']]), JobAction::Start)->allowed, 'AM: matrix deny');
        t_true(Policy::canTransition(bf_user(Role::PM, 'pm'), bf_access('briefed', ['creatorId' => 'pm', 'assetAssigneeIds' => ['dz']]), JobAction::Start)->allowed, 'PM on an owned job');
        t_true(!Policy::canTransition(bf_user(Role::QA, 'qa'), $teamed, JobAction::Start)->allowed, 'QA never starts');
        t_true(!Policy::canTransition($traffic, bf_access('in_progress', ['assetAssigneeIds' => ['dz']]), JobAction::Start)->allowed, 'only from briefed');
        t_true(!Policy::canTransition($traffic, bf_access('in_progress'), JobAction::Submit)->allowed, 'Submit for review is not available yet');
    },
    'role policy: the job viewer carries view_budget for the store' => function (): void {
        t_eq('deny', Policy::jobViewer(bf_user(Role::Traffic))->budgetView->value);
        t_eq('deny', Policy::jobViewer(bf_user(Role::Copywriter))->budgetView->value);
        t_eq('assigned_or_creator', Policy::jobViewer(bf_user(Role::PM))->budgetView->value);
        t_eq('allow', Policy::jobViewer(bf_user(Role::ECD))->budgetView->value);
    },
    'my day (assigned): overdue, due soon and new or updated briefs since the last visit' => function (): void {
        $now = new DateTimeImmutable('2026-10-09 09:00:00', new DateTimeZone('Africa/Johannesburg'));
        $jobs = [
            rp_job('a', ['due' => '2026-10-05']),
            rp_job('b', ['due' => '2026-10-12', 'sentAt' => '2026-10-08 10:00:00', 'version' => new BriefVersion(1, 1, 0)]),
            rp_job('c', ['stage' => Stage::Briefed, 'due' => '2026-11-01', 'sentAt' => '2026-10-09 06:00:00']),
            rp_job('d', ['stage' => Stage::Done, 'due' => '2026-10-01']),
            rp_job('e', ['stage' => Stage::Draft, 'due' => '2026-10-01', 'sentAt' => null]),
        ];
        $edited = new MyDayChange(new Activity('x1', 'c', 'am', 'Amy', 'brief_edited', 'brief', 'b', [], '2026-10-09 05:00:00'), 'C', 'Title c', true);
        $sent = new MyDayChange(new Activity('x2', 'c', 'am', 'Amy', 'brief_sent', 'job', 'c', [], '2026-10-09 06:00:00'), 'C', 'Title c', true);
        $r = MyDay::buildAssigned('me', MyDayMode::Assigned, $jobs, [$edited, $sent], '2026-10-07 00:00:00', $now);
        t_eq(MyDayMode::Assigned, $r->mode);
        t_eq(['A'], array_map(static fn ($i) => $i->jobNumber, $r->overdue->items), 'done and draft jobs never show');
        t_eq(['B'], array_map(static fn ($i) => $i->jobNumber, $r->dueSoon->items));
        t_true($r->briefs !== null && $r->team === null);
        t_eq(['C', 'B'], array_map(static fn ($i) => $i->jobNumber, $r->briefs->items), 'newest send first');
        t_eq('New brief v1.0.0.', $r->briefs->items[0]->reason);
        t_eq('Brief updated to v1.1.0.', $r->briefs->items[1]->reason);
        t_eq(1, $r->changedTotal, 'working-copy edits are not shown to assigned users');
        t_eq('brief_sent', $r->changed[0]->activity->verb);
        t_eq(['strip', 'briefs', 'overdue', 'due-soon', 'changed'], MyDay::sectionKeys(MyDayMode::Assigned));
    },
    'my day (Traffic): briefs waiting for Traffic are the ones on which I am Traffic with no team' => function (): void {
        $now = new DateTimeImmutable('2026-10-09 09:00:00', new DateTimeZone('Africa/Johannesburg'));
        $jobs = [
            rp_job('t1', ['stage' => Stage::Briefed, 'traffic' => true, 'team' => true, 'sentAt' => '2026-10-01 08:00:00']),
            rp_job('t2', ['stage' => Stage::Briefed, 'traffic' => true, 'team' => false, 'sentAt' => '2026-10-08 08:00:00', 'version' => new BriefVersion(1, 0, 1)]),
            rp_job('t3', ['stage' => Stage::Briefed, 'traffic' => false, 'team' => true]),
            rp_job('t4', ['stage' => Stage::InProgress, 'traffic' => true, 'team' => false, 'sentAt' => '2026-09-01 08:00:00']),
        ];
        $r = MyDay::buildAssigned('me', MyDayMode::Traffic, $jobs, [], '2026-10-07 00:00:00', $now);
        t_true($r->team !== null && $r->briefs === null);
        t_eq(['T2', 'T1'], array_map(static fn ($i) => $i->jobNumber, $r->team->items));
        t_eq('Brief updated to v1.0.1.', $r->team->items[0]->reason);
        t_eq('Needs a team: assign the CD and creatives.', $r->team->items[1]->reason);
        t_eq(1, $r->attention, 'the job that needs a team counts for the nav badge');
        t_eq(2, $r->strip->waitingOnMe);
    },
];

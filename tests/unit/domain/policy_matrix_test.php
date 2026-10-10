<?php
declare(strict_types=1);

use App\Domain\GridField;
use App\Domain\JobReviewStatus;
use App\Domain\MediaKind;
use App\Domain\PartState;
use App\Domain\ReviewPolicy;
use App\Domain\JobAction;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Types\AssetReview;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobAccess;
use App\Domain\Types\JobReview;
use App\Domain\Types\Team;
use App\Domain\Types\User;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

/**
 * Policy must agree with tests/fixtures/roles/policy-matrix.json for every
 * brief and job action it implements. For each action: a fixture stage where
 * the action's stage conditions pass, then for each role four relations
 * (unrelated, assigned, creator, own brand) checked against the cell.
 * Actions without an entry here are skipped and listed (later phases, or rows
 * a builder adds such as the social_* actions).
 */
function pmt_cases(): array
{
    $j = static fn (string $stage, array $o = []): array => [$stage, $o];
    return [
        'create_brief' => [null, static fn (User $u, ?JobAccess $a) => Policy::canCreateBrief($u)],
        'manage_campaign' => [null, static fn (User $u, ?JobAccess $a) => Policy::canManageCampaign($u)],
        'manage_users' => [null, static fn (User $u, ?JobAccess $a) => Policy::canManageUsers($u)],
        'admin_system' => [null, static fn (User $u, ?JobAccess $a) => Policy::canViewSystem($u)],
        'view_brief_draft' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canViewBriefDraft($u, $a)],
        'view_brief_sent' => [$j('briefed'), static fn (User $u, JobAccess $a) => Policy::canViewBriefSent($u, $a)],
        'edit_brief_draft' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canEditBriefDraft($u, $a)],
        'edit_brief_sent' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canEditBriefSent($u, $a)],
        'send_brief' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canSendBrief($u, $a)],
        'send_brief_update' => [$j('in_progress', ['hasUnsentChanges' => true]), static fn (User $u, JobAccess $a) => Policy::canSendBriefUpdate($u, $a)],
        'recall_brief' => [$j('briefed'), static fn (User $u, JobAccess $a) => Policy::canRecallBrief($u, $a)],
        'assign_traffic' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canAssign($u, $a, Role::Traffic)],
        'assign_creatives' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canAssign($u, $a, Role::Designer)],
        'assign_account_roles' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canAssign($u, $a, Role::AM)],
        'transition:draft->briefed' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Send)],
        'transition:briefed->draft' => [$j('briefed'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Recall)],
        // Start work: needs a CD or creative on the job (an asset assignee here, so every relation keeps it)
        'transition:briefed->in_progress' => [$j('briefed', ['assetAssigneeIds' => ['maker9']]), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Start)],
        'transition:approved_client->done' => [$j('approved_client'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::MarkDone)],
        'transition:approved_client->ready_to_schedule' => [$j('approved_client'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::ReadyToSchedule)],
        'transition:ready_to_schedule->scheduled' => [$j('ready_to_schedule'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Schedule)],
        'transition:scheduled->live' => [$j('scheduled'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::GoLive)],
        'transition:live->done' => [$j('live'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::MarkDone)],
        'transition:workable->waiting' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Wait)],
        'transition:waiting->resume' => [$j('waiting'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Resume)],
        'transition:workable->on_hold' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Hold)],
        'transition:on_hold->resume' => [$j('on_hold'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Resume)],
        'transition:open->cancelled' => [$j('briefed'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Cancel)],
        'transition:done->archived' => [$j('done'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Archive)],
        'transition:cancelled->archived' => [$j('cancelled'), static fn (User $u, JobAccess $a) => Policy::canTransition($u, $a, JobAction::Archive)],
        'view_all_jobs' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canViewJob($u, $a)],
        'view_budget' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canViewBudget($u, $a)],
        'view_hours' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canViewHours($u, $a)],
        // Phase 3: jobs grid/board
        'edit_job_field:title' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canEditJobField($u, $a, GridField::Title)],
        'edit_job_field:campaign_id' => [$j('draft'), static fn (User $u, JobAccess $a) => Policy::canEditJobField($u, $a, GridField::CampaignId)],
        'edit_job_field:due_date' => [$j('briefed'), static fn (User $u, JobAccess $a) => Policy::canEditJobField($u, $a, GridField::DueDate)],
        'edit_job_field:hours_estimate' => [$j('in_review'), static fn (User $u, JobAccess $a) => Policy::canEditJobField($u, $a, GridField::HoursEstimate)],
        'edit_job_field:waiting_on' => [$j('waiting'), static fn (User $u, JobAccess $a) => Policy::canEditJobField($u, $a, GridField::WaitingOn)],
        'edit_job_field:waiting_reason' => [$j('waiting'), static fn (User $u, JobAccess $a) => Policy::canEditJobField($u, $a, GridField::WaitingReason)],
        // Owner decisions 2026-10
        'assign_task_roles' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canAssignTaskRole($u, $a, Role::Developer)],
        'view_all_assets' => [$j('in_progress'), static fn (User $u, JobAccess $a) => Policy::canViewJobAssets($u, $a)],
        'override_asset_status' => [$j('scheduled'), static fn (User $u, JobAccess $a) => Policy::canOverrideAssetStatus($u, $a)],
        'manage_brand_logo' => [null, static fn (User $u, ?JobAccess $a) => Policy::canSetBrandLogo($u)],
        'view_overrides_report' => [null, static fn (User $u, ?JobAccess $a) => Policy::canViewOverridesReport($u)],
        'add_demo_role_tasks' => [null, static fn (User $u, ?JobAccess $a) => Policy::canAddDemoRoleTasks($u, true)],
        // Reviews (owner spec 2026-10): ReviewPolicy with review state that meets each action's conditions
        'view_internal_feedback' => [$j('in_review'), static fn (User $u, JobAccess $a) => ReviewPolicy::canView($u, $a)],
        'submit_for_review' => [$j('in_progress'), static fn (User $u, JobAccess $a) => ReviewPolicy::canSubmit($u, $a, pmt_asset($u, $a), new Team($a->assignments))],
        'approve_internal' => [$j('in_review'), static fn (User $u, JobAccess $a) => ReviewPolicy::canReview($u, $a, JobReview::empty($a->jobId))],
        'request_ecd_review' => [$j('in_progress'), static fn (User $u, JobAccess $a) => ReviewPolicy::canRequestEcdReview($u, $a, JobReview::empty($a->jobId))],
        'approve_job_ecd' => [$j('in_review'), static fn (User $u, JobAccess $a) => ReviewPolicy::canApproveAsEcd($u, $a, pmt_jr(true, false, false), JobReviewStatus::AllApproved)],
        'reassign_review' => [$j('in_review'), static fn (User $u, JobAccess $a) => ReviewPolicy::canReassign($u, $a, JobReview::empty($a->jobId))],
        'mark_ready_for_client' => [$j('approved_internal'), static fn (User $u, JobAccess $a) => ReviewPolicy::canMarkReady($u, $a, pmt_jr(true, true, false), JobReviewStatus::AllApproved)],
        'send_to_client' => [$j('approved_internal'), static fn (User $u, JobAccess $a) => ReviewPolicy::canSendToClient($u, $a, pmt_jr(true, true, true), JobReviewStatus::AllApproved)],
    ];
}

/** An asset of the fixture job, assigned to the actor when the actor is on the job ("asset is assigned to the actor"). */
function pmt_asset(User $u, JobAccess $a): AssetReview
{
    $mine = $a->isAssigned($u->id);
    return new AssetReview('a1', $a->jobId, 'MERC-004_Static_01', 'image', 'social-static', 'Inbox', $mine ? $u->id : null, $mine ? $u->role : null, '', '', '', 0,
        null, 0, '', '', MediaKind::Link, '', '', '', PartState::Missing, PartState::Missing, null, null, null, false, 0, null, '', null, '');
}

function pmt_jr(bool $cd, bool $ecd, bool $ready): JobReview
{
    $at = '2026-10-09 10:00:00';
    return new JobReview('j1', 1, null, null, null, $cd ? 'cd9' : null, '', $cd ? $at : null, $ecd ? 'ecd9' : null, '', $ecd ? $at : null,
        $ready ? 'am9' : null, $ready ? $at : null, null, null, null, '', null, 1);
}

/**
 * Matrix cells whose condition makes the explicit action impossible for some
 * roles: makers start a job only implicitly, by starting their asset.
 * @return array<string,list<string>>
 */
function pmt_implicit_only(): array
{
    return ['transition:briefed->in_progress' => ['Copywriter', 'Designer', 'Developer', 'SEO', 'Social']];
}

/**
 * Matrix actions with no screen or route yet, each with the reason. Every
 * matrix action must be checked by pmt_cases() or listed here.
 * @return array<string,string>
 */
function pmt_not_yet(): array
{
    $out = [
        'manage_tasks' => 'tasks are edited in legacy',
        'edit_asset_schedule' => 'asset scheduling comes with the creative queue',
        'edit_asset_work' => 'asset work comes with the creative queue',
        'edit_job_field:description' => 'no grid cell; edited in the brief editor (edit_brief_*)',
        'edit_job_field:brief_date' => 'no grid cell; edited in the brief editor (edit_brief_*)',
        'edit_job_field:first_go_live' => 'no grid cell; edited in the brief editor (edit_brief_*)',
        'edit_job_field:last_go_live' => 'no grid cell; edited in the brief editor (edit_brief_*)',
        'edit_job_field:budget' => 'no grid cell; edited in the brief editor (edit_brief_*)',
        'edit_job_field:sort_order' => 'no manual job ordering yet',
        'transition:in_progress->in_review' => 'implicit: Request review or every post handed in (ReviewRules::stageMoves)',
        'transition:in_review->in_progress' => 'implicit: a rejection in the review flow (ReviewRules::stageMoves)',
        'transition:in_review->approved_internal' => 'implicit: the ECD approval of a CD-approved job (ReviewRules::stageMoves)',
        'transition:approved_internal->in_progress' => 'implicit: a rejection in the review flow; the AM send back comes with the client portal',
        'transition:approved_internal->approved_client' => 'client portal phase',
        'transition:approved_client->in_progress' => 'reopen flow not built',
        'transition:done->in_progress' => 'reopen flow not built',
        'transition:archived->restore' => 'restore flow not built',
        'transition:cancelled->draft' => 'reinstate flow not built',
        'qa_signoff' => 'QA is advisory (Q13) and not part of the review flow yet',
        'give_feedback_internal' => 'feedback is given through approve_internal (reject with feedback) in the review flow',
        'route_feedback' => 'rejections are routed to the part maker automatically; manual routing comes with client feedback',
        'approve_client' => 'client portal phase',
        'give_feedback_client' => 'client portal phase',
        'recommend_signoff' => 'client portal phase',
        'comment' => 'comments come later',
        'manage_brand' => 'no brand admin screen',
        'toggle_demo_mode' => 'the owner flips the demo flag by hand',
        'view_staff_emails' => 'no screen shows staff emails to non-admins',
        'view_capacity' => 'capacity screen comes later',
        'view_wiki' => 'wiki stays in legacy',
        'edit_wiki' => 'wiki stays in legacy',
    ];
    foreach (['social_view_queue', 'social_edit_checklist', 'social_set_ready_to_schedule', 'social_set_scheduled', 'social_set_live',
        'social_edit_live_link', 'social_set_promoted', 'social_archive_post', 'social_set_checking', 'social_edit_test_result'] as $id) {
        $out[$id] = 'Social publishing: SocialPolicy, checked in social_test.php';
    }
    return $out;
}

function pmt_expected(string $cell, string $relation): bool
{
    return match ($cell) {
        'allow' => true,
        'deny' => false,
        'assigned' => $relation === 'assigned',
        'creator' => $relation === 'creator',
        'assigned_or_creator' => $relation === 'assigned' || $relation === 'creator',
        'own_brand' => $relation === 'own_brand',
        default => throw new TestFailure('unknown cell ' . $cell),
    };
}

return [
    'policy agrees with policy-matrix.json for every implemented brief and job action' => function (): void {
        $m = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/roles/policy-matrix.json'), true);
        t_eq(Policy::COLUMNS, $m['roles'], 'column order');
        $cases = pmt_cases();
        $notYet = pmt_not_yet();
        $implicit = pmt_implicit_only();
        $skipped = [];
        $checked = 0;
        foreach ($m['actions'] as $action) {
            $id = (string) $action['id'];
            if (!isset($cases[$id])) {
                t_true(isset($notYet[$id]), $id . ' is neither checked nor listed as not yet functional');
                $skipped[] = $id;
                continue;
            }
            [$fixture, $fn] = $cases[$id];
            foreach ($m['roles'] as $roleName) {
                $role = Role::from($roleName);
                $cell = (string) $action['decisions'][$roleName];
                if ($id !== 'manage_users' && $id !== 'admin_system') {
                    t_eq($cell, Policy::rule($id, $role)->value, "$id table cell for $roleName");
                }
                foreach (['unrelated', 'assigned', 'creator', 'own_brand'] as $rel) {
                    $user = bf_user($role, 'actor', $rel === 'own_brand' ? 'brand1' : 'brand9');
                    $access = null;
                    if ($fixture !== null) {
                        $o = $fixture[1];
                        if ($rel === 'assigned') {
                            $o['assignments'] = [new Assignment($role, 'actor', 'Actor', $role)];
                        }
                        if ($rel === 'creator') {
                            $o['creatorId'] = 'actor';
                        }
                        $access = bf_access($fixture[0], $o);
                    }
                    $want = pmt_expected($cell, $rel) && !in_array($roleName, $implicit[$id] ?? [], true);
                    $got = $fn($user, $access);
                    t_eq($want, $got->allowed, "$id: $roleName ($rel): " . $got->reason);
                    $checked++;
                }
            }
        }
        t_true($checked > 1000, 'checked ' . $checked);
        foreach (array_keys($notYet) as $id) {
            $known = false;
            foreach ($m['actions'] as $action) {
                $known = $known || $action['id'] === $id;
            }
            t_true($known, $id . ' is listed as not yet functional but is not in the matrix');
        }
        fwrite(STDERR, 'note: policy matrix: ' . count($cases) . ' actions checked for all 14 roles; ' . count($skipped) . ' not yet functional: ' . implode(', ', $skipped) . "\n");
    },
    'policy: stage conditions on brief and job actions' => function (): void {
        $am = bf_user(Role::AM, 'am1');
        $own = static fn (string $stage, array $o = []): JobAccess => bf_access($stage, $o + ['creatorId' => 'am1']);
        t_true(!Policy::canEditBriefSent($am, $own('done'))->allowed, 'closed');
        t_true(Policy::canEditBriefSent($am, $own('waiting'))->allowed);
        t_true(Policy::canEditBrief($am, $own('draft'))->allowed);
        t_true(!Policy::canEditBrief($am, $own('cancelled', ['briefSent' => false]))->allowed, 'a cancelled draft is closed');
        t_true(!Policy::canSendBrief($am, $own('briefed'))->allowed);
        t_true(!Policy::canSendBrief($am, $own('draft', ['briefSent' => true]))->allowed, 'recalled: send an update instead');
        t_true(Policy::canSendBriefUpdate($am, $own('draft', ['briefSent' => true]))->allowed);
        t_true(!Policy::canSendBriefUpdate($am, $own('cancelled'))->allowed);
        t_true(!Policy::canRecallBrief($am, $own('briefed', ['anyAssetStarted' => true]))->allowed);
        t_true(!Policy::canRecallBrief($am, $own('in_progress'))->allowed);
        t_true(Policy::canAssign($am, $own('draft'), Role::CD)->allowed, 'AM suggests creatives while draft');
        t_true(!Policy::canAssign($am, $own('briefed'), Role::CD)->allowed, 'Traffic assigns after send');
        t_true(Policy::canAssign($am, $own('briefed'), Role::Traffic)->allowed);
        t_true(Policy::canAssign(bf_user(Role::Traffic, 't'), bf_access('in_progress'), Role::Designer)->allowed);
        t_true(!Policy::canAssign($am, $own('done'), Role::Traffic)->allowed);
        t_true(!Policy::canAssign(bf_user(Role::COO), bf_access('draft'), Role::COO)->allowed, 'COO is not a slot');
        t_true(!Policy::canTransition(bf_user(Role::Traffic, 't'), bf_access('draft'), JobAction::Wait)->allowed, 'Traffic not from draft');
        t_true(!Policy::canTransition(bf_user(Role::Traffic, 't'), bf_access('draft'), JobAction::Hold)->allowed);
        t_true(Policy::canTransition(bf_user(Role::Traffic, 't'), bf_access('briefed'), JobAction::Hold)->allowed);
        t_true(!Policy::canTransition($am, $own('in_progress'), JobAction::Archive)->allowed, 'archive needs done or cancelled');
        t_true(!Policy::canTransition($am, $own('in_review'), JobAction::ApproveInternal)->allowed, 'AM never approves internally');
        t_true(!Policy::canTransition(bf_user(Role::COO), bf_access('in_review'), JobAction::ApproveInternal)->allowed, 'reviews not available yet');
        t_true(!Policy::canTransition($am, $own('scheduled'), JobAction::GoLive)->allowed, 'AM cannot set live');
        t_true(Policy::canTransition($am, $own('live'), JobAction::MarkDone)->allowed);
    },
    'policy: owner decisions 2026-10: task slots, assets, overrides, demo tasks, assigned-only filters' => function (): void {
        $tr = bf_user(Role::Traffic, 't');
        $am = bf_user(Role::AM, 'am1');
        $own = static fn (string $stage): JobAccess => bf_access($stage, ['creatorId' => 'am1']);
        // [user, access, slot, canSetSlot]: Developer, SEO, Producer open to Traffic after the send; owners before and after.
        $cases = [
            [$tr, bf_access('draft'), Role::Producer, false], [$tr, bf_access('briefed'), Role::Developer, true], [$tr, bf_access('in_progress'), Role::SEO, true],
            [$tr, bf_access('in_progress'), Role::Producer, true], [$tr, bf_access('in_progress'), Role::PM, false], [$tr, bf_access('done'), Role::Developer, false],
            [$am, $own('draft'), Role::Developer, true], [$am, $own('briefed'), Role::Developer, true], [$am, $own('briefed'), Role::Designer, false],
            [$am, bf_access('briefed'), Role::SEO, false], [$am, $own('in_progress'), Role::Producer, true],
            [bf_user(Role::Designer, 'd'), bf_access('in_progress'), Role::Developer, false], [bf_user(Role::Developer, 'dev'), bf_access('in_progress'), Role::Developer, false],
        ];
        foreach ($cases as $i => [$u, $a, $slot, $want]) {
            t_eq($want, Policy::canSetSlot($u, $a, $slot)->allowed, "slot case $i " . $u->role->value . ' ' . $slot->value . ' ' . $a->stage->value);
        }
        t_true(!Policy::canAssignTaskRole($tr, bf_access('in_progress'), Role::Designer)->allowed, 'only the three task roles');
        // Assets page and overrides: Traffic, COO, ECD on sent jobs only.
        foreach ([Role::Traffic, Role::COO, Role::ECD] as $r) {
            t_true(Policy::canOverrideAssetStatus(bf_user($r), bf_access('live'))->allowed, $r->value);
            t_true(!Policy::canOverrideAssetStatus(bf_user($r), bf_access('draft'))->allowed, $r->value . ' draft');
            t_true(Policy::canViewJobAssets(bf_user($r), bf_access('done'))->allowed, $r->value . ' done job readable');
        }
        foreach ([Role::Designer, Role::AM, Role::CD, Role::Producer, Role::Social, Role::Client] as $r) {
            $mine = bf_access('in_progress', ['assignments' => [new Assignment($r, 'u1', 'U', $r)], 'creatorId' => 'u1']);
            t_true(!Policy::canOverrideAssetStatus(bf_user($r), $mine)->allowed, $r->value . ' cannot override, even on their job');
        }
        t_true(!Policy::canAddDemoRoleTasks(bf_user(Role::COO), false)->allowed, 'demo tasks need demo mode');
        t_true(!Policy::canAddDemoRoleTasks(bf_user(Role::ECD), true)->allowed, 'COO only');
        t_true(Policy::canSeeNav(bf_user(Role::COO), 'admin-overrides'));
        t_true(!Policy::canSeeNav(bf_user(Role::ECD), 'admin-overrides'));
        // Assigned-only roles: filter lists limited, no Owner filter.
        $assignedOnly = [Role::Copywriter, Role::Designer, Role::Developer, Role::SEO, Role::QA, Role::Social];
        foreach (Role::cases() as $r) {
            t_eq(in_array($r, $assignedOnly, true), Policy::seesOnlyAssignedJobs(bf_user($r)), 'assigned only: ' . $r->value);
        }
    },
    'policy: claim AM' => function (): void {
        foreach ([Role::AM, Role::PM, Role::Producer, Role::COO, Role::ECD] as $r) {
            t_true(Policy::canClaimAm(bf_user($r), bf_access('in_progress'))->allowed, $r->value);
        }
        foreach ([Role::Traffic, Role::CD, Role::Designer, Role::Client, Role::Social] as $r) {
            t_true(!Policy::canClaimAm(bf_user($r), bf_access('in_progress'))->allowed, $r->value);
        }
        $taken = bf_access('in_progress', ['assignments' => [new Assignment(Role::AM, 'x', 'X', Role::AM)]]);
        t_eq('This job already has an AM.', Policy::canClaimAm(bf_user(Role::AM), $taken)->reason);
        t_true(!Policy::canClaimAm(bf_user(Role::AM), bf_access('archived'))->allowed);
    },
];

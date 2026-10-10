<?php
declare(strict_types=1);

use App\Domain\JobAction;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobAccess;
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
    ];
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
        $skipped = [];
        $checked = 0;
        foreach ($m['actions'] as $action) {
            $id = (string) $action['id'];
            if (!isset($cases[$id])) {
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
                    $want = pmt_expected($cell, $rel);
                    $got = $fn($user, $access);
                    t_eq($want, $got->allowed, "$id: $roleName ($rel): " . $got->reason);
                    $checked++;
                }
            }
        }
        t_true($checked > 1000, 'checked ' . $checked);
        fwrite(STDERR, 'note: policy matrix test skipped ' . count($skipped) . ' actions not implemented in Phase 2: ' . implode(', ', $skipped) . "\n");
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

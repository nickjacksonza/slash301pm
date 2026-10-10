<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\JobAccess;
use App\Domain\Types\JobViewer;
use App\Domain\Types\SavedView;
use App\Domain\Types\User;

/**
 * One pure function per action. The actor always comes from the session.
 * Job actions follow docs/roles.md section 4 (tests/fixtures/roles/policy-matrix.json):
 * ROWS holds each action's cells per role, in the matrix column order
 * COO ECD AM Traffic PM Producer CD Copywriter Designer QA Developer SEO Social Client,
 * and each can*() adds the action's stage conditions. A matrix test checks both.
 */
final class Policy
{
    /** @var list<string> column order of ROWS */
    public const COLUMNS = ['COO', 'ECD', 'AM', 'Traffic', 'PM', 'Producer', 'CD', 'Copywriter', 'Designer', 'QA', 'Developer', 'SEO', 'Social', 'Client'];

    private const ROWS = [
        'create_brief' => 'Y Y Y - Y Y - - - - - - - -',
        'view_brief_draft' => 'Y Y AC - AC AC - - - - - - - -',
        'view_brief_sent' => 'Y Y Y Y Y Y Y A A A A A A B',
        'edit_brief_draft' => 'Y Y AC - AC AC - - - - - - - -',
        'edit_brief_sent' => 'Y Y AC - AC AC - - - - - - - -',
        'send_brief' => 'Y Y AC - AC AC - - - - - - - -',
        'send_brief_update' => 'Y Y AC - AC AC - - - - - - - -',
        'recall_brief' => 'Y Y AC - AC AC - - - - - - - -',
        'assign_traffic' => 'Y Y AC Y AC AC - - - - - - - -',
        'assign_creatives' => 'Y Y AC Y AC AC - - - - - - - -',
        'assign_account_roles' => 'Y Y AC - AC AC - - - - - - - -',
        'transition:draft->briefed' => 'Y Y AC - AC AC - - - - - - - -',
        'transition:briefed->draft' => 'Y Y AC - AC AC - - - - - - - -',
        'transition:briefed->in_progress' => 'Y Y - Y AC AC A A A - A A A -',
        'transition:approved_client->done' => 'Y Y AC Y AC AC - - - - - - - -',
        'transition:approved_client->ready_to_schedule' => 'Y Y - - - - - - - - - - A -',
        'transition:ready_to_schedule->scheduled' => 'Y Y - - - - - - - - - - A -',
        'transition:scheduled->live' => 'Y Y - - - - - - - - - - A -',
        'transition:live->done' => 'Y Y AC Y AC AC - - - - - - - -',
        'transition:workable->waiting' => 'Y Y AC Y AC AC A - - - - - - -',
        'transition:waiting->resume' => 'Y Y AC Y AC AC A - - - - - - -',
        'transition:workable->on_hold' => 'Y Y AC Y AC AC - - - - - - - -',
        'transition:on_hold->resume' => 'Y Y AC Y AC AC - - - - - - - -',
        'transition:open->cancelled' => 'Y Y AC - AC AC - - - - - - - -',
        'transition:done->archived' => 'Y Y AC Y AC AC - - - - - - - -',
        'transition:cancelled->archived' => 'Y Y AC Y AC AC - - - - - - - -',
        'manage_campaign' => 'Y Y Y - Y Y - - - - - - - -',
        'view_all_jobs' => 'Y Y Y Y Y Y Y A A A A A A B',
        'view_budget' => 'Y Y AC - AC AC - - - - - - - -',
        'view_hours' => 'Y Y Y Y Y Y A A A A A A A -',
        // Phase 3: jobs grid/board
        'edit_job_field:title' => 'Y Y AC - AC AC - - - - - - - -',
        'edit_job_field:campaign_id' => 'Y Y AC - AC AC - - - - - - - -',
        'edit_job_field:due_date' => 'Y Y AC - AC AC - - - - - - - -',
        'edit_job_field:hours_estimate' => 'Y Y AC - AC AC - - - - - - - -',
        'edit_job_field:budget' => 'Y Y AC - AC AC - - - - - - - -',
        'edit_job_field:waiting_on' => 'Y Y AC Y AC AC A - - - - - - -',
        'edit_job_field:waiting_reason' => 'Y Y AC Y AC AC A - - - - - - -',
        // Owner decisions 2026-10: brand logos, asset status overrides, role task slots, demo tasks
        'manage_brand_logo' => 'Y Y Y - Y Y - - - - - - - -',
        'assign_task_roles' => 'Y Y AC Y AC AC - - - - - - - -',
        'view_all_assets' => 'Y Y - Y - - - - - - - - - -',
        'override_asset_status' => 'Y Y - Y - - - - - - - - - -',
        'view_overrides_report' => 'Y - - - - - - - - - - - - -',
        'add_demo_role_tasks' => 'Y - - - - - - - - - - - - -',
    ];

    public static function canManageUsers(User $actor): Decision
    {
        return self::isAdmin($actor) ? Decision::allow() : Decision::deny('Only the COO or ECD can manage users.');
    }

    public static function canViewSystem(User $actor): Decision
    {
        return self::isAdmin($actor) ? Decision::allow() : Decision::deny('Only the COO or ECD can open system pages.');
    }

    public static function canDeactivate(User $actor, User $target): Decision
    {
        $d = self::canManageUsers($actor);
        if (!$d->allowed) {
            return $d;
        }
        if ($actor->id === $target->id) {
            return Decision::deny('You cannot deactivate your own account.');
        }
        return Decision::allow();
    }

    /**
     * BetaGate: may this user use the new UI?
     * @param list<Role> $newUiRoles
     */
    public static function canUseNewUi(User $actor, array $newUiRoles): Decision
    {
        foreach ($newUiRoles as $role) {
            if ($actor->role === $role) {
                return Decision::allow();
            }
        }
        return Decision::deny('The new app is not open to your role yet.');
    }

    public static function isAdmin(User $actor): bool
    {
        return $actor->role === Role::COO || $actor->role === Role::ECD;
    }

    // ---- Briefs -------------------------------------------------------------

    public static function canCreateBrief(User $u): Decision
    {
        return self::cell('create_brief', $u, null, 'Only account managers, PMs, producers, the COO and the ECD create briefs.');
    }

    /** Read the draft or the unsent working copy. */
    public static function canViewBriefDraft(User $u, JobAccess $j): Decision
    {
        return self::cell('view_brief_draft', $u, $j, 'Drafts and unsent changes are only visible to the brief owner.');
    }

    /** Read the latest sent version. */
    public static function canViewBriefSent(User $u, JobAccess $j): Decision
    {
        if (!$j->briefSent) {
            return Decision::deny('This brief has not been sent yet.');
        }
        return self::cell('view_brief_sent', $u, $j, 'You are not on this job.');
    }

    public static function canEditBriefDraft(User $u, JobAccess $j): Decision
    {
        if ($j->briefSent) {
            return Decision::deny('This brief has been sent; edits go to the working copy.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '; its brief can no longer change.');
        }
        return self::cell('edit_brief_draft', $u, $j, 'Only the brief owner can edit this draft.');
    }

    public static function canEditBriefSent(User $u, JobAccess $j): Decision
    {
        if (!$j->briefSent) {
            return Decision::deny('This brief is still a draft.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '; its brief can no longer change.');
        }
        return self::cell('edit_brief_sent', $u, $j, 'Only the brief owner can change a sent brief.');
    }

    /** Draft or working copy, whichever applies. */
    public static function canEditBrief(User $u, JobAccess $j): Decision
    {
        return $j->briefSent ? self::canEditBriefSent($u, $j) : self::canEditBriefDraft($u, $j);
    }

    public static function canSendBrief(User $u, JobAccess $j): Decision
    {
        if ($j->briefSent) {
            return Decision::deny('This brief was sent before; send an update instead.');
        }
        if ($j->stage !== Stage::Draft) {
            return Decision::deny('Only a draft can be sent to Traffic.');
        }
        return self::cell('send_brief', $u, $j, 'Only the brief owner can send it.');
    }

    /** Send update (also re-sending a recalled brief). Whether anything changed is BriefSend's check. */
    public static function canSendBriefUpdate(User $u, JobAccess $j): Decision
    {
        if (!$j->briefSent) {
            return Decision::deny('Send the brief to Traffic first.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '; its brief can no longer change.');
        }
        return self::cell('send_brief_update', $u, $j, 'Only the brief owner can send an update.');
    }

    public static function canRecallBrief(User $u, JobAccess $j): Decision
    {
        if ($j->stage !== Stage::Briefed) {
            return Decision::deny('Only a briefed job can be recalled.');
        }
        if ($j->anyAssetStarted) {
            return Decision::deny('An asset has started, so the brief cannot be recalled.');
        }
        return self::cell('recall_brief', $u, $j, 'Only the brief owner can recall it.');
    }

    // ---- Assignments ----------------------------------------------------------

    /** Set or clear one slot. Traffic: assign_traffic; AM, PM, Producer, Client: assign_account_roles; the rest: assign_creatives. */
    public static function canAssign(User $u, JobAccess $j, Role $slot): Decision
    {
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '.');
        }
        if ($slot === Role::COO || $slot === Role::ECD) {
            return Decision::deny('That is not a job slot.');
        }
        if ($slot === Role::Traffic) {
            return self::cell('assign_traffic', $u, $j, 'You cannot change Traffic on this job.');
        }
        if ($slot === Role::AM || $slot === Role::PM || $slot === Role::Producer || $slot === Role::Client) {
            return self::cell('assign_account_roles', $u, $j, 'You cannot change the account team on this job.');
        }
        $d = self::cell('assign_creatives', $u, $j, 'Traffic assigns the creative team.');
        if ($d->allowed && in_array($u->role, [Role::AM, Role::PM, Role::Producer], true) && $j->stage !== Stage::Draft) {
            return Decision::deny('After the brief is sent, Traffic assigns the creative team.');
        }
        return $d;
    }

    /**
     * Developer, SEO and Producer after the send (owner decision: their
     * template tasks are assigned to the job's holder of the role): Traffic
     * and the job's owners, like the Social slot. Before the send canAssign
     * already allows the owners.
     */
    public static function canAssignTaskRole(User $u, JobAccess $j, Role $slot): Decision
    {
        if (!in_array($slot, self::TASK_ROLES, true)) {
            return Decision::deny('That slot is not chosen this way.');
        }
        if (!$j->briefSent || $j->stage === Stage::Draft) {
            return Decision::deny('This slot is chosen after the brief is sent.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '.');
        }
        return self::cell('assign_task_roles', $u, $j, 'Only the job owner, Traffic, the COO or the ECD can fill the ' . $slot->value . ' slot.');
    }

    /** Roles whose slot holder receives template tasks (AssetTemplate::defaultRole). */
    public const TASK_ROLES = [Role::Developer, Role::SEO, Role::Producer];

    /**
     * The one check for a team slot picker (brief team section, job sheet):
     * Social follows SocialPolicy, Developer, SEO and Producer also open after
     * the send (canAssignTaskRole), everything else canAssign.
     */
    public static function canSetSlot(User $u, JobAccess $j, Role $slot): Decision
    {
        if ($slot === Role::Social) {
            return SocialPolicy::canSetSocialSlot($u, $j);
        }
        $d = self::canAssign($u, $j, $slot);
        if ($d->allowed || !in_array($slot, self::TASK_ROLES, true)) {
            return $d;
        }
        $after = self::canAssignTaskRole($u, $j, $slot);
        return $after->allowed ? $after : $d;
    }

    /** "Make me AM": a manager takes the empty AM slot (existing jobs have no AM; no backfill). */
    public static function canClaimAm(User $u, JobAccess $j): Decision
    {
        if (!in_array($u->role, [Role::AM, Role::PM, Role::Producer, Role::COO, Role::ECD], true)) {
            return Decision::deny('Only account managers, PMs, producers, the COO and the ECD can take the AM slot.');
        }
        if ($j->hasAm()) {
            return Decision::deny('This job already has an AM.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '.');
        }
        return Decision::allow();
    }

    // ---- Stage moves -----------------------------------------------------------

    /** Who may make a named move from the job's current stage. The move's own rules are in Transitions. */
    public static function canTransition(User $u, JobAccess $j, JobAction $a): Decision
    {
        $id = Transitions::matrixId($a, $j->stage);
        if ($id === null) {
            return Decision::deny($a->label() . ' is not possible while the job is ' . strtolower($j->stage->label()) . '.');
        }
        if (!isset(self::ROWS[$id])) {
            return Decision::deny($a->label() . ' is not available yet.');
        }
        if ($a === JobAction::Send) {
            return self::canSendBrief($u, $j);
        }
        if ($a === JobAction::Recall) {
            return self::canRecallBrief($u, $j);
        }
        if (($a === JobAction::Wait && ($u->role === Role::Traffic || $u->role === Role::CD)) || ($a === JobAction::Hold && $u->role === Role::Traffic)) {
            if ($j->stage === Stage::Draft) {
                return Decision::deny('Drafts belong to the brief owner until they are sent.');
            }
        }
        $d = self::cell($id, $u, $j, 'You cannot ' . strtolower($a->label()) . ' on this job.');
        if ($d->allowed && $a === JobAction::Start) {
            // Makers start a job implicitly, by starting their first assigned asset (matrix condition).
            if (in_array($u->role, self::MAKERS, true)) {
                return Decision::deny('The job moves to In progress by itself when you start your first asset.');
            }
            if (!$j->hasCreativeTeam()) {
                return Decision::deny('Assign the CD or a creative before starting work.');
            }
        }
        return $d;
    }

    /** Copywriter, Designer, Developer, SEO, Social: one rule set for asset work (docs/roles.md "Makers"). */
    public const MAKERS = [Role::Copywriter, Role::Designer, Role::Developer, Role::SEO, Role::Social];

    // ---- Organisation and visibility -------------------------------------------

    public static function canManageCampaign(User $u): Decision
    {
        return self::cell('manage_campaign', $u, null, 'Only account managers, PMs, producers, the COO and the ECD manage campaigns.');
    }

    /**
     * May the user see this job at all (grid, board, sheet, moves)? view_all_jobs,
     * and a draft only for who may read it (view_brief_draft), or for a manager
     * who could claim it (no creator). Same rule as JobQueryStore's scope; a
     * handler answers 404 or "no longer exists" when this denies.
     */
    public static function canViewJob(User $u, JobAccess $j): Decision
    {
        $d = self::cell('view_all_jobs', $u, $j, 'You are not on this job.');
        if (!$d->allowed || $j->stage !== Stage::Draft) {
            return $d;
        }
        if (self::canViewBriefDraft($u, $j)->allowed) {
            return $d;
        }
        if ($j->creatorId === null && self::rule('view_brief_draft', $u->role) !== PolicyRule::Deny) {
            return $d;
        }
        return Decision::deny('Drafts are only visible to the brief owner.');
    }

    /** A brand's logo link (Campaigns page): the roles that manage campaigns. */
    public static function canSetBrandLogo(User $u): Decision
    {
        return self::cell('manage_brand_logo', $u, null, 'Only account managers, PMs, producers, the COO and the ECD change brand logos.');
    }

    /**
     * Every asset of a sent job (job sheet list, GET /jobs/{id}/assets):
     * Traffic, the COO and the ECD (owner decision: crunch time and workflow reviews).
     */
    public static function canViewJobAssets(User $u, JobAccess $j): Decision
    {
        if (!$j->briefSent) {
            return Decision::deny('The brief has not been sent, so there are no assets yet.');
        }
        return self::cell('view_all_assets', $u, $j, 'Only Traffic, the COO and the ECD see every asset of a job.');
    }

    /**
     * Override an asset's status, or a Social post's status, with a reason.
     * Bypasses the normal flow on purpose; every use is logged as an override.
     */
    public static function canOverrideAssetStatus(User $u, JobAccess $j): Decision
    {
        if (!$j->briefSent) {
            return Decision::deny('The brief has not been sent, so there are no assets yet.');
        }
        return self::cell('override_asset_status', $u, $j, 'Only Traffic, the COO and the ECD can override an asset status.');
    }

    /** GET /admin/overrides: the COO's workflow review. */
    public static function canViewOverridesReport(User $u): Decision
    {
        return self::cell('view_overrides_report', $u, null, 'Only the COO sees the overrides report.');
    }

    /** "Add demo role tasks" on /admin/system: the COO, and only while demo mode is on. */
    public static function canAddDemoRoleTasks(User $u, bool $demoMode): Decision
    {
        if (!$demoMode) {
            return Decision::deny('Demo tasks can only be added while demo mode is on.');
        }
        return self::cell('add_demo_role_tasks', $u, null, 'Only the COO can add demo tasks.');
    }

    /**
     * Roles that see only the jobs they are assigned to (view_all_jobs = A):
     * their filter lists hold only the brands and campaigns of those jobs, and
     * they get no Owner filter.
     */
    public static function seesOnlyAssignedJobs(User $u): bool
    {
        return self::rule('view_all_jobs', $u->role) === PolicyRule::Assigned;
    }

    public static function canViewBudget(User $u, JobAccess $j): Decision
    {
        return self::cell('view_budget', $u, $j, 'Budget is visible to the job owner, the COO and the ECD.');
    }

    public static function canViewHours(User $u, JobAccess $j): Decision
    {
        return self::cell('view_hours', $u, $j, 'Hours are not shown to your role.');
    }

    // ---- Phase 3: jobs grid/board ------------------------------------------------

    /**
     * Inline edit of one grid cell. Brief-owned fields: not on a closed job (after
     * the first send the edit goes to the working copy). Waiting fields: stage
     * waiting only. AM and Traffic: the assignment rules (canAssign).
     */
    public static function canEditJobField(User $u, JobAccess $j, GridField $f): Decision
    {
        $slot = $f->slot();
        if ($slot !== null) {
            return self::canAssign($u, $j, $slot);
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '; it can no longer change.');
        }
        if ($f->isWaitingField() && $j->stage !== Stage::Waiting) {
            return Decision::deny('Only a waiting job has a waiting reason.');
        }
        return self::cell((string) $f->matrixId(), $u, $j, 'You cannot change the ' . strtolower($f->label()) . ' on this job.');
    }

    /** What the job search may return to this user and which brief values they see. */
    public static function jobViewer(User $u): JobViewer
    {
        return new JobViewer($u->id, self::rule('view_all_jobs', $u->role), self::rule('view_brief_draft', $u->role), $u->brandId,
            self::rule('view_budget', $u->role));
    }

    /** Which "My day" the user gets: owners (create_brief), Traffic, or assigned work. */
    public static function myDayMode(User $u): MyDayMode
    {
        if (self::rule('create_brief', $u->role) !== PolicyRule::Deny) {
            return MyDayMode::Owner;
        }
        return $u->role === Role::Traffic ? MyDayMode::Traffic : MyDayMode::Assigned;
    }

    /**
     * Nav visibility: show only what the role can use (no dead links). Not an
     * authorization check; every route still runs its own Policy function.
     * Items: today, briefs, jobs, board, campaigns, social, admin-users,
     * admin-system, admin-overrides, spike. Unknown items are hidden.
     */
    public static function canSeeNav(User $u, string $item): bool
    {
        if ($u->role === Role::Client) {
            return false;
        }
        return match ($item) {
            'today', 'jobs', 'board' => true,
            // Brief creators and owners (Traffic and creatives open sent briefs from their jobs).
            'briefs' => self::canCreateBrief($u)->allowed,
            'campaigns' => self::canManageCampaign($u)->allowed,
            // social_view_queue: COO, ECD, Social; AM, PM, Producer read only on their jobs.
            'social' => in_array($u->role, [Role::COO, Role::ECD, Role::AM, Role::PM, Role::Producer, Role::Social], true),
            'admin-users' => self::canManageUsers($u)->allowed,
            'admin-system', 'spike' => self::canViewSystem($u)->allowed,
            'admin-overrides' => self::canViewOverridesReport($u)->allowed,
            default => false,
        };
    }

    /** Shared saved views: manager and admin roles only. */
    public static function canShareView(User $u): Decision
    {
        return in_array($u->role, [Role::COO, Role::ECD, Role::AM, Role::PM, Role::Producer, Role::Traffic], true)
            ? Decision::allow()
            : Decision::deny('Only managers and admins can share views.');
    }

    /** Rename, re-share, make default or delete: the owner; an admin may also manage a shared view. */
    public static function canManageView(User $u, SavedView $v): Decision
    {
        if ($v->ownerId === $u->id) {
            return Decision::allow();
        }
        if ($v->isShared && self::isAdmin($u)) {
            return Decision::allow();
        }
        return Decision::deny('Only the person who saved this view can change it.');
    }

    /** The matrix cell for an action and role (deny when the action has no row). */
    public static function rule(string $action, Role $role): PolicyRule
    {
        $row = self::ROWS[$action] ?? null;
        if ($row === null) {
            return PolicyRule::Deny;
        }
        $codes = explode(' ', $row);
        $i = array_search($role->value, self::COLUMNS, true);
        return PolicyRule::fromCode($i === false ? '-' : ($codes[$i] ?? '-'));
    }

    /** Evaluate a matrix cell against the actor's relation to the job. */
    private static function cell(string $action, User $u, ?JobAccess $j, string $denyReason): Decision
    {
        $ok = match (self::rule($action, $u->role)) {
            PolicyRule::Allow => true,
            PolicyRule::Deny => false,
            PolicyRule::Assigned => $j !== null && $j->isAssigned($u->id),
            PolicyRule::Creator => $j !== null && $j->isCreator($u->id),
            PolicyRule::AssignedOrCreator => $j !== null && ($j->isAssigned($u->id) || $j->isCreator($u->id)),
            PolicyRule::OwnBrand => $j !== null && $u->brandId !== null && $j->brandId !== null && $u->brandId === $j->brandId,
        };
        return $ok ? Decision::allow() : Decision::deny($denyReason);
    }
}

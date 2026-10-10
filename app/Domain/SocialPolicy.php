<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\JobAccess;
use App\Domain\Types\User;

/**
 * Who may do what in Social publishing (docs/roles.md 2.13 and the "Social
 * publishing" block of section 4; tests/fixtures/roles/policy-matrix.json
 * social_* rows). Same shape as Policy: ROWS holds the matrix cells in
 * Policy::COLUMNS order, each can*() adds the stage conditions. Pure.
 *
 * Social "A" means the Social slot on the job or an asset of the job assigned
 * to them (never another slot). Everything applies from client approval on:
 * a job before approved_client is not in the queue and nothing can be written.
 * Go: package domain, functions SocialCanViewQueue, ...
 */
final class SocialPolicy
{
    private const ROWS = [
        'social_view_queue' => 'Y Y AC - AC AC - - - - - - Y -',
        'social_edit_checklist' => 'Y Y - - - - - - - - - - A -',
        'social_set_ready_to_schedule' => 'Y Y - - - - - - - - - - A -',
        'social_set_scheduled' => 'Y Y - - - - - - - - - - A -',
        'social_set_live' => 'Y Y - - - - - - - - - - A -',
        'social_edit_live_link' => 'Y Y - - - - - - - - - - A -',
        'social_set_promoted' => 'Y Y - - - - - - - - - - A -',
        'social_archive_post' => 'Y Y - - - - - - - - - - A -',
        // Owner decision 2026-10: the Producer owns asset test reports, so on their
        // jobs they may send a post back to checking and fill the test result item.
        'social_set_checking' => 'Y Y - - - AC - - - - - - A -',
        'social_edit_test_result' => 'Y Y - - - AC - - - - - - A -',
        // Not in the matrix (owner defaults for this phase): reopen one step back, and
        // filling the Social slot after the brief is sent.
        'social_reopen' => 'Y Y - - - - - - - - - - A -',
        'social_assign' => 'Y Y AC Y AC AC - - - - - - - -',
    ];

    /** The /social page itself: Social, COO and ECD see the whole queue; AM, PM and Producer their own jobs. */
    public static function canViewQueue(User $u): Decision
    {
        return in_array($u->role, [Role::COO, Role::ECD, Role::Social, Role::AM, Role::PM, Role::Producer], true)
            ? Decision::allow()
            : Decision::deny('The Social queue is for Social, account managers, PMs, producers, the COO and the ECD.');
    }

    /** Does the queue list every job to this role (true) or only the jobs they own (false)? */
    public static function seesWholeQueue(User $u): bool
    {
        return self::rule('social_view_queue', $u->role) === PolicyRule::Allow;
    }

    /** One job in the queue (GET /social/jobs/{id}). */
    public static function canViewJob(User $u, JobAccess $j): Decision
    {
        if (!self::isPastClientApproval($j->stage)) {
            return Decision::deny('This job is not approved by the client yet, so it is not in the Social queue.');
        }
        return self::cell('social_view_queue', $u, $j, 'You can only see the Social status of your own jobs.');
    }

    public static function canEditChecklist(User $u, JobAccess $j): Decision
    {
        return self::write('social_edit_checklist', $u, $j);
    }

    public static function canSetReadyToSchedule(User $u, JobAccess $j): Decision
    {
        return self::write('social_set_ready_to_schedule', $u, $j);
    }

    public static function canSetScheduled(User $u, JobAccess $j): Decision
    {
        return self::write('social_set_scheduled', $u, $j);
    }

    /** The AM cannot set Live (Q23): Social, COO and ECD only. */
    public static function canSetLive(User $u, JobAccess $j): Decision
    {
        return self::write('social_set_live', $u, $j);
    }

    public static function canEditLiveLink(User $u, JobAccess $j): Decision
    {
        return self::write('social_edit_live_link', $u, $j);
    }

    public static function canSetPromoted(User $u, JobAccess $j): Decision
    {
        return self::write('social_set_promoted', $u, $j);
    }

    public static function canArchive(User $u, JobAccess $j): Decision
    {
        return self::write('social_archive_post', $u, $j);
    }

    /** Move a publication back one step, with a reason. */
    public static function canReopen(User $u, JobAccess $j): Decision
    {
        return self::write('social_reopen', $u, $j);
    }

    /** Send a post back to checking (from Ready to schedule or Scheduled), with a reason. */
    public static function canSetChecking(User $u, JobAccess $j): Decision
    {
        return self::write('social_set_checking', $u, $j);
    }

    /**
     * The checklist's Test result item only (asset test reports). Whoever may
     * edit the whole checklist may edit this item too.
     */
    public static function canEditTestResult(User $u, JobAccess $j): Decision
    {
        $all = self::canEditChecklist($u, $j);
        return $all->allowed ? $all : self::write('social_edit_test_result', $u, $j);
    }

    /** The decision for a publication move. */
    public static function canAct(User $u, JobAccess $j, PublicationAction $a): Decision
    {
        return match ($a) {
            PublicationAction::Ready => self::canSetReadyToSchedule($u, $j),
            PublicationAction::Schedule => self::canSetScheduled($u, $j),
            PublicationAction::GoLive => self::canSetLive($u, $j),
            PublicationAction::Archive => self::canArchive($u, $j),
            PublicationAction::Reopen => self::canReopen($u, $j),
            PublicationAction::Recheck => self::canSetChecking($u, $j),
        };
    }

    /**
     * Fill or clear the Social slot once the brief has been sent: the job's AM,
     * PM or Producer (assigned or creator), Traffic, the COO and the ECD.
     */
    public static function canAssignSocial(User $u, JobAccess $j): Decision
    {
        if (!$j->briefSent || $j->stage === Stage::Draft) {
            return Decision::deny('Social is chosen after the brief is sent.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '.');
        }
        return self::cell('social_assign', $u, $j, 'Only the job owner, Traffic, the COO or the ECD can choose Social.');
    }

    /**
     * The Social slot picker (brief team section, job sheet): before the send
     * the usual creative-team rule (Policy::canAssign, a suggestion Traffic can
     * change), after it canAssignSocial. Either one allows.
     */
    public static function canSetSocialSlot(User $u, JobAccess $j): Decision
    {
        $after = self::canAssignSocial($u, $j);
        if ($after->allowed) {
            return $after;
        }
        $before = Policy::canAssign($u, $j, Role::Social);
        return $before->allowed ? $before : $after;
    }

    /** approved_client, the three Social stages, or done. */
    public static function isPastClientApproval(Stage $s): bool
    {
        return PublicationRules::isSocialWindow($s) || $s === Stage::Done;
    }

    /** The matrix cell for an action and role (deny when the action has no row). */
    public static function rule(string $action, Role $role): PolicyRule
    {
        $row = self::ROWS[$action] ?? null;
        if ($row === null) {
            return PolicyRule::Deny;
        }
        $codes = explode(' ', $row);
        $i = array_search($role->value, Policy::COLUMNS, true);
        return PolicyRule::fromCode($i === false ? '-' : ($codes[$i] ?? '-'));
    }

    /** Writes: the job is in the Social window (client approved, not done or paused), then the cell. */
    private static function write(string $action, User $u, JobAccess $j): Decision
    {
        if (!PublicationRules::isSocialWindow($j->stage)) {
            return Decision::deny(
                $j->stage === Stage::Done || $j->stage->isClosed()
                    ? 'The job is ' . strtolower($j->stage->label()) . '; its posts can no longer change.'
                    : 'Social work starts once the client has approved the job.'
            );
        }
        return self::cell($action, $u, $j, match (true) {
            $u->role === Role::Social => 'You need the Social slot on this job (or an asset assigned to you) to change its posts.',
            $u->role === Role::Producer && self::rule($action, $u->role) !== PolicyRule::Deny => 'Only the Producer on this job can do this.',
            default => 'Only Social, the COO or the ECD can change the posts.',
        });
    }

    private static function cell(string $action, User $u, JobAccess $j, string $denyReason): Decision
    {
        $ok = match (self::rule($action, $u->role)) {
            PolicyRule::Allow => true,
            PolicyRule::Deny => false,
            PolicyRule::Assigned => $u->role === Role::Social
                ? ($j->slotHolder(Role::Social) === $u->id || in_array($u->id, $j->assetAssigneeIds, true))
                : $j->isAssigned($u->id),
            PolicyRule::Creator => $j->isCreator($u->id),
            PolicyRule::AssignedOrCreator => $j->isAssigned($u->id) || $j->isCreator($u->id),
            PolicyRule::OwnBrand => $u->brandId !== null && $j->brandId !== null && $u->brandId === $j->brandId,
        };
        return $ok ? Decision::allow() : Decision::deny($denyReason);
    }
}

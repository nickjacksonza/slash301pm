<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\AssetReview;
use App\Domain\Types\JobAccess;
use App\Domain\Types\JobReview;
use App\Domain\Types\Team;
use App\Domain\Types\User;

/**
 * Who may do what in internal review (owner spec 2026-10; the Reviews block of
 * docs/roles.md section 4). The matrix cells live in Policy::ROWS; each
 * function here adds the review conditions. Pure.
 *
 * "A" means, per role:
 * - CD: holds the CD slot on the job, or the review was reassigned to them.
 * - Makers (submit_for_review): a maker of a part of that asset
 *   (ReviewRules::isMakerOf: the part's slot, the asset's assignee, or who
 *   handed it in).
 * - AM, Producer (AC): the usual assigned or brief creator.
 * Every action needs a sent brief (or a legacy job past draft) and an open job.
 * Go: package domain, func ReviewCanView, ...
 */
final class ReviewPolicy
{
    /** GET /jobs/{id}/review: agency people who read internal feedback on this job (view_internal_feedback). */
    public static function canView(User $u, JobAccess $j): Decision
    {
        if (!Policy::canViewJob($u, $j)->allowed) {
            return Decision::deny('You are not on this job.');
        }
        if (self::notSent($j)) {
            return Decision::deny('The brief has not been sent, so there is nothing to review yet.');
        }
        return self::cell('view_internal_feedback', $u, $j, null, 'Only the team on this job sees its review.');
    }

    /**
     * The parts of this asset the user may hand in (submit_for_review): COO and
     * ECD any part; the CD slot holder any part; a maker the parts they make.
     * @return list<ReviewPart>
     */
    public static function submitParts(User $u, JobAccess $j, AssetReview $a, Team $team): array
    {
        if (self::closedOrDraft($j) !== null || Policy::rule('submit_for_review', $u->role) === PolicyRule::Deny) {
            return [];
        }
        $all = Policy::rule('submit_for_review', $u->role) === PolicyRule::Allow || ($u->role === Role::CD && $j->slotHolder(Role::CD) === $u->id);
        $out = [];
        foreach ($a->requiredParts() as $p) {
            if ($all || ($u->role !== Role::CD && ReviewRules::isMakerOf($u->id, $a, $p, $team))) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** Save a hand-in or Request review on this asset: some part is theirs to make. */
    public static function canSubmit(User $u, JobAccess $j, AssetReview $a, Team $team): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        if (Policy::rule('submit_for_review', $u->role) === PolicyRule::Deny) {
            return Decision::deny('Only the makers of this post, its CD, the ECD or the COO hand it in.');
        }
        return self::submitParts($u, $j, $a, $team) !== [] ? Decision::allow() : Decision::deny('This post is not yours to make.');
    }

    /**
     * Approve or reject a part (approve_internal): COO, ECD, or the CD on the
     * job (slot or reassigned). A CD may not judge a part they handed in
     * themselves while another CD or the ECD can (matrix condition).
     */
    public static function canReview(User $u, JobAccess $j, JobReview $jr, ?AssetReview $a = null, ?ReviewPart $p = null, bool $otherReviewerExists = true): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        $d = self::cell('approve_internal', $u, $j, $jr, 'Only the CD on this job, the ECD or the COO review its posts.');
        if (!$d->allowed) {
            return $d;
        }
        if ($u->role === Role::CD && $a !== null && $otherReviewerExists) {
            foreach ($p !== null ? [$p] : $a->requiredParts() as $part) {
                if ($a->makerId($part) === $u->id) {
                    return Decision::deny('You handed in the ' . strtolower($part->label()) . ' yourself, so another CD or the ECD reviews it.');
                }
            }
        }
        return $d;
    }

    /** "Approve job" by the CD: every post approved, not approved already. */
    public static function canApproveJob(User $u, JobAccess $j, JobReview $jr, JobReviewStatus $status): Decision
    {
        $d = self::canReview($u, $j, $jr);
        if (!$d->allowed) {
            return $d;
        }
        if ($jr->cdApproved()) {
            return Decision::deny('The job is already approved by ' . ($jr->cdApprovedByName !== '' ? $jr->cdApprovedByName : 'the CD') . '.');
        }
        if ($status !== JobReviewStatus::AllApproved) {
            return Decision::deny('Approve every post (copy and media) first.');
        }
        return Decision::allow();
    }

    /** "Request ECD review" by the CD, even before every post is done. */
    public static function canRequestEcdReview(User $u, JobAccess $j, JobReview $jr): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        $d = self::cell('request_ecd_review', $u, $j, $jr, 'Only the CD on this job asks the ECD to review it.');
        if (!$d->allowed) {
            return $d;
        }
        if ($jr->ecdApproved()) {
            return Decision::deny('The ECD has already approved this job.');
        }
        return Decision::allow();
    }

    /** The ECD's approval of a CD-approved job (the COO may cover). */
    public static function canApproveAsEcd(User $u, JobAccess $j, JobReview $jr, JobReviewStatus $status): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        $d = self::cell('approve_job_ecd', $u, $j, $jr, 'Only the ECD (or the COO) gives the ECD approval.');
        if (!$d->allowed) {
            return $d;
        }
        if ($jr->ecdApproved()) {
            return Decision::deny('The ECD has already approved this job.');
        }
        if (!$jr->cdApproved()) {
            return Decision::deny('The CD approves the job first.');
        }
        if ($status !== JobReviewStatus::AllApproved) {
            return Decision::deny('Every post must be approved first.');
        }
        return Decision::allow();
    }

    /**
     * Hand the review to someone else: another CD or the ECD at any time, or a
     * Client contact of the job's brand once the ECD has approved the job.
     */
    public static function canReassign(User $u, JobAccess $j, JobReview $jr): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        return self::cell('reassign_review', $u, $j, $jr, 'Only the CD on this job, the ECD or the COO reassign its review.');
    }

    /** Is $target someone the review may be handed to now? '' when yes, else the reason. */
    public static function reassignTargetProblem(User $target, JobAccess $j, JobReview $jr, string $actorId): string
    {
        if (!$target->isActive) {
            return 'That person is not an active user.';
        }
        if ($target->id === $actorId) {
            return 'Choose someone else.';
        }
        if ($jr->assigneeId === $target->id) {
            return 'The review is already with ' . $target->name . '.';
        }
        if ($target->role === Role::CD || $target->role === Role::ECD) {
            return '';
        }
        if ($target->role === Role::Client) {
            if ($j->brandId === null || $target->brandId !== $j->brandId) {
                return 'Only a client contact of this job\'s brand can review it.';
            }
            return $jr->ecdApproved() ? '' : 'The client can review only after the ECD has approved the job.';
        }
        return 'The review can go to another CD, the ECD, or (after ECD approval) a client contact of the brand.';
    }

    /** Ready for client review: CD and ECD approved, stage approved_internal. */
    public static function canMarkReady(User $u, JobAccess $j, JobReview $jr, JobReviewStatus $status): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        $d = self::cell('mark_ready_for_client', $u, $j, $jr, 'Only the CD, ECD, AM, COO, Traffic or Producer on this job get it ready for the client.');
        if (!$d->allowed) {
            return $d;
        }
        if (!$jr->internallyApproved() || $status !== JobReviewStatus::AllApproved) {
            return Decision::deny('The CD and the ECD must both approve the job first.');
        }
        if ($j->stage !== Stage::ApprovedInternal) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '; it must be approved (internal) to go to the client.');
        }
        if ($jr->readyForClient()) {
            return Decision::deny('The job is already ready for client review.');
        }
        return Decision::allow();
    }

    /** Sent to the client (by hand, outside the app, in this phase): after ready for client. */
    public static function canSendToClient(User $u, JobAccess $j, JobReview $jr, JobReviewStatus $status): Decision
    {
        $closed = self::closedOrDraft($j);
        if ($closed !== null) {
            return $closed;
        }
        $d = self::cell('send_to_client', $u, $j, $jr, 'Only the CD, ECD, AM, COO, Traffic or Producer on this job send it to the client.');
        if (!$d->allowed) {
            return $d;
        }
        if (!$jr->internallyApproved() || $status !== JobReviewStatus::AllApproved) {
            return Decision::deny('The CD and the ECD must both approve the job first.');
        }
        if ($j->stage !== Stage::ApprovedInternal) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '; it must be approved (internal) to go to the client.');
        }
        if (!$jr->readyForClient()) {
            return Decision::deny('Mark the job ready for client review first.');
        }
        if ($jr->sentToClient()) {
            return Decision::deny('The job was already sent to the client.');
        }
        return Decision::allow();
    }

    /** Does this user review posts on this job (for "Waiting for me")? */
    public static function isReviewer(User $u, JobAccess $j, JobReview $jr): bool
    {
        return self::canReview($u, $j, $jr)->allowed;
    }

    private static function notSent(JobAccess $j): bool
    {
        return !$j->briefSent && $j->stage === Stage::Draft;
    }

    private static function closedOrDraft(JobAccess $j): ?Decision
    {
        if (self::notSent($j) || $j->stage === Stage::Draft) {
            return Decision::deny('The brief has not been sent, so there is nothing to review yet.');
        }
        if ($j->stage->isClosed()) {
            return Decision::deny('The job is ' . strtolower($j->stage->label()) . '.');
        }
        return null;
    }

    /** A matrix cell; for the CD, "assigned" means the CD slot or the review assignee. */
    private static function cell(string $action, User $u, JobAccess $j, ?JobReview $jr, string $denyReason): Decision
    {
        $rule = Policy::rule($action, $u->role);
        $ok = match ($rule) {
            PolicyRule::Allow => true,
            PolicyRule::Deny => false,
            PolicyRule::Assigned => $u->role === Role::CD
                ? ($j->slotHolder(Role::CD) === $u->id || ($jr !== null && $jr->assigneeId === $u->id))
                : $j->isAssigned($u->id),
            PolicyRule::Creator => $j->isCreator($u->id),
            PolicyRule::AssignedOrCreator => $j->isAssigned($u->id) || $j->isCreator($u->id),
            PolicyRule::OwnBrand => $u->brandId !== null && $j->brandId !== null && $u->brandId === $j->brandId,
        };
        return $ok ? Decision::allow() : Decision::deny($denyReason);
    }
}

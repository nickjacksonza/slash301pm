<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\AssetReview;

/**
 * Client portal rules that are not about who may act (ReviewPolicy::canClient*
 * decides that). Pure. Go: package domain.
 *
 * - A client decision is per post (every part at once): Approve, Reject with
 *   feedback, or Reject. Both rejections need feedback from a client, so the
 *   maker always learns why.
 * - The job sign-off needs every live post approved by a client contact of the
 *   brand in its current round.
 * - Recently decided jobs stay on /portal for RECENT_DAYS.
 */
final class PortalRules
{
    public const RECENT_DAYS = 30;

    /** '' or the reason, for an untrusted decision value ('approved', 'rejected_with_feedback', 'rejected'). */
    public static function decisionProblems(?ReviewDecision $d, string $feedback): ValidationErrors
    {
        $e = new ValidationErrors();
        if ($d === null) {
            return $e->with('decision', 'Choose Approve, Reject with feedback or Reject.');
        }
        if ($d->isRejection() && trim($feedback) === '') {
            return $e->with('feedback', 'Tell the team what to change.');
        }
        if (mb_strlen($feedback) > ReviewRules::MAX_FEEDBACK) {
            return $e->with('feedback', 'Keep the feedback under ' . ReviewRules::MAX_FEEDBACK . ' characters.');
        }
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $feedback) === 1) {
            return $e->with('feedback', 'The feedback contains characters that cannot be stored.');
        }
        return $e;
    }

    /**
     * Every live post (not cancelled) is approved by a client in its current
     * round. A job with no live post cannot be signed off.
     * @param list<AssetReview> $posts
     * @param list<string> $clientApprovedIds asset ids approved by a client contact in their current round
     */
    public static function allClientApproved(array $posts, array $clientApprovedIds): bool
    {
        $any = false;
        foreach ($posts as $p) {
            if (AssetStatus::isCancelled($p->assetStatus)) {
                continue;
            }
            $any = true;
            if (!in_array($p->assetId, $clientApprovedIds, true)) {
                return false;
            }
        }
        return $any;
    }

    /** A post may be approved or rejected by a client: something handed in, and not approved by a client already this round. */
    public static function postProblem(AssetReview $p, bool $clientApproved, ReviewDecision $d): string
    {
        if (AssetStatus::isCancelled($p->assetStatus)) {
            return 'This post was cancelled.';
        }
        foreach ($p->requiredParts() as $part) {
            if (!$p->state($part)->hasContent()) {
                return 'This post is not ready for review.';
            }
        }
        if ($clientApproved && $d === ReviewDecision::Approved) {
            return 'Already approved.';
        }
        return '';
    }
}

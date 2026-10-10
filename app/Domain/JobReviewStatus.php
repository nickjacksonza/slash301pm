<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * The review state of a whole job, from its non-cancelled assets
 * (ReviewRules::aggregate). Go: type JobReviewStatus string.
 */
enum JobReviewStatus: string
{
    /** No assets to review. */
    case Empty = 'empty';
    /** Some part still to hand in, nothing rejected. */
    case InProgress = 'in_progress';
    /** Everything handed in, something waiting for a decision. */
    case WaitingReview = 'waiting_review';
    /** A part was rejected and is not handed in again yet. */
    case NeedsChanges = 'needs_changes';
    /** Every part of every asset approved: the job counts as CD-approvable. */
    case AllApproved = 'all_approved';

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'No posts yet',
            self::InProgress => 'Work in progress',
            self::WaitingReview => 'Waiting for review',
            self::NeedsChanges => 'Needs changes',
            self::AllApproved => 'All approved',
        };
    }
}

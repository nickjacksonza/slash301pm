<?php
declare(strict_types=1);

namespace App\Domain;

/** A reviewer's decision on a part (asset_reviews.decision). Go: type ReviewDecision string. */
enum ReviewDecision: string
{
    case Approved = 'approved';
    case RejectedWithFeedback = 'rejected_with_feedback';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::RejectedWithFeedback => 'Rejected with feedback',
            self::Rejected => 'Rejected',
        };
    }

    public function state(): PartState
    {
        return match ($this) {
            self::Approved => PartState::Approved,
            self::RejectedWithFeedback => PartState::ChangesRequested,
            self::Rejected => PartState::Rejected,
        };
    }

    public function isRejection(): bool
    {
        return $this !== self::Approved;
    }
}

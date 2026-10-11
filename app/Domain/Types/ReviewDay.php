<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * The review lists of one person's My day (Domain\ReviewDayRules::build).
 * A null list does not apply to the person's role and is not shown.
 * Go: type ReviewDay struct.
 */
final class ReviewDay
{
    /**
     * @param list<ReviewDayItem>|null $toReview posts waiting for this reviewer (CD, or whoever the review was handed to)
     * @param list<ReviewDayItem>|null $ecdWaiting jobs waiting for the ECD's approval (ECD, COO)
     * @param list<ReviewDayItem>|null $changes posts with a part sent back to this maker
     * @param list<ReviewDayItem>|null $roundWarnings posts of the owner's jobs past the round limit
     * @param list<ReviewDayItem>|null $readyForClient the owner's (or Traffic's) jobs approved by the CD and ECD, not sent yet
     */
    public function __construct(
        public readonly ?array $toReview,
        public readonly ?array $ecdWaiting,
        public readonly ?array $changes,
        public readonly ?array $roundWarnings,
        public readonly ?array $readyForClient,
    ) {}

    /** Does any list apply to this person? */
    public function applies(): bool
    {
        return $this->toReview !== null || $this->ecdWaiting !== null || $this->changes !== null || $this->roundWarnings !== null || $this->readyForClient !== null;
    }

    /** Items that need the person (the nav badge). */
    public function count(): int
    {
        $n = 0;
        foreach ([$this->toReview, $this->ecdWaiting, $this->changes, $this->roundWarnings, $this->readyForClient] as $list) {
            $n += $list === null ? 0 : count($list);
        }
        return $n;
    }
}

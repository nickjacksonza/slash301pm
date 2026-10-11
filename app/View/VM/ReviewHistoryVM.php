<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\ReviewDecision;
use App\Domain\ReviewPart;

/** One earlier decision on a part, shown under a post ("Previous feedback"). */
final class ReviewHistoryVM
{
    public function __construct(
        public readonly ReviewPart $part,
        public readonly ReviewDecision $decision,
        public readonly string $feedback,
        public readonly string $reviewerName,
        public readonly string $when,
        public readonly int $round,
        public readonly string $briefVersion,
    ) {}
}

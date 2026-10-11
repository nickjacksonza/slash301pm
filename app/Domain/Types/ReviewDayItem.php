<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One line of a My day review list: a job (or a post of it) and what it needs. Go: type ReviewDayItem struct. */
final class ReviewDayItem
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $detail,
        /** 'review' opens the review page, 'work' the hand-in page. */
        public readonly string $target,
    ) {}
}

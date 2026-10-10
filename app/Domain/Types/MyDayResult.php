<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** Everything the "My day" page shows. Go: type MyDayResult struct. */
final class MyDayResult
{
    /** @param list<MyDayChange> $changed newest first, at most MyDay::SHOW */
    public function __construct(
        public readonly MyDaySection $overdue,
        public readonly MyDaySection $dueSoon,
        public readonly MyDaySection $waiting,
        /** Open jobs with no AM that the user could take (AM, PM, Producer only). */
        public readonly MyDaySection $claimable,
        public readonly array $changed,
        public readonly int $changedTotal,
        public readonly MyDayStrip $strip,
        /** Distinct jobs that are overdue or waiting on me (the nav badge). */
        public readonly int $attention,
    ) {}
}

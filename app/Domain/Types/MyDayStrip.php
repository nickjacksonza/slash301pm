<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** The "This week" counts (Monday to Sunday, SAST). */
final class MyDayStrip
{
    public function __construct(
        public readonly int $dueThisWeek,
        public readonly int $overdue,
        public readonly int $waitingOnMe,
        public readonly int $sentThisWeek,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\VM;

/** One line of the Social section on /today. */
final class SocialDayItemVM
{
    public function __construct(
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $detail,
        public readonly string $when,
        public readonly string $href,
    ) {}
}

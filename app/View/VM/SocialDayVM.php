<?php
declare(strict_types=1);

namespace App\View\VM;

/** The Social section of /today (role Social, or anyone holding a Social slot). */
final class SocialDayVM
{
    /**
     * @param list<SocialDayItemVM> $toCheck jobs with social assets still to check
     * @param list<SocialDayItemVM> $scheduledToday
     * @param list<SocialDayItemVM> $needLink scheduled posts past their time, or live without a link
     */
    public function __construct(
        public readonly array $toCheck,
        public readonly array $scheduledToday,
        public readonly array $needLink,
    ) {}
}

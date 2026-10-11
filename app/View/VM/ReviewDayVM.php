<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\ReviewDay;

/** The Reviews section of My day (#today-section-reviews). */
final class ReviewDayVM
{
    public function __construct(
        public readonly ReviewDay $day,
    ) {}
}

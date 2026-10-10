<?php
declare(strict_types=1);

namespace App\View\VM;

final class ActivityItemVM
{
    public function __construct(
        public readonly string $actor,
        public readonly string $text,
        public readonly string $at,
        /** An asset or post status override: shown with an "Override" badge. */
        public readonly bool $override = false,
    ) {}
}

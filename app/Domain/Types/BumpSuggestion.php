<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BumpLevel;

final class BumpSuggestion
{
    public function __construct(
        public readonly BumpLevel $level,
        public readonly string $reason,
    ) {}
}

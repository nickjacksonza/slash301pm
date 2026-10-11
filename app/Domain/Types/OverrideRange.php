<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** The Overrides report's range: SAST days (inclusive) and UTC bounds [fromUtc, toUtc). */
final class OverrideRange
{
    public function __construct(
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly string $fromUtc,
        public readonly string $toUtc,
    ) {}
}

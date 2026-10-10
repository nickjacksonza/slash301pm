<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\OverrideRecord;

/** GET /admin/overrides: the COO's report of status overrides. */
final class OverridesVM
{
    /** @param list<OverrideRecord> $rows newest first */
    public function __construct(
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly array $rows,
        public readonly bool $capped,
    ) {}
}

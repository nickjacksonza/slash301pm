<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\BriefDiff;

final class BriefVersionsVM
{
    /** @param list<VersionRowVM> $rows newest first */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly array $rows,
        public readonly ?BriefDocVM $doc,
        public readonly ?BriefDiff $diff,
        public readonly string $diffAgainst,
        public readonly bool $showBudget,
    ) {}
}

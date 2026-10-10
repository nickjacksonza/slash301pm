<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\BriefSnapshot;

/** A read-only brief: a sent version, or the working copy for its owner (print). */
final class BriefDocVM
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $versionLabel,
        public readonly string $sentLine,
        public readonly string $note,
        public readonly BriefSnapshot $snapshot,
        public readonly bool $showBudget,
        public readonly bool $showHours,
        public readonly bool $isWorkingCopy,
    ) {}
}

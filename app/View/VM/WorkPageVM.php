<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;

/** GET /jobs/{id}/work: the posts this person makes, with their hand-in forms. */
final class WorkPageVM
{
    /** @param list<WorkItemVM> $items */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly Stage $stage,
        public readonly string $versionLabel,
        public readonly string $briefUrl,
        public readonly string $reviewUrl,
        public readonly array $items,
        public readonly int $otherCount,
    ) {}
}

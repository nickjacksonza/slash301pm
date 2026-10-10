<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;

/**
 * One grid row. hoursText / budgetText are null when the viewer may not see
 * them. editable: column value => the GridField value its editor saves, only
 * for cells this viewer may edit.
 * @phpstan-type Editable array<string,string>
 */
final class GridRowVM
{
    /** @param array<string,string> $editable */
    public function __construct(
        public readonly string $id,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $briefUrl,
        public readonly string $sheetUrl,
        public readonly string $brandName,
        public readonly string $campaignName,
        public readonly Stage $stage,
        public readonly string $waitingText,
        public readonly string $amName,
        public readonly string $trafficName,
        public readonly string $dueText,
        public readonly string $dueBadge,
        public readonly ?string $hoursText,
        public readonly ?string $budgetText,
        public readonly string $versionLabel,
        public readonly bool $unsent,
        public readonly string $updatedText,
        public readonly array $editable,
        public readonly string $rowUrl,
    ) {}
}

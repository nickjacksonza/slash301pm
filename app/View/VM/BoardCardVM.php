<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;

/** A Kanban card. */
final class BoardCardVM
{
    /** @param list<MoveOptionVM> $moves */
    public function __construct(
        public readonly string $id,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly string $dueText,
        public readonly string $dueBadge,
        public readonly string $amName,
        public readonly string $versionLabel,
        public readonly bool $unsent,
        public readonly Stage $stage,
        public readonly int $rowVersion,
        public readonly string $waitingText,
        public readonly array $moves,
        public readonly string $briefUrl,
        public readonly string $sheetUrl,
        public readonly string $moveUrl,
    ) {}
}

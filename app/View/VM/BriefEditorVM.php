<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\Brief;
use App\Domain\Types\BriefLine;
use App\View\ui\SelectOption;

/**
 * The brief page. canEdit false renders the latest sent version read-only (doc).
 */
final class BriefEditorVM
{
    /**
     * @param list<SelectOption> $campaignOptions campaigns of the job's brand
     * @param list<SelectOption> $templateOptions '' (custom) plus the 18 templates
     * @param list<BriefLine> $lines
     * @param list<TeamSlotVM> $team
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $campaignLabel,
        public readonly bool $canEdit,
        public readonly bool $canViewBudget,
        public readonly bool $canViewHours,
        public readonly Brief $brief,
        public readonly array $campaignOptions,
        public readonly array $templateOptions,
        public readonly array $lines,
        public readonly array $team,
        public readonly BriefRailVM $rail,
        public readonly ?BriefDocVM $doc,
    ) {}
}

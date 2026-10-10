<?php
declare(strict_types=1);

namespace App\View\VM;

use App\View\ui\SelectOption;

/** The filter bar of the grid and the board. fetchUrl is the rows (grid) or columns (board) endpoint. */
final class JobFiltersVM
{
    /**
     * @param list<SelectOption> $stageOptions
     * @param list<SelectOption> $brandOptions
     * @param list<SelectOption> $campaignOptions group = brand name
     * @param list<SelectOption> $ownerOptions
     * @param list<SelectOption> $dueOptions
     * @param list<SelectOption> $assigneeOptions
     * @param list<SelectOption> $roleOptions
     * @param list<SelectOption> $groupOptions
     * @param list<SelectOption> $columnOptions disabled = always shown
     * @param array<string,list<string>> $stageSets name => aligned stage signal array (JobQuery::alignedStages)
     */
    public function __construct(
        public readonly string $screen,
        public readonly string $fetchUrl,
        public readonly string $resetUrl,
        public readonly array $stageOptions,
        public readonly array $stageSets,
        public readonly array $brandOptions,
        public readonly array $campaignOptions,
        public readonly array $ownerOptions,
        public readonly array $dueOptions,
        public readonly array $assigneeOptions,
        public readonly array $roleOptions,
        public readonly array $groupOptions,
        public readonly array $columnOptions,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\VM;

use App\View\ui\SelectOption;

/**
 * An inline editor replacing one grid cell. kind: text | date | number | select | waiting.
 * cellId is the display cell's id, so the saved row morphs it back.
 */
final class GridCellEditorVM
{
    /**
     * @param list<SelectOption> $options select and waiting kinds
     */
    public function __construct(
        public readonly string $cellId,
        public readonly string $field,
        public readonly string $label,
        public readonly string $kind,
        public readonly string $value,
        public readonly array $options,
        public readonly string $waitingOn,
        public readonly int $rowVersion,
        public readonly int $briefRowVersion,
        public readonly string $saveUrl,
        public readonly string $cancelUrl,
    ) {}
}

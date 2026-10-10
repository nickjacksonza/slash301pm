<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\JobColumn;

/** One grid header cell. dir: '' | 'asc' | 'desc'; pos 1 or 2 when sorted. nextSort/nextSortAdd: the sort token after a click / shift-click. */
final class GridHeaderVM
{
    public function __construct(
        public readonly JobColumn $column,
        public readonly string $label,
        public readonly bool $sortable,
        public readonly string $dir,
        public readonly int $pos,
        public readonly string $nextSort,
        public readonly string $nextSortAdd,
    ) {}
}

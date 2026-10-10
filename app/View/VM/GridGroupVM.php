<?php
declare(strict_types=1);

namespace App\View\VM;

/** A group of grid rows. key is a short safe token (signal name part); label is display text. */
final class GridGroupVM
{
    /** @param list<GridRowVM> $rows */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $rows,
    ) {}
}

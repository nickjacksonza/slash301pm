<?php
declare(strict_types=1);

namespace App\View\VM;

/** #board-columns: every visible column, plus the count line and the URL for history.replaceState. */
final class BoardColumnsVM
{
    /** @param list<BoardColumnVM> $columns */
    public function __construct(
        public readonly array $columns,
        public readonly int $total,
        public readonly int $shown,
        public readonly int $cap,
        public readonly string $url,
    ) {}
}

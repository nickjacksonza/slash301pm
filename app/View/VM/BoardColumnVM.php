<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;

/** A Kanban column (one stage). */
final class BoardColumnVM
{
    /** @param list<BoardCardVM> $cards */
    public function __construct(
        public readonly Stage $stage,
        public readonly bool $needsReason,
        public readonly array $cards,
    ) {}
}

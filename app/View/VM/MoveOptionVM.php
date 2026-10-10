<?php
declare(strict_types=1);

namespace App\View\VM;

/** One "Move to..." choice. viaBrief: a link to the brief (Send). */
final class MoveOptionVM
{
    public function __construct(
        public readonly string $to,
        public readonly string $label,
        public readonly bool $needsReason,
        public readonly bool $needsWaitingOn,
        public readonly bool $viaBrief,
        public readonly string $href,
    ) {}
}

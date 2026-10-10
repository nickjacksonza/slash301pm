<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One line of the beta gate checklist (docs/beta-gate.md). */
final class BetaGateItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $met,
        public readonly string $detail,
        public readonly bool $ownerAction,
    ) {}
}

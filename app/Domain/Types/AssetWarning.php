<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** N09 started_asset_conflict: a line asks for fewer assets than have started. */
final class AssetWarning
{
    public function __construct(
        public readonly string $lineId,
        public readonly string $label,
        public readonly int $startedKept,
        public readonly string $message,
    ) {}
}

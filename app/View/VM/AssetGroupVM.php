<?php
declare(strict_types=1);

namespace App\View\VM;

/** A deliverable line and its assets ("Other assets" for assets without a line). */
final class AssetGroupVM
{
    /** @param list<AssetRowVM> $assets */
    public function __construct(
        public readonly string $label,
        public readonly array $assets,
    ) {}
}

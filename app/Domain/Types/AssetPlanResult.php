<?php
declare(strict_types=1);

namespace App\Domain\Types;

final class AssetPlanResult
{
    /**
     * @param list<PlannedAsset> $create
     * @param list<string> $cancel asset ids (unstarted only)
     * @param list<AssetWarning> $warnings
     */
    public function __construct(
        public readonly array $create,
        public readonly array $cancel,
        public readonly array $warnings,
    ) {}

    public function isEmpty(): bool
    {
        return $this->create === [] && $this->cancel === [];
    }
}

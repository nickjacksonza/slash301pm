<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\Campaign;

final class CampaignGroupVM
{
    /** @param list<Campaign> $campaigns */
    public function __construct(
        public readonly string $brandId,
        public readonly string $brandName,
        public readonly string $prefix,
        public readonly array $campaigns,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

use DateTimeImmutable;

/** What asset names and new asset rows need from the job. */
final class AssetNaming
{
    public function __construct(
        public readonly string $jobNumber,
        public readonly string $brandName,
        public readonly string $campaignName,
        public readonly ?string $briefDueDate,
        public readonly DateTimeImmutable $now,
    ) {}
}

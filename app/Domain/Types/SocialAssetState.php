<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\PublicationStatus;

/** One social asset of a job and the statuses of its publications (input to PublicationRules::jobTarget). */
final class SocialAssetState
{
    /** @param list<PublicationStatus> $statuses one per platform row, archived included */
    public function __construct(
        public readonly string $assetId,
        public readonly array $statuses,
    ) {}
}

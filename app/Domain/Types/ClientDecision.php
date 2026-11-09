<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\ReviewDecision;

/**
 * One client decision on a post (asset_reviews rows written by a Client
 * contact of the job's brand, one per decision, not per part). Internal
 * decisions are never turned into this type. Go: type ClientDecision struct.
 */
final class ClientDecision
{
    public function __construct(
        public readonly string $assetId,
        public readonly int $round,
        public readonly ReviewDecision $decision,
        public readonly string $feedback,
        public readonly string $clientName,
        public readonly string $at,
    ) {}
}

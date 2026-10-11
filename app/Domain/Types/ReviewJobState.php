<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Stage;

/**
 * One open job in review, as My day and the nav badge read it: the job, its
 * team, the brief creator, the job review row and every post (ReviewStore::
 * dayStates). Go: type ReviewJobState struct.
 */
final class ReviewJobState
{
    /** @param list<AssetReview> $assets */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly Stage $stage,
        public readonly ?string $creatorId,
        public readonly Team $team,
        public readonly JobReview $review,
        public readonly array $assets,
    ) {}
}

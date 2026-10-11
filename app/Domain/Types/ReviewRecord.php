<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\ReviewDecision;
use App\Domain\ReviewPart;

/** One decision on one part (asset_reviews), with the reviewer's name. Go: type ReviewRecord struct. */
final class ReviewRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $assetId,
        public readonly ReviewPart $part,
        public readonly int $round,
        public readonly ReviewDecision $decision,
        public readonly string $feedback,
        public readonly ?string $reviewerId,
        public readonly string $reviewerName,
        public readonly string $reviewedAt,
        public readonly string $briefVersion,
    ) {}

    public static function fromRow(array $r): ?self
    {
        $part = ReviewPart::tryFrom((string) $r['part']);
        $decision = ReviewDecision::tryFrom((string) $r['decision']);
        if ($part === null || $decision === null) {
            return null;
        }
        return new self(
            (string) $r['id'], (string) $r['asset_id'], $part, (int) $r['round'], $decision, (string) ($r['feedback'] ?? ''),
            $r['reviewer_id'] !== null && $r['reviewer_id'] !== '' ? (string) $r['reviewer_id'] : null, (string) ($r['reviewer_name'] ?? ''),
            (string) $r['reviewed_at'], (string) ($r['brief_version'] ?? ''),
        );
    }
}

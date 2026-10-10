<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Platform;
use App\Domain\PublicationChecklist;
use App\Domain\PublicationStatus;

/** An asset_publications row (migration 0012): one asset on one platform. Go: type Publication struct. */
final class Publication
{
    public function __construct(
        public readonly string $id,
        public readonly string $assetId,
        public readonly string $jobId,
        public readonly Platform $platform,
        public readonly PublicationStatus $status,
        public readonly ?string $scheduledAt,   // 'Y-m-d H:i', South African time
        public readonly string $liveUrl,
        public readonly bool $promoted,
        public readonly ?string $promotedAt,
        public readonly string $promotedNote,
        public readonly PublicationChecklist $checklist,
        public readonly ?string $updatedBy,
        public readonly string $updatedAt,
        public readonly int $rowVersion,
    ) {}

    /** Null when the row holds a platform or status this code does not know (skipped, never guessed). */
    public static function fromRow(array $r): ?self
    {
        $platform = Platform::tryFrom((string) $r['platform']);
        $status = PublicationStatus::tryFrom((string) $r['status']);
        if ($platform === null || $status === null) {
            return null;
        }
        $str = static fn (string $k): ?string => isset($r[$k]) && $r[$k] !== null && $r[$k] !== '' ? (string) $r[$k] : null;
        return new self(
            (string) $r['id'], (string) $r['asset_id'], (string) $r['job_id'], $platform, $status,
            $str('scheduled_at'), (string) ($str('live_url') ?? ''), (int) ($r['promoted'] ?? 0) === 1, $str('promoted_at'),
            (string) ($str('promoted_note') ?? ''), PublicationChecklist::fromJson($str('checklist_json')),
            $str('updated_by'), (string) ($r['updated_at'] ?? ''), (int) $r['row_version'],
        );
    }
}

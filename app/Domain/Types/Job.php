<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Stage;
use App\Domain\WaitingOn;

/** A jobs row with its campaign and brand names (joined, null when the campaign is missing). */
final class Job
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobNumber,
        public readonly ?string $campaignId,
        public readonly string $title,
        public readonly string $status,
        public readonly Stage $stage,
        public readonly ?string $stageChangedAt,
        public readonly ?WaitingOn $waitingOn,
        public readonly string $waitingReason,
        public readonly ?Stage $resumeStage,
        public readonly int $rowVersion,
        public readonly ?string $amUserId,
        public readonly ?string $createdBy,
        public readonly ?string $updatedBy,
        public readonly ?string $deliveryDate,
        public readonly string $updatedAt,
        public readonly ?string $campaignName,
        public readonly ?string $brandId,
        public readonly ?string $brandName,
        public readonly ?string $brandPrefix,
    ) {}

    public static function fromRow(array $r): self
    {
        $stage = Stage::tryFrom((string) ($r['stage'] ?? ''));
        if ($stage === null) {
            $stage = Stage::fromLegacy((string) $r['status']) ?? Stage::Draft;
        }
        return new self(
            (string) $r['id'], (string) $r['job_number'], self::opt($r['campaign_id'] ?? null), (string) $r['title'], (string) $r['status'],
            $stage, self::opt($r['stage_changed_at'] ?? null), WaitingOn::tryFrom((string) ($r['waiting_on'] ?? '')),
            (string) ($r['waiting_reason'] ?? ''), Stage::tryFrom((string) ($r['resume_stage'] ?? '')), (int) $r['row_version'],
            self::opt($r['am_user_id'] ?? null), self::opt($r['created_by'] ?? null), self::opt($r['updated_by'] ?? null),
            self::opt($r['delivery_date'] ?? null), (string) ($r['updated_at'] ?? ''),
            self::opt($r['campaign_name'] ?? null), self::opt($r['brand_id'] ?? null), self::opt($r['brand_name'] ?? null), self::opt($r['brand_prefix'] ?? null),
        );
    }

    private static function opt(mixed $v): ?string
    {
        return $v === null || $v === '' ? null : (string) $v;
    }
}

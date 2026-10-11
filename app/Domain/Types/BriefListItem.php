<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefVersion;
use App\Domain\Stage;

/** One row of "My briefs" and the unowned-jobs list. */
final class BriefListItem
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $campaignName,
        public readonly string $brandName,
        public readonly Stage $stage,
        public readonly BriefVersion $version,
        public readonly bool $sent,
        public readonly bool $hasUnsentChanges,
        public readonly ?string $sentAt,
        public readonly ?string $dueDate,
        public readonly string $updatedAt,
        public readonly string $brandId = '',
        public readonly string $brandLogoUrl = '',
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['job_id'], (string) $r['job_number'], (string) $r['title'], (string) ($r['campaign_name'] ?? ''), (string) ($r['brand_name'] ?? ''),
            Stage::tryFrom((string) $r['stage']) ?? Stage::Draft,
            new BriefVersion((int) $r['version_major'], (int) $r['version_minor'], (int) $r['version_patch']),
            $r['sent_at'] !== null, (int) $r['has_unsent_changes'] === 1,
            $r['sent_at'] !== null ? (string) $r['sent_at'] : null,
            $r['due_date'] !== null && $r['due_date'] !== '' ? (string) $r['due_date'] : null, (string) $r['updated_at'],
            (string) ($r['brand_id'] ?? ''), (string) ($r['brand_logo'] ?? ''),
        );
    }
}

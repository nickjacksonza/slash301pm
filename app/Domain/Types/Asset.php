<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** A legacy assets row (one deliverable piece). status has no CHECK; see AssetStatus. */
final class Asset
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId,
        public readonly ?string $briefAssetId,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $templateId,
        public readonly string $status,
        public readonly ?string $assignedTo,
        public readonly ?string $dueDate,
        public readonly int $sortOrder,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'], (string) $r['job_id'],
            $r['brief_asset_id'] !== null && $r['brief_asset_id'] !== '' ? (string) $r['brief_asset_id'] : null,
            (string) $r['name'], (string) $r['type'],
            $r['template_id'] !== null && $r['template_id'] !== '' ? (string) $r['template_id'] : null,
            (string) $r['status'],
            $r['assigned_to'] !== null && $r['assigned_to'] !== '' ? (string) $r['assigned_to'] : null,
            $r['due_date'] !== null && $r['due_date'] !== '' ? (string) $r['due_date'] : null,
            (int) $r['sort_order'],
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefDiff;
use App\Domain\BriefVersion;

/** A brief_versions row with its decoded snapshot and diff. */
final class BriefVersionRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $briefId,
        public readonly string $jobId,
        public readonly BriefVersion $version,
        public readonly string $bumpLevel,
        public readonly string $note,
        public readonly BriefSnapshot $snapshot,
        public readonly ?BriefDiff $diff,
        public readonly ?string $createdBy,
        public readonly string $createdByName,
        public readonly string $createdAt,
    ) {}

    public static function fromRow(array $r): self
    {
        $snap = json_decode((string) $r['snapshot_json'], true);
        $diff = is_string($r['diff_json'] ?? null) ? json_decode((string) $r['diff_json'], true) : null;
        return new self(
            (string) $r['id'], (string) $r['brief_id'], (string) $r['job_id'],
            new BriefVersion((int) $r['major'], (int) $r['minor'], (int) $r['patch']), (string) $r['bump_level'], (string) ($r['note'] ?? ''),
            BriefSnapshot::fromArray(is_array($snap) ? $snap : [], (string) $r['brief_id'], (string) $r['job_id']),
            is_array($diff) ? BriefDiff::fromArray($diff) : null,
            $r['created_by'] !== null ? (string) $r['created_by'] : null, (string) ($r['created_by_name'] ?? ''), (string) $r['created_at'],
        );
    }
}

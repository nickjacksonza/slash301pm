<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One asset_status_overridden activity row, as the Overrides report shows it. Go: type OverrideRecord struct. */
final class OverrideRecord
{
    public function __construct(
        public readonly string $createdAt,
        public readonly string $actorName,
        public readonly string $actorRole,
        public readonly ?string $jobId,
        public readonly string $jobNumber,
        public readonly string $jobTitle,
        /** 'asset' or 'publication' */
        public readonly string $kind,
        public readonly string $assetName,
        public readonly string $platform,
        public readonly string $from,
        public readonly string $to,
        public readonly string $reason,
    ) {}

    /** @param array<string,mixed> $r activity columns plus actor_name, actor_role, job_number, job_title */
    public static function fromRow(array $r): self
    {
        $a = Activity::fromRow($r);
        $s = static fn (string $k): string => isset($a->data[$k]) && is_scalar($a->data[$k]) ? (string) $a->data[$k] : '';
        return new self(
            $a->createdAt, $a->actorName, (string) ($r['actor_role'] ?? ''), $a->jobId, (string) ($r['job_number'] ?? ''), (string) ($r['job_title'] ?? ''),
            $s('kind') !== '' ? $s('kind') : 'asset', $s('asset_name'), $s('platform'), $s('from'), $s('to'), $s('reason'),
        );
    }
}

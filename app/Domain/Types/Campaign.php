<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** A campaign with its brand (joined). jobCount counts every job in it. */
final class Campaign
{
    public function __construct(
        public readonly string $id,
        public readonly string $brandId,
        public readonly string $brandName,
        public readonly string $brandPrefix,
        public readonly string $name,
        public readonly string $description,
        public readonly string $status,
        public readonly int $jobCount,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'], (string) $r['brand_id'], (string) $r['brand_name'], (string) $r['brand_prefix'],
            (string) $r['name'], (string) ($r['description'] ?? ''), (string) ($r['status'] ?? 'active'), (int) ($r['job_count'] ?? 0),
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One activity row (event log / notification source). data is the decoded data_json. */
final class Activity
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $id,
        public readonly ?string $jobId,
        public readonly ?string $actorId,
        public readonly string $actorName,
        public readonly string $verb,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly array $data,
        public readonly string $createdAt,
    ) {}

    public static function fromRow(array $r): self
    {
        $data = is_string($r['data_json'] ?? null) ? json_decode((string) $r['data_json'], true) : null;
        return new self(
            (string) $r['id'], $r['job_id'] !== null ? (string) $r['job_id'] : null, $r['actor_id'] !== null ? (string) $r['actor_id'] : null,
            (string) ($r['actor_name'] ?? ''), (string) $r['verb'], (string) $r['entity_type'], (string) $r['entity_id'],
            is_array($data) ? $data : [], (string) $r['created_at'],
        );
    }
}

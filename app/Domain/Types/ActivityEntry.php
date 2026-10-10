<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * An activity row to write. verb is a notification catalogue event name
 * (docs/roles.md section 3) or a plain log verb. data must be JSON-encodable;
 * recipients (user ids) go in data['recipients'].
 */
final class ActivityEntry
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly ?string $jobId,
        public readonly ?string $actorId,
        public readonly string $verb,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly array $data = [],
    ) {}
}

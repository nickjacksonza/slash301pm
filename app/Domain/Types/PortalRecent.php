<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * A job a client contact of the brand decided recently (/portal): signed off,
 * or sent back with feedback. Client-safe. Go: type PortalRecent struct.
 */
final class PortalRecent
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        /** true: signed off; false: changes requested. */
        public readonly bool $signedOff,
        public readonly string $actorName,
        public readonly string $at,
    ) {}
}

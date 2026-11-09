<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * A job on /portal, client-safe: number, sent title, brand, when it was marked
 * ready for client review (UTC), and its post counts. Never budget, hours,
 * internal feedback, notes or staff details. Go: type PortalJob struct.
 */
final class PortalJob
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly string $readyAt,
        public readonly int $posts,
        public readonly int $clientApproved,
    ) {}
}

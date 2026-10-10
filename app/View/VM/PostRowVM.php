<?php
declare(strict_types=1);

namespace App\View\VM;

/** One Social post (publication) under an asset on the assets page. */
final class PostRowVM
{
    public function __construct(
        public readonly string $id,
        public readonly string $platformLabel,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly int $rowVersion,
    ) {}
}

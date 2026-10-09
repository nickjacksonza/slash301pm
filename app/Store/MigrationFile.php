<?php
declare(strict_types=1);

namespace App\Store;

/** One migrations/NNNN_name.sql file. */
final class MigrationFile
{
    public function __construct(
        public readonly int $version,
        public readonly string $name,
        public readonly string $path,
        public readonly string $sha256,
    ) {}
}

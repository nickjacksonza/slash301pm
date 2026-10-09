<?php
declare(strict_types=1);

namespace App\Store;

/** A data/backups/pre-NNNN-*.db file. */
final class BackupFile
{
    public function __construct(
        public readonly string $name,
        public readonly int $bytes,
        public readonly int $modifiedAt,
    ) {}
}

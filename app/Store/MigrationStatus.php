<?php
declare(strict_types=1);

namespace App\Store;

/** For /healthz and /admin/system. */
final class MigrationStatus
{
    /**
     * @param list<MigrationFile> $pending
     * @param list<AppliedMigration> $applied
     * @param list<BackupFile> $backups newest first
     * @param list<int> $modified applied versions whose file sha256 changed since
     */
    public function __construct(
        public readonly int $current,
        public readonly int $latest,
        public readonly array $pending,
        public readonly array $applied,
        public readonly array $backups,
        public readonly ?string $failureJson,
        public readonly array $modified,
    ) {}

    public function isCurrent(): bool
    {
        return $this->current >= $this->latest && $this->failureJson === null;
    }
}

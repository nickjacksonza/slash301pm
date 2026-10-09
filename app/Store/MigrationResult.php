<?php
declare(strict_types=1);

namespace App\Store;

/** What one Migrator::run() did. */
final class MigrationResult
{
    /** @param list<int> $applied */
    public function __construct(
        public readonly bool $ok,
        public readonly array $applied,
        public readonly ?string $backupPath,
        public readonly string $error,
    ) {}

    public static function nothing(): self
    {
        return new self(true, [], null, '');
    }
}

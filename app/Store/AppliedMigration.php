<?php
declare(strict_types=1);

namespace App\Store;

/** A schema_migrations row. */
final class AppliedMigration
{
    public function __construct(
        public readonly int $version,
        public readonly string $name,
        public readonly string $sha256,
        public readonly string $appliedAt,
        public readonly int $ms,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self((int) $r['version'], (string) $r['name'], (string) $r['sha256'], (string) $r['applied_at'], (int) $r['ms']);
    }
}

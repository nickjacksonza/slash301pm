<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * A saved_views row (or a built-in view, id "builtin:<key>", owner ''). The
 * state is kept as JSON and parsed with JobQuery::fromSavedState on use.
 */
final class SavedView
{
    public function __construct(
        public readonly string $id,
        public readonly string $ownerId,
        public readonly string $ownerName,
        public readonly string $screen,
        public readonly string $name,
        public readonly string $stateJson,
        public readonly bool $isShared,
        public readonly bool $isDefault,
        public readonly int $position,
        public readonly bool $builtin = false,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'], (string) $r['owner_id'], (string) ($r['owner_name'] ?? ''), (string) $r['screen'], (string) $r['name'],
            (string) $r['state_json'], (int) $r['is_shared'] === 1, (int) $r['is_default'] === 1, (int) $r['position'],
        );
    }
}

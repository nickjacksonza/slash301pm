<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;

/** A new assets row to insert (the Store generates the id). Status starts as AssetStatus::NEW. */
final class PlannedAsset
{
    public function __construct(
        public readonly string $lineId,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $templateId,
        public readonly ?string $dueDate,
        public readonly int $sortOrder,
        /** The template's default role: the Store assigns the job's holder of that slot, if any. */
        public readonly ?Role $defaultRole = null,
    ) {}
}

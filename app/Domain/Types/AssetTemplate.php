<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;

final class AssetTemplate
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,   // legacy assets.type: image | video | document | copy | audio
        /** Assets from this template go to the job's holder of this slot when the brief is sent (else unassigned). */
        public readonly ?Role $defaultRole = null,
    ) {}
}

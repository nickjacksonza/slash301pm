<?php
declare(strict_types=1);

namespace App\Domain\Types;

final class AssetTemplate
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,   // legacy assets.type: image | video | document | copy | audio
    ) {}
}

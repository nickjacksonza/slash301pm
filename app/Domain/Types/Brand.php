<?php
declare(strict_types=1);

namespace App\Domain\Types;

final class Brand
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $prefix,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self((string) $r['id'], (string) $r['name'], (string) $r['prefix']);
    }
}

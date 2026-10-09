<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/avatar AvatarArgs plus name: when name is set and no children are given, initials and a stable colour are derived from it.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class AvatarProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $name = '',
        public string $backgroundColor = '',
        public string $textColor = '',
        public string $class = '',
        public array $attrs = [],
    ) {}
}

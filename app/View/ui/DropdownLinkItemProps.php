<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/dropdown DropdownMenuLinkItemArgs.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class DropdownLinkItemProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $id = '',
        public string $href = '',
        public string $target = '',
        public string $rel = '',
        public string $variant = '',
        public bool $disabled = false,
        public string $onClick = '',
        public bool $inset = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

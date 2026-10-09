<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/dropdown DropdownMenuTriggerArgs.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class DropdownTriggerProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $id = '',
        public bool $asChild = false,
        public bool $disabled = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

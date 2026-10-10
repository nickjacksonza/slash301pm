<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/sheet SheetArgs. id is the content container id (patch into #<id>). class is unused upstream and omitted.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class SheetProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $id = '',
        public bool $defaultOpen = false,
        public bool $modal = false,
        public string $side = 'right',
        public array $attrs = [],
    ) {}
}

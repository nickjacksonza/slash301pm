<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/dialog DialogCloseArgs.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class DialogCloseProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $dialogId = '',
        public string $returnValue = '',
        public string $variant = 'default',
        public string $class = '',
        public array $attrs = [],
    ) {}
}

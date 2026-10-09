<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/input InputArgs.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class InputProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $type = '',
        public string $class = '',
        public string $placeholder = '',
        public string $value = '',
        public string $name = '',
        public string $id = '',
        public string $formId = '',
        public bool $disabled = false,
        public bool $required = false,
        public array $attrs = [],
    ) {}
}

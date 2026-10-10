<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/select SelectArgs. options is a list of SelectOption.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class SelectProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    /** @param list<SelectOption> $options */
    public function __construct(
        public string $id = '',
        public bool $open = false,
        public bool $defaultOpen = false,
        public string $value = '',
        public string $defaultValue = '',
        public array $options = [],
        public string $name = '',
        public bool $disabled = false,
        public bool $required = false,
        public string $placeholder = '',
        public string $onChange = '',
        public string $class = '',
        public array $attrs = [],
    ) {}
}

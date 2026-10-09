<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * Searchable select built from the DatastarUI select (no DatastarUI source). options is a list of SelectOption.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class ComboboxProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    /** @param list<SelectOption> $options */
    public function __construct(
        public string $id = '',
        public array $options = [],
        public string $value = '',
        public string $name = '',
        public string $placeholder = '',
        public string $searchPlaceholder = 'Search...',
        public string $emptyText = 'No results',
        public bool $disabled = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

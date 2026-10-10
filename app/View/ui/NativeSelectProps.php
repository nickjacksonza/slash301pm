<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * Native <select> styled like the DatastarUI select trigger (no DatastarUI source).
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class NativeSelectProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    /** @param list<SelectOption> $options */
    public function __construct(
        public string $id = '',
        public string $name = '',
        public array $options = [],
        public string $value = '',
        public string $placeholder = '',
        public bool $disabled = false,
        public bool $required = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/select SelectOptionArgs. Also used by the native select and the combobox.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class SelectOption
{
    public function __construct(
        public string $value = '',
        public string $label = '',
        public bool $disabled = false,
        public string $group = '',
    ) {}
}

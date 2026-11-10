<?php
declare(strict_types=1);

namespace App\View\ui;

/** A plain <input type="checkbox"> (posts natively, binds with data-bind). No DatastarUI source. */
final readonly class NativeCheckProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $id = '',
        public string $name = '',
        public string $value = '',
        public bool $checked = false,
        public bool $disabled = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

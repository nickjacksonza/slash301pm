<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * shadcn/ui table row. selected sets data-state=selected.
 * Source: shadcn/ui new-york-v4 / project (no DatastarUI equivalent)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class TableRowProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public bool $selected = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * Class and extra attributes only. Used by every sub-part that has no state of its own (card parts, dialog header, table parts ...).
 * Source: shadcn/ui new-york-v4 / project (no DatastarUI equivalent)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class PartProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $class = '',
        public array $attrs = [],
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * shadcn/ui new-york-v4 badge.tsx. stage takes a stage key (draft, in_progress ...) or overdue / due_soon and wins over variant.
 * Source: shadcn/ui new-york-v4 / project (no DatastarUI equivalent)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class BadgeProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $variant = 'default',
        public string $stage = '',
        public string $href = '',
        public string $class = '',
        public array $attrs = [],
    ) {}
}

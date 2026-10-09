<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * shadcn/ui new-york-v4 skeleton.tsx. Size comes from class.
 * Source: shadcn/ui new-york-v4 / project (no DatastarUI equivalent)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class SkeletonProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $class = '',
        public array $attrs = [],
    ) {}
}

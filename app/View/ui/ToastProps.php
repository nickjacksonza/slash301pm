<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * DatastarUI components/toast ToastItemArgs. kind: ok|warn|error|info|default (or the upstream names success|destructive). durationMs > 0 with open=true auto-dismisses.
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/
 */
final readonly class ToastProps
{
    /** @param array<string,string|int|bool> $attrs extra attributes, keys match /^[a-z0-9:_.\-]+$/, values are escaped, true prints a bare attribute */
    public function __construct(
        public string $id = '',
        public string $title = '',
        public string $description = '',
        public string $kind = 'default',
        public int $durationMs = 0,
        public bool $open = false,
        public string $class = '',
        public array $attrs = [],
    ) {}
}

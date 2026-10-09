<?php
declare(strict_types=1);

namespace App\View\VM;

final class NavItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $href,
        public readonly bool $enabled,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\VM;

/** Everything the app shell shows. Built by App\Http\Handlers\Shell from the request. */
final class LayoutVM
{
    /**
     * @param list<NavItem> $nav
     * @param list<NavItem> $adminNav empty unless the user is COO/ECD
     */
    public function __construct(
        public readonly string $title,
        public readonly string $active,
        public readonly string $userName,
        public readonly string $userRole,
        public readonly string $csrf,
        public readonly bool $demoMode,
        public readonly array $nav,
        public readonly array $adminNav,
    ) {}
}

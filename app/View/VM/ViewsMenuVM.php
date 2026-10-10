<?php
declare(strict_types=1);

namespace App\View\VM;

/** #views-menu: built-in, own and shared views, the save dialog's options. */
final class ViewsMenuVM
{
    /**
     * @param list<ViewItemVM> $builtins
     * @param list<ViewItemVM> $mine
     * @param list<ViewItemVM> $shared
     */
    public function __construct(
        public readonly string $screen,
        public readonly array $builtins,
        public readonly array $mine,
        public readonly array $shared,
        public readonly string $activeName,
        public readonly bool $canShare,
        public readonly string $createUrl,
    ) {}
}

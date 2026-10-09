<?php
declare(strict_types=1);

use App\View\ui\SkeletonProps;

/**
 * Source: shadcn/ui new-york-v4 skeleton.tsx (no DatastarUI equivalent). Fixture is hand written.
 * Size comes from the caller's class, for example 'h-4 w-[250px]'.
 */
function ui_skeleton(SkeletonProps $p): string
{
    return '<div data-slot="skeleton" class="' . attr(cx('animate-pulse rounded-md bg-accent', $p->class)) . '"' . ui_attrs($p->attrs) . '></div>';
}

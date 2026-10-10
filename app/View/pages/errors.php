<?php
declare(strict_types=1);

/** Standalone error page (no shell, no user data). */
function page_error(int $status, string $title, string $message): string
{
    $content = '<div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">'
        . '<p class="text-sm text-muted-foreground">Error ' . e($status) . '</p>'
        . '<h1 class="mt-1 text-xl font-semibold">' . e($title) . '</h1>'
        . '<p class="mt-2 text-sm">' . e($message) . '</p>'
        . '<p class="mt-4 text-sm"><a class="underline" href="' . attr(url('/today')) . '">Go to My day</a> · <a class="underline" href="' . attr(url('/legacy/')) . '">Open the old app</a></p>'
        . '</div>';
    return layout_bare($title, $content);
}

function page_maintenance(string $message): string
{
    $content = '<div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">'
        . '<h1 class="text-xl font-semibold">Back in a moment</h1>'
        . '<p class="mt-2 text-sm">' . e($message) . '</p>'
        . '<p class="mt-4 text-sm"><a class="underline" href="' . attr(url('/legacy/')) . '">Open the old app</a></p>'
        . '</div>';
    return layout_bare('Maintenance', $content);
}

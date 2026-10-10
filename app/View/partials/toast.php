<?php
declare(strict_types=1);

/**
 * One toast, appended to #toasts by Response (SSE) or inside a fresh #toasts
 * region (html transport). Removes itself after 4 seconds.
 * $kind: ok | warn | error | info (App\Http\ToastKind).
 */
function partial_toast(string $kind, string $message, string $linkUrl = '', string $linkLabel = ''): string
{
    $classes = [
        'ok' => 'pointer-events-auto w-full rounded-md border border-border bg-card px-4 py-3 text-sm text-card-foreground shadow-lg',
        'info' => 'pointer-events-auto w-full rounded-md border border-border bg-card px-4 py-3 text-sm text-card-foreground shadow-lg',
        'warn' => 'pointer-events-auto w-full rounded-md border border-border bg-accent px-4 py-3 text-sm text-accent-foreground shadow-lg',
        'error' => 'pointer-events-auto w-full rounded-md border border-destructive bg-card px-4 py-3 text-sm text-destructive shadow-lg',
    ];
    $labels = ['ok' => 'Done', 'info' => 'Note', 'warn' => 'Warning', 'error' => 'Error'];
    $class = $classes[$kind] ?? $classes['info'];
    $label = $labels[$kind] ?? $labels['info'];
    $role = $kind === 'error' ? 'alert' : 'status';
    return '<div class="' . $class . '" role="' . $role . '" data-toast="' . attr($kind) . '" data-init__delay.4s="el.remove()">'
        . '<span class="sr-only">' . e($label) . ': </span>' . e($message)
        . ($linkUrl !== '' ? ' <a class="font-medium underline underline-offset-4" href="' . attr($linkUrl) . '">' . e($linkLabel !== '' ? $linkLabel : 'Open') . '</a>' : '')
        . '</div>';
}

/** The #toasts container. Rendered once by the layout; html transport replaces it whole. */
function partial_toasts_region(string $innerHtml): string
{
    return '<div id="toasts" aria-live="polite" class="pointer-events-none fixed right-0 bottom-0 z-[100] flex w-96 max-w-[100vw] flex-col gap-2 p-4">'
        . $innerHtml . '</div>';
}

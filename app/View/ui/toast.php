<?php
declare(strict_types=1);

use App\View\ui\ToastProps;
use App\View\ui\ToastRegionProps;

/**
 * Ported from DatastarUI components/toast/toast.templ + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/toast*.html
 * Signal {<id>: {open}} per item. Upstream items start closed and a trigger opens them; for server pushed toasts pass
 * open: true (the item shows at once) and durationMs: 4000 (auto-dismiss, see below).
 * Auto-dismiss (not upstream, core Datastar only): the wrapper gets
 *   data-init="setTimeout(() => { $<id>.open = false; setTimeout(() => el.remove(), 300) }, <ms>)"
 * which closes the toast, lets the close animation run, then removes the element from the region.
 * Ids must be unique per page: the caller (the Toast event) generates one per toast.
 * kind maps ok -> success, warn -> warning, error -> destructive, info -> info. 'warning' is not upstream
 * (it uses the due-soon tokens). Upstream oddity kept: data-state holds expression text, so data-[state=...] classes are inert.
 */
function ui_toast_variant(string $kind): string
{
    return match ($kind) {
        'ok', 'success' => 'success',
        'warn', 'warning' => 'warning',
        'error', 'destructive' => 'destructive',
        'info' => 'info',
        default => 'default',
    };
}

function ui_toast_region(ToastRegionProps $p, string $children = ''): string
{
    $positions = [
        'top-left' => 'left-0 top-0',
        'top-center' => '-translate-x-1/2 left-1/2 top-0',
        'top-right' => 'right-0 top-0',
        'bottom-left' => 'bottom-0 left-0',
        'bottom-center' => '-translate-x-1/2 bottom-0 left-1/2',
        'bottom-right' => 'bottom-0 right-0',
    ];
    $classes = cx('fixed flex flex-col gap-2 p-4 pointer-events-none z-[100]', ui_pick($positions, $p->position, 'top-right'), $p->class);
    return '<div id="' . attr($p->id) . '" class="' . attr($classes) . '" aria-live="polite" aria-atomic="false"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_toast(ToastProps $p, string $children = ''): string
{
    $variants = [
        'default' => 'bg-background border text-foreground',
        'success' => 'bg-green-50 border border-green-500/50 dark:bg-green-950 dark:border-green-500/50 dark:text-green-50 text-green-900',
        'destructive' => 'bg-destructive border border-destructive/50 text-destructive-foreground',
        'info' => 'bg-blue-50 border border-blue-500/50 dark:bg-blue-950 dark:border-blue-500/50 dark:text-blue-50 text-blue-900',
        'warning' => 'bg-due-soon border border-transparent text-due-soon-foreground',
    ];
    $base = 'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:slide-out-to-right-full data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-right-full duration-300 flex group items-center justify-between overflow-hidden p-6 pointer-events-auto pr-8 relative rounded-md shadow-lg space-x-4 transition-all w-full';
    $classes = cx($base, $variants[ui_toast_variant($p->kind)], $p->class);
    $sig = '$' . ui_sig($p->id);

    $init = '';
    if ($p->open && $p->durationMs > 0) {
        $ms = min($p->durationMs, 600000);
        $init = ' data-init="' . attr("setTimeout(() => { {$sig}.open = false; setTimeout(() => el.remove(), 300) }, {$ms})") . '"';
    }
    $h = '<div' . ui_signals_attr($p->id, ['open' => $p->open]) . ' data-show="' . attr("{$sig}.open") . '"'
        . ($p->open ? '' : ' style="display: none;"') . $init . '>';
    $h .= '<div id="' . attr($p->id) . '" class="' . attr($classes) . '" role="alert" aria-live="assertive" aria-atomic="true"'
        . ' data-state="' . attr("{$sig}.open ? 'open' : 'closed'") . '"' . ui_attrs($p->attrs) . '>';
    $h .= '<div class="grid gap-1 flex-1">';
    if ($p->title !== '') {
        $h .= '<div class="text-sm font-semibold">' . e($p->title) . '</div>';
    }
    if ($p->description !== '') {
        $h .= '<div class="text-sm opacity-90">' . e($p->description) . '</div>';
    }
    $h .= $children . '</div>';
    $h .= '<button type="button" class="absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100" aria-label="Close"'
        . ' data-on:click="' . attr("{$sig}.open = false") . '">'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg></button>';
    return $h . '</div></div>';
}

/** Expression (unescaped, pass through attr()) that opens a toast and optionally closes it again. Upstream ShowToastExpr. */
function ui_toast_show_expr(string $toastId, int $durationMs): string
{
    $sig = '$' . ui_sig($toastId);
    $show = "{$sig}.open = true";
    return $durationMs > 0 ? "{$show}; setTimeout(() => { {$sig}.open = false }, {$durationMs})" : $show;
}

function ui_toast_trigger(string $toastId, int $durationMs, string $children = ''): string
{
    return '<button type="button" data-on:click="' . attr(ui_toast_show_expr($toastId, $durationMs)) . '">' . $children . '</button>';
}

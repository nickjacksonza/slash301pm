<?php
declare(strict_types=1);

use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\DialogTriggerProps;
use App\View\ui\PartProps;

/**
 * Ported from DatastarUI components/dialog/dialog.templ + expressions.go + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/dialog*.html
 * Signal {<id>: {open}}; the dialog id is the DOM id of the panel, the signal root replaces '-' with '_'.
 * Close with returnValue also sets $<id>.returnValue (an undeclared signal upstream, kept as is).
 * Upstream oddities kept: data-on:mount is inert, data-state classes never fire (no data-state attribute is set).
 * Deviation: DialogOverlay is not ported (unused upstream). Upstream ignores Dialog Attributes; here attrs land on the panel element (the one with the id). A class containing max-w- replaces the default max-w-lg.
 */
function ui_dialog(DialogProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    $backdrop = 'bg-black/50 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-50';
    $panel = 'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 fixed left-[50%] max-w-lg top-[50%] translate-x-[-50%] translate-y-[-50%] w-full z-50';
    $container = 'bg-background border max-h-[90vh] overflow-auto p-6 rounded-lg shadow-lg w-full';
    $width = str_contains($p->class, 'max-w-') ? $p->class : cx('max-w-lg', $p->class);

    $h = '<div' . ui_signals_attr($p->id, ['open' => $p->defaultOpen]) . '>';
    $h .= '<div class="' . attr($backdrop) . '" data-show="' . attr("{$sig}.open") . '"'
        . ' data-on:click="' . attr("evt.target === evt.currentTarget ? ({$sig}.open = false) : void 0") . '"'
        . ' data-on:keydown__window="' . attr("evt.key === 'Escape' && {$sig}.open ? ({$sig}.open = false) : void 0") . '"'
        . ($p->defaultOpen ? '' : ' style="display: none;"') . '>';
    $h .= '<div id="' . attr($p->id) . '" class="' . attr($panel) . '" role="dialog" aria-modal="true" tabindex="-1"'
        . ' data-on:click="evt.stopPropagation()" data-on:mount="evt.target.focus()"' . ui_attrs($p->attrs) . '>';
    $h .= '<div class="' . attr(cx($container, $width)) . '">' . $children . '</div>';
    return $h . '</div></div></div>';
}

function ui_dialog_trigger(DialogTriggerProps $p, string $children = ''): string
{
    $click = ' data-on:click="' . attr('$' . ui_sig($p->dialogId) . '.open = true') . '"';
    $tag = $p->asChild ? 'div' : 'button type="button"';
    $close = $p->asChild ? 'div' : 'button';
    return '<' . $tag . $click . ui_attrs($p->attrs) . ui_class_attr($p->class) . '>' . $children . '</' . $close . '>';
}

function ui_dialog_part(string $tag, string $slot, string $classes, PartProps $p, string $children): string
{
    return '<' . $tag . ' data-slot="' . $slot . '" class="' . attr(cx($classes, $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</' . $tag . '>';
}

function ui_dialog_content(PartProps $p, string $children = ''): string
{
    return ui_dialog_part('div', 'dialog-content', 'py-4', $p, $children);
}

function ui_dialog_header(PartProps $p, string $children = ''): string
{
    return ui_dialog_part('div', 'dialog-header', 'flex flex-col gap-2 text-left', $p, $children);
}

function ui_dialog_footer(PartProps $p, string $children = ''): string
{
    return ui_dialog_part('div', 'dialog-footer', 'flex flex-row gap-3 justify-end pt-4', $p, $children);
}

function ui_dialog_title(PartProps $p, string $children = ''): string
{
    return ui_dialog_part('h2', 'dialog-title', 'font-semibold leading-none text-lg', $p, $children);
}

function ui_dialog_description(PartProps $p, string $children = ''): string
{
    return ui_dialog_part('p', 'dialog-description', 'text-muted-foreground text-sm', $p, $children);
}

function ui_dialog_close(DialogCloseProps $p, string $children = ''): string
{
    $base = 'disabled:opacity-50 disabled:pointer-events-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-ring font-medium h-10 inline-flex items-center justify-center px-4 py-2 ring-offset-background rounded-md text-sm transition-colors whitespace-nowrap';
    $variants = [
        'default' => 'bg-primary hover:bg-primary/90 text-primary-foreground',
        'destructive' => 'bg-destructive hover:bg-destructive/90 text-destructive-foreground',
        'outline' => 'bg-background border border-input hover:bg-accent hover:text-accent-foreground text-foreground',
        'secondary' => 'bg-secondary hover:bg-secondary/80 text-secondary-foreground',
        'ghost' => 'hover:bg-accent hover:text-accent-foreground text-foreground',
        'link' => 'hover:underline text-primary underline-offset-4',
    ];
    $sig = '$' . ui_sig($p->dialogId);
    $expr = "{$sig}.open = false";
    if ($p->returnValue !== '') {
        $expr .= "; {$sig}.returnValue = " . ui_js_sq($p->returnValue);
    }
    return '<button type="button" data-on:click="' . attr($expr) . '"' . ui_attrs($p->attrs)
        . ' class="' . attr(cx($base, ui_pick($variants, $p->variant, 'default'), $p->class)) . '">' . $children . '</button>';
}

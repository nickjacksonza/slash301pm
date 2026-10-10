<?php
declare(strict_types=1);

use App\View\ui\PartProps;
use App\View\ui\SheetCloseProps;
use App\View\ui\SheetContentProps;
use App\View\ui\SheetProps;
use App\View\ui\SheetTriggerProps;

/**
 * Ported from DatastarUI components/sheet/sheet.templ + expressions.go + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/sheet*.html
 * Signal {<id>: {open, modal, returnValue}}. id is the DOM id of the panel (default pattern: 'sheet', so pages
 * patch into #sheet with mode inner and open it with a signal patch {"sheet":{"open":true}}).
 * Attributes go on the panel, as upstream. Upstream oddities kept: data-on:mount is inert, the backdrop and
 * panel fade through data-class opacity toggles.
 */
function ui_sheet(SheetProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    $backdrop = 'bg-black/50 data-[state=closed]:opacity-0 data-[state=open]:opacity-100 duration-300 fixed inset-0 transition-opacity z-50';
    $z = $p->modal ? 'z-50' : 'z-40';
    $side = match ($p->side) {
        'top' => 'border-b inset-x-0 top-0',
        'bottom' => 'border-t bottom-0 inset-x-0',
        'left' => 'border-r h-full inset-y-0 left-0 sm:max-w-sm w-3/4',
        default => 'border-l h-full inset-y-0 right-0 sm:max-w-sm w-3/4',
    };
    $panel = cx('bg-background duration-300 fixed gap-4 p-6 shadow-lg transition-opacity', $z, $side);
    $fade = "{'opacity-0': !{$sig}.open, 'opacity-100': {$sig}.open}";
    $hidden = $p->defaultOpen ? '' : ' style="display: none;"';

    $h = '<div' . ui_signals_attr($p->id, ['open' => $p->defaultOpen, 'modal' => $p->modal, 'returnValue' => null]) . '>';
    if ($p->modal) {
        $h .= '<div class="' . attr($backdrop) . '" data-show="' . attr("{$sig}.open") . '" data-class="' . attr($fade) . '"'
            . ' data-on:click="' . attr("evt.target === evt.currentTarget ? ({$sig}.open = false) : void 0") . '"' . $hidden . '></div>';
    }
    $h .= '<div id="' . attr($p->id) . '" data-show="' . attr("{$sig}.open") . '" class="' . attr($panel) . '" data-class="' . attr($fade) . '"'
        . ' role="dialog"' . ($p->modal ? ' aria-modal="true"' : '') . ' tabindex="-1"'
        . ' data-on:keydown__window="' . attr("evt.key === 'Escape' && {$sig}.open ? ({$sig}.open = false) : void 0") . '"'
        . ' data-on:mount="evt.target.focus()"' . $hidden . ui_attrs($p->attrs) . '>' . $children . '</div>';
    return $h . '</div>';
}

function ui_sheet_trigger(SheetTriggerProps $p, string $children = ''): string
{
    $click = ' data-on:click="' . attr('$' . ui_sig($p->sheetId) . '.open = true') . '"';
    if ($p->asChild) {
        return '<div' . $click . ui_attrs($p->attrs) . ui_class_attr($p->class) . '>' . $children . '</div>';
    }
    return '<button type="button"' . $click . ui_attrs($p->attrs) . ui_class_attr($p->class) . '>' . $children . '</button>';
}

function ui_sheet_content(SheetContentProps $p, string $children = ''): string
{
    $closeClass = 'rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-secondary w-6 h-6 flex items-center justify-center shrink-0';
    return '<div data-slot="sheet-content" class="' . attr(cx('flex flex-col relative', $p->class)) . '"' . ui_attrs($p->attrs) . '>'
        . '<div class="flex flex-row justify-end mb-4">'
        . ui_sheet_close(new SheetCloseProps(sheetId: $p->sheetId, class: $closeClass))
        . '</div>' . $children . '</div>';
}

function ui_sheet_part(string $tag, string $slot, string $classes, PartProps $p, string $children): string
{
    return '<' . $tag . ' data-slot="' . $slot . '" class="' . attr(cx($classes, $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</' . $tag . '>';
}

function ui_sheet_header(PartProps $p, string $children = ''): string
{
    return ui_sheet_part('div', 'sheet-header', 'flex flex-col gap-1.5 p-4', $p, $children);
}

function ui_sheet_footer(PartProps $p, string $children = ''): string
{
    return ui_sheet_part('div', 'sheet-footer', 'flex flex-col gap-2 mt-auto p-4', $p, $children);
}

function ui_sheet_title(PartProps $p, string $children = ''): string
{
    return ui_sheet_part('h2', 'sheet-title', 'font-semibold text-foreground', $p, $children);
}

function ui_sheet_description(PartProps $p, string $children = ''): string
{
    return ui_sheet_part('p', 'sheet-description', 'text-muted-foreground text-sm', $p, $children);
}

function ui_sheet_close(SheetCloseProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->sheetId);
    $expr = "{$sig}.open = false";
    if ($p->returnValue !== '') {
        $expr .= "; {$sig}.returnValue = " . ui_js_sq($p->returnValue);
    }
    $click = ' data-on:click="' . attr($expr) . '"';
    if ($p->asChild) {
        return '<div' . $click . ui_attrs($p->attrs) . ui_class_attr($p->class) . '>' . $children . '</div>';
    }
    return '<button type="button"' . $click . ui_attrs($p->attrs) . ui_class_attr($p->class) . '>' . $children
        . '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>'
        . '<span class="sr-only">Close</span></button>';
}

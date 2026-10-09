<?php
declare(strict_types=1);

use App\View\ui\ComboboxProps;
use App\View\ui\SelectOption;

/**
 * Source: built from DatastarUI components/select (select.templ, expressions.go) plus shadcn/ui new-york-v4 command.tsx
 * class strings (no DatastarUI combobox exists). Fixture is hand written (tests/fixtures/ui/combobox.html).
 * Signals {<id>: {open, value, label, query, highlighted}}; the root is marked data-combobox-id=<id>.
 * Filtering is client side: each item has data-show with a lower-cased substring test on $<id>.query.
 * Keys, on the search input: ArrowDown/ArrowUp move through the visible, enabled items, Enter picks the highlighted one,
 * Escape closes and clears the query. A hidden input named name carries the value for plain form posts.
 * Core Datastar only (no Pro attributes). Over about 200 options use a server search instead.
 */
function ui_combobox_query(string $id): string
{
    return "document.querySelector('[data-combobox-id=\"{$id}\"]')";
}

function ui_combobox(ComboboxProps $p): string
{
    $id = ui_id($p->id);
    $sig = '$' . ui_sig($id);
    $q = ui_combobox_query($id);
    $label = '';
    foreach ($p->options as $o) {
        if ($o->value === $p->value) {
            $label = $o->label;
            break;
        }
    }
    $labels = array_map(static fn (SelectOption $o): string => $o->label, array_values($p->options));
    $lowerQuery = "{$sig}.query.toLowerCase()";
    $close = "{$sig}.open = false, {$sig}.query = '', {$sig}.highlighted = -1"; // comma form: used inside ternaries
    $closeStmts = "{$sig}.open = false; {$sig}.query = ''; {$sig}.highlighted = -1";
    $visible = "[...{$q}.querySelectorAll('[data-combobox-item]')].filter(n => n.style.display !== 'none' && !n.hasAttribute('data-disabled')).map(n => +n.dataset.index)";

    $triggerClasses = 'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs ring-offset-background focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30 [&>span]:line-clamp-1';
    $panelClasses = 'bg-popover border min-w-[8rem] overflow-hidden rounded-md shadow-md text-popover-foreground z-50 p-0';
    $itemClasses = 'relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4 [&_svg:not([class*=\'text-\'])]:text-muted-foreground';

    $h = '<div data-slot="combobox" data-combobox-id="' . attr($id) . '"'
        . ui_signals_attr($id, ['open' => false, 'value' => $p->value, 'label' => $label, 'query' => '', 'highlighted' => -1])
        . ' data-on:click__outside="' . attr("{$sig}.open && !evt.target.closest('[data-combobox-id=\"{$id}\"]') ? ({$close}) : null") . '"'
        . ' class="' . attr(cx('relative', $p->class)) . '"' . ui_attrs($p->attrs) . '>';
    if ($p->name !== '') {
        $h .= '<input type="hidden" name="' . attr($p->name) . '" data-bind="' . attr("{$sig}.value") . '" />';
    }
    $h .= '<button data-slot="combobox-trigger" type="button" role="combobox" class="' . attr($triggerClasses) . '"'
        . ui_bool('disabled', $p->disabled) . ' aria-haspopup="listbox" data-attr:aria-expanded="' . attr("{$sig}.open") . '"'
        . ' data-on:click="' . attr("{$sig}.open ? ({$close}) : ({$sig}.open = true, {$sig}.highlighted = -1, setTimeout(() => {$q}?.querySelector('[data-slot=command-input]')?.focus(), 0))") . '">'
        . '<span class="pointer-events-none truncate" data-text="' . attr("{$sig}.label || " . ui_js_sq($p->placeholder)) . '">' . e($p->placeholder) . '</span>'
        . '<svg class="h-4 w-4 opacity-50 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>'
        . '</button>';

    $h .= '<div data-slot="combobox-content" class="' . attr($panelClasses) . '" data-show="' . attr("{$sig}.open") . '"'
        . ' style="position: absolute; top: 100%; left: 0; right: 0; z-index: 50; display: none;">';
    $h .= '<div data-slot="command-input-wrapper" class="flex h-9 items-center gap-2 border-b px-3">'
        . '<svg class="size-4 shrink-0 opacity-50" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>'
        . '<input data-slot="command-input" type="text" autocomplete="off" role="searchbox" data-bind="' . attr("{$sig}.query") . '" placeholder="' . attr($p->searchPlaceholder) . '"'
        . ' class="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"'
        . ' data-on:input="' . attr("{$sig}.highlighted = -1") . '"'
        . ' data-on:keydown="' . attr(implode('; ', [
            "evt.key === 'ArrowDown' ? (evt.preventDefault(), {$sig}.highlighted = (v => v[Math.min(v.length - 1, v.indexOf({$sig}.highlighted) + 1)] ?? -1)({$visible})) : null",
            "evt.key === 'ArrowUp' ? (evt.preventDefault(), {$sig}.highlighted = (v => v[Math.max(0, v.indexOf({$sig}.highlighted) - 1)] ?? -1)({$visible})) : null",
            "evt.key === 'Enter' && {$sig}.highlighted >= 0 ? (evt.preventDefault(), {$q}.querySelector('[data-combobox-item][data-index=\"' + {$sig}.highlighted + '\"]')?.click()) : null",
            "evt.key === 'Escape' ? (evt.preventDefault(), evt.stopPropagation(), {$close}) : null",
        ])) . '" />';
    $h .= '</div>';
    $h .= '<div data-slot="command-list" role="listbox" class="max-h-[300px] scroll-py-1 overflow-x-hidden overflow-y-auto"><div data-slot="command-group" class="overflow-hidden p-1 text-foreground">';
    $i = 0;
    foreach ($p->options as $o) {
        $hl = "{$sig}.highlighted === {$i}";
        $labelJs = ui_js_raw($o->label);
        $h .= '<div data-slot="command-item" data-combobox-item role="option" tabindex="-1" class="' . attr($itemClasses) . '"'
            . ' data-index="' . $i . '" data-value="' . attr($o->value) . '"'
            . ' data-attr:data-selected="' . attr("{$hl} ? 'true' : false") . '"'
            . ' data-attr:aria-selected="' . attr("{$sig}.value === " . ui_js_raw($o->value)) . '"'
            . ' data-show="' . attr("{$labelJs}.toLowerCase().includes({$lowerQuery})") . '"';
        if ($o->disabled) {
            $h .= ' data-disabled="true"';
        } else {
            $h .= ' data-on:click="' . attr("{$sig}.value = " . ui_js_raw($o->value) . "; {$sig}.label = {$labelJs}; {$closeStmts}") . '"';
        }
        $h .= '>' . '<span class="truncate">' . e($o->label) . '</span>'
            . '<svg class="ml-auto h-4 w-4" data-show="' . attr("{$sig}.value === " . ui_js_raw($o->value)) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>'
            . '</div>';
        $i++;
    }
    $h .= '</div>';
    $h .= '<div data-slot="command-empty" class="py-6 text-center text-sm" data-show="' . attr('!' . ui_js_raw($labels) . ".some(l => l.toLowerCase().includes({$lowerQuery}))") . '" style="display: none;">'
        . e($p->emptyText) . '</div>';
    return $h . '</div></div></div>';
}

<?php
declare(strict_types=1);

use App\View\ui\NativeSelectProps;
use App\View\ui\SelectOption;
use App\View\ui\SelectProps;

/**
 * Ported from DatastarUI components/select/select.templ + expressions.go + variants.go (ui_select)
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/select*.html (ui_select). ui_native_select has no DatastarUI source: shadcn/ui
 * new-york-v4 native-select.tsx, structure checked by render tests.
 *
 * ui_select: signals {<id>: {open, value, label, highlighted, _lastValue}}. The root carries data-select-id=<id>, the keyboard
 * handlers query it. Options render ungrouped first, then each group in first-seen order (upstream iterates a Go map,
 * so group order there is random). Only the auto-render form (from options) is ported, not manual composition.
 * Upstream oddities kept: the hidden input is bound with data-bind="$<id>.value" (a '$' in the value form),
 * data-state classes are inert. onChange is developer JS run when value changes.
 * Use this when you want a styled menu; use ui_native_select for plain forms (works without Datastar, posts natively).
 */
const UI_SELECT_CHEVRON = '<svg class="h-4 w-4 opacity-50 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>';

function ui_select_root_query(string $id): string
{
    return "document.querySelector('[data-select-id=\"{$id}\"]')";
}

function ui_select_item(string $id, SelectOption $o, int $index): string
{
    $sig = '$' . ui_sig($id);
    $hl = "{$sig}.highlighted === {$index}";
    $classes = 'cursor-default data-[disabled]:opacity-50 data-[disabled]:pointer-events-none flex focus:bg-accent focus:text-accent-foreground items-center outline-none pl-2 pr-8 py-1.5 relative rounded-sm select-none text-sm w-full';
    $h = '<div data-slot="select-item" data-select-item class="' . attr($classes) . '"'
        . ' data-class="' . attr("{'bg-accent': {$hl}, 'text-accent-foreground': {$hl}}") . '"'
        . ' role="option" data-value="' . attr($o->value) . '" data-index="' . $index . '" tabindex="0"';
    if (!$o->disabled) {
        $select = "{$sig}.value = " . ui_js_raw($o->value) . "; {$sig}.label = evt.currentTarget.querySelector('.select-item-text')?.textContent.trim() || ''; {$sig}.open = false";
        $h .= ' data-on:click="' . attr($select) . '"'
            . ' data-on:keydown="' . attr("(evt.key === 'Enter' || evt.key === ' ') ? (evt.preventDefault(), evt.target.click()) : null") . '"';
    } else {
        $h .= ' data-disabled="true"';
    }
    $h .= '>';
    $h .= '<span class="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">'
        . '<svg class="h-4 w-4" data-show="' . attr("{$sig}.value === " . ui_js_raw($o->value)) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span>';
    return $h . '<span class="select-item-text truncate">' . e($o->label) . '</span></div>';
}

/** @param list<SelectOption> $options */
function ui_select_options(string $id, array $options): string
{
    $ungrouped = [];
    $groups = [];
    foreach ($options as $o) {
        if ($o->group !== '') {
            $groups[$o->group][] = $o;
        } else {
            $ungrouped[] = $o;
        }
    }
    $h = '';
    $i = 0;
    foreach ($ungrouped as $o) {
        $h .= ui_select_item($id, $o, $i++);
    }
    foreach ($groups as $name => $members) {
        $h .= '<div data-slot="select-separator" class="-mx-1 bg-muted h-px my-1"></div>';
        $h .= '<div data-slot="select-group" class="" role="group">';
        $h .= '<div data-slot="select-label" class="font-semibold px-2 py-1.5 text-sm">' . e((string) $name) . '</div>';
        foreach ($members as $o) {
            $h .= ui_select_item($id, $o, $i++);
        }
        $h .= '</div>';
    }
    return $h;
}

function ui_select(SelectProps $p, string $children = ''): string
{
    $id = ui_id($p->id);
    $sig = '$' . ui_sig($id);
    $initial = $p->value !== '' ? $p->value : $p->defaultValue;
    $label = '';
    foreach ($p->options as $o) {
        if ($o->value === $initial) {
            $label = $o->label;
            break;
        }
    }
    $q = ui_select_root_query($id);
    $outside = "{$sig}.open && !evt.target.closest('[data-select-id=\"{$id}\"]') ? {$sig}.open = false : null";
    $effect = $p->onChange === '' ? '' : ' data-effect="' . attr("if ({$sig}.value !== {$sig}._lastValue) { {$sig}._lastValue = {$sig}.value; {$p->onChange} }") . '"';

    $h = '<div data-slot="select" data-select-id="' . attr($id) . '"'
        . ui_signals_attr($id, ['open' => $p->defaultOpen || $p->open, 'value' => $initial, 'label' => $label, 'highlighted' => -1, '_lastValue' => $initial])
        . $effect . ' data-on:click__outside="' . attr($outside) . '" class="' . attr(cx('relative', $p->class)) . '"' . ui_attrs($p->attrs) . '>';
    if ($p->name !== '') {
        $h .= '<input type="hidden" name="' . attr($p->name) . '" data-bind="' . attr("{$sig}.value") . '"' . ui_bool('required', $p->required) . ' />';
    }
    if ($children !== '') {
        return $h . $children . '</div>';
    }

    $triggerClasses = '[&>span]:line-clamp-1 bg-transparent border border-input disabled:cursor-not-allowed disabled:opacity-50 flex focus:outline-none focus:ring-1 focus:ring-ring h-9 items-center justify-between placeholder:text-muted-foreground px-3 py-2 ring-offset-background rounded-md shadow-sm text-sm w-full whitespace-nowrap';
    $keys = [
        "evt.key === 'ArrowDown' && !{$sig}.open ? ({$sig}.open = true, {$sig}.highlighted = 0, evt.preventDefault()) : null",
        "evt.key === 'ArrowUp' && !{$sig}.open ? ({$sig}.open = true, {$sig}.highlighted = {$q}.querySelectorAll('[data-select-item]:not([data-disabled])').length - 1, evt.preventDefault()) : null",
        "evt.key === ' ' && !{$sig}.open ? ({$sig}.open = true, {$sig}.highlighted = -1, evt.preventDefault()) : null",
        "evt.key === 'Enter' && !{$sig}.open ? ({$sig}.open = true, {$sig}.highlighted = -1, evt.preventDefault()) : null",
    ];
    $h .= '<button data-slot="select-trigger" type="button" role="combobox" data-attr:aria-expanded="' . attr("{$sig}.open") . '"'
        . ' class="' . attr($triggerClasses) . '"' . ui_bool('disabled', $p->disabled || count($p->options) === 0)
        . ' data-on:click="' . attr("{$sig}.open ? ({$sig}.open = false, {$sig}.highlighted = -1) : ({$sig}.open = true, {$sig}.highlighted = -1)") . '"'
        . ' data-on:keydown="' . attr(implode('; ', $keys)) . '">';
    $h .= '<span data-slot="select-value" class="pointer-events-none truncate" data-text="' . attr("{$sig}.label || " . ui_js_sq($p->placeholder)) . '">'
        . e($p->placeholder) . '</span>' . UI_SELECT_CHEVRON . '</button>';

    if (count($p->options) > 0) {
        $max = "{$q}.querySelectorAll('[data-select-item]:not([data-disabled])').length - 1";
        $open = "{$q} && {$sig}.open";
        $win = [
            "evt.key === 'ArrowDown' && {$open} ? (evt.preventDefault(), evt.stopPropagation(), {$sig}.highlighted = Math.min({$max}, {$sig}.highlighted + 1)) : null",
            "evt.key === 'ArrowUp' && {$open} ? (evt.preventDefault(), evt.stopPropagation(), {$sig}.highlighted = Math.max(0, {$sig}.highlighted - 1)) : null",
            "(evt.key === 'Enter' || evt.key === ' ') && {$open} && {$sig}.highlighted >= 0 ? (evt.preventDefault(), evt.stopPropagation(), {$q}.querySelector('[data-select-item][data-index=\"' + {$sig}.highlighted + '\"]')?.click()) : null",
            "evt.key === 'Escape' && {$open} ? (evt.preventDefault(), evt.stopPropagation(), {$sig}.open = false) : null",
            "evt.key === 'Tab' && {$open} ? {$sig}.open = false : null",
        ];
        $contentClasses = 'bg-popover border data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md shadow-md text-popover-foreground z-50';
        $h .= '<div data-slot="select-content" class="' . attr($contentClasses) . '" data-show="' . attr("{$sig}.open") . '"'
            . ' data-on:keydown__window="' . attr(implode('; ', $win)) . '" role="listbox" tabindex="-1"'
            . ' style="position: absolute; top: 100%; left: 0; right: 0; z-index: 50; display: none;">'
            . '<div class="p-1">' . ui_select_options($id, $p->options) . '</div></div>';
    }
    return $h . '</div>';
}

/**
 * Native <select> styled like the select trigger. placeholder adds a first empty option. Groups become <optgroup>.
 * Wrapper and chevron follow shadcn native-select; options get theme colours so the dark list stays readable.
 */
function ui_native_select(NativeSelectProps $p): string
{
    $classes = cx(
        'border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 dark:hover:bg-input/50 h-9 w-full min-w-0 appearance-none rounded-md border bg-transparent px-3 py-2 pr-9 text-sm shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&_option]:bg-popover [&_option]:text-popover-foreground',
        $p->class,
    );
    $opt = static fn (SelectOption $o): string => '<option value="' . attr($o->value) . '"' . ($o->value === $p->value ? ' selected' : '')
        . ui_bool('disabled', $o->disabled) . '>' . e($o->label) . '</option>';
    $ungrouped = '';
    $groups = [];
    foreach ($p->options as $o) {
        if ($o->group !== '') {
            $groups[$o->group][] = $o;
        } else {
            $ungrouped .= $opt($o);
        }
    }
    $body = $p->placeholder !== '' ? '<option value=""' . ($p->value === '' ? ' selected' : '') . ' disabled>' . e($p->placeholder) . '</option>' : '';
    $body .= $ungrouped;
    foreach ($groups as $name => $members) {
        $body .= '<optgroup label="' . attr((string) $name) . '">' . implode('', array_map($opt, $members)) . '</optgroup>';
    }
    return '<div data-slot="native-select-wrapper" class="group/native-select relative w-full has-[select:disabled]:opacity-50">'
        . '<select data-slot="native-select" class="' . attr($classes) . '"' . ui_attr_if('id', $p->id) . ui_attr_if('name', $p->name)
        . ui_bool('disabled', $p->disabled) . ui_bool('required', $p->required) . ui_attrs($p->attrs) . '>' . $body . '</select>'
        . '<svg class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 opacity-50 select-none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>'
        . '</div>';
}

<?php
declare(strict_types=1);

use App\View\ui\CheckboxProps;

/**
 * Ported from DatastarUI components/checkbox/checkbox.templ + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/checkbox*.html
 * Signals {<id>: {checked, disabled}}. A <button role=checkbox> plus an sr-only <input type=checkbox> for plain posts.
 * Upstream oddity kept: name is always printed on the hidden input, even when empty.
 */
function ui_checkbox(CheckboxProps $p): string
{
    $s = '$' . ui_sig($p->id);
    $classes = cx(
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 border border-input dark:aria-invalid:ring-destructive/40 dark:bg-input/30 dark:data-[state=checked]:bg-primary data-[state=checked]:bg-primary data-[state=checked]:border-primary data-[state=checked]:text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 outline-none peer rounded-[4px] shadow-xs shrink-0 size-4 transition-shadow',
        $p->class,
    );
    $h = '<div' . ui_signals_attr($p->id, ['checked' => $p->checked, 'disabled' => $p->disabled]) . '>';
    $h .= '<button type="button" id="' . attr($p->id) . '" class="' . attr($classes) . '"'
        . ' data-class="' . attr("{'cursor-not-allowed opacity-50': {$s}.disabled}") . '"'
        . ' role="checkbox"'
        . ' data-on:click="' . attr("!{$s}.disabled ? {$s}.checked = !{$s}.checked : null") . '"'
        . ' data-attr:aria-checked="' . attr("{$s}.checked ? 'true' : 'false'") . '"'
        . ' data-attr:data-state="' . attr("{$s}.checked ? 'checked' : 'unchecked'") . '"'
        . ' data-attr:disabled="' . attr("{$s}.disabled") . '"'
        . ' data-attr:aria-disabled="' . attr("{$s}.disabled") . '"'
        . ui_attrs($p->attrs) . '>';
    $h .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"'
        . ' data-attr:style="' . attr("{$s}.checked ? 'opacity: 1' : 'opacity: 0'") . '"><path d="M20 6 9 17l-5-5"></path></svg>';
    $h .= '</button>';
    $h .= '<input type="checkbox" name="' . attr($p->name) . '" class="sr-only"'
        . ' data-attr:checked="' . attr("{$s}.checked") . '"'
        . ' data-attr:value="' . attr("{$s}.checked ? 'true' : 'false'") . '"'
        . ' data-attr:disabled="' . attr("{$s}.disabled") . '" tabindex="-1" />';
    return $h . '</div>';
}

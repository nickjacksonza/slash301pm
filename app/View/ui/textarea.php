<?php
declare(strict_types=1);

use App\View\ui\TextareaProps;

/**
 * Ported from DatastarUI components/textarea/textarea.templ + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/textarea.*.html
 * rows defaults to 4 when 0. borderless drops the border and focus ring classes.
 */
function ui_textarea(TextareaProps $p): string
{
    $base = 'bg-transparent disabled:cursor-not-allowed disabled:opacity-50 field-sizing-content flex md:text-sm min-h-16 outline-none placeholder:text-muted-foreground px-3 py-2 rounded-md text-base text-foreground transition-[color,box-shadow] w-full';
    $framed = 'aria-invalid:border-destructive aria-invalid:ring-destructive/20 border border-input dark:aria-invalid:ring-destructive/40 dark:bg-input/30 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 shadow-xs';
    $classes = cx($base, $p->borderless ? '' : $framed, $p->class);
    $bind = '';
    if ($p->formId !== '' && $p->name !== '') {
        $bind = ' data-bind="' . attr(str_replace('-', '_', $p->formId) . '.' . $p->name) . '"';
    }
    return '<textarea data-slot="textarea" class="' . attr($classes) . '"' . ui_attr_if('placeholder', $p->placeholder)
        . ui_attr_if('name', $p->name) . ui_attr_if('id', $p->id) . ui_bool('disabled', $p->disabled)
        . ui_bool('required', $p->required) . ' rows="' . ($p->rows === 0 ? 4 : $p->rows) . '"' . $bind
        . ui_attrs($p->attrs) . '>' . e($p->value) . '</textarea>';
}

<?php
declare(strict_types=1);

use App\View\ui\InputProps;

/**
 * Ported from DatastarUI components/input/input.templ + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/input.*.html
 * Empty type, placeholder, value, name and id are omitted. formId + name gives data-bind="<form_id>.<name>".
 * Deviation: class strings carry no conflict handling (see README), so pass utilities that do not clash (max-w-xs is fine).
 */
function ui_input(InputProps $p): string
{
    $classes = cx(
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 bg-background border border-input dark:aria-invalid:ring-destructive/40 dark:bg-input/30 disabled:cursor-not-allowed disabled:opacity-50 disabled:pointer-events-none file:bg-transparent file:border-0 file:font-medium file:h-7 file:inline-flex file:text-foreground file:text-sm flex focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 h-9 md:text-sm min-w-0 outline-none placeholder:text-muted-foreground px-3 py-1 rounded-md selection:bg-primary selection:text-primary-foreground shadow-xs text-base text-foreground transition-[color,box-shadow] w-full z-auto',
        $p->class,
    );
    $bind = '';
    if ($p->formId !== '' && $p->name !== '') {
        $bind = ' data-bind="' . attr(str_replace('-', '_', $p->formId) . '.' . $p->name) . '"';
    }
    return '<input data-slot="input" class="' . attr($classes) . '"' . ui_attr_if('type', $p->type)
        . ui_attr_if('placeholder', $p->placeholder) . ui_attr_if('value', $p->value) . ui_attr_if('name', $p->name)
        . ui_attr_if('id', $p->id) . ui_bool('disabled', $p->disabled) . ui_bool('required', $p->required)
        . $bind . ui_attrs($p->attrs) . ' />';
}

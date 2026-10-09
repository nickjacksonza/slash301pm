<?php
declare(strict_types=1);

use App\View\ui\LabelProps;

/**
 * Ported from DatastarUI components/label/label.templ + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/label.*.html. $children is escaped HTML built by the caller.
 */
function ui_label(LabelProps $p, string $children = ''): string
{
    $classes = cx(
        'flex font-medium gap-2 group-data-[disabled=true]:opacity-50 group-data-[disabled=true]:pointer-events-none items-center leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-50 select-none text-sm',
        $p->class,
    );
    return '<label data-slot="label" class="' . attr($classes) . '"' . ui_attr_if('for', $p->for) . ui_attrs($p->attrs) . '>'
        . $children . '</label>';
}

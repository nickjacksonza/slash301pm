<?php
declare(strict_types=1);

use App\View\ui\NativeCheckProps;

/**
 * Plain checkbox for forms and Datastar data-bind (including array binds such as q.stages).
 * ui_checkbox is the DatastarUI button checkbox with its own signals; use this one when the page binds the value itself.
 */
function ui_native_check(NativeCheckProps $p): string
{
    return '<input type="checkbox" data-slot="native-check" class="' . attr(cx('accent-primary size-4 shrink-0', $p->class)) . '"'
        . ui_attr_if('id', $p->id) . ui_attr_if('name', $p->name) . ui_attr_if('value', $p->value)
        . ui_bool('checked', $p->checked) . ui_bool('disabled', $p->disabled) . ui_attrs($p->attrs) . ' />';
}

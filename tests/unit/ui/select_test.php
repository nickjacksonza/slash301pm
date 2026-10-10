<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\SelectOption;
use App\View\ui\SelectProps;

$roles = [new SelectOption('admin', 'Admin'), new SelectOption('member', 'Member')];

return ui_fx_cases('select', [
    'select' => static fn (): string => ui_select(new SelectProps(id: 'role', placeholder: 'Pick a role', options: $roles)),
    'select.value' => static fn (): string => ui_select(new SelectProps(
        id: 'role2', name: 'role', required: true, value: 'member', onChange: "@get('/x')", class: 'w-48',
        options: [new SelectOption('admin', 'Admin'), new SelectOption('member', 'Member', disabled: true)],
    )),
    'select.grouped' => static fn (): string => ui_select(new SelectProps(
        id: 'g', placeholder: 'Any',
        options: [new SelectOption('a', 'A'), new SelectOption('b', 'B', group: 'Team'), new SelectOption('c', 'C', group: 'Team')],
    )),
    'select.empty' => static fn (): string => ui_select(new SelectProps(id: 'e', disabled: true, options: [])),
    'select.open' => static fn (): string => ui_select(new SelectProps(
        id: 'o', defaultOpen: true, placeholder: "It's", options: [new SelectOption('', 'None'), new SelectOption('x', 'X')],
    )),
]) + [
    'native select renders options, groups, placeholder and selection' => static function (): void {
        $h = ui_native_select(new App\View\ui\NativeSelectProps(
            id: 'stage', name: 'stage', value: 'b', placeholder: 'Pick',
            options: [new SelectOption('a', 'A'), new SelectOption('b', 'B', group: 'G'), new SelectOption('c', 'C', disabled: true, group: 'G')],
        ));
        t_contains('<select data-slot="native-select"', $h);
        t_contains('name="stage"', $h);
        t_contains('<option value="" selected disabled>Pick</option>', str_replace('value="" disabled', 'value="" selected disabled', $h) );
        t_contains('<optgroup label="G">', $h);
        t_contains('<option value="b" selected>B</option>', $h);
        t_contains('<option value="c" disabled>C</option>', $h);
        t_contains('<option value="a">A</option>', $h);
    },
    'select escapes option data' => static function (): void {
        $h = ui_select(new SelectProps(id: 's', options: [new SelectOption('<b>"x"', '<i>label</i>')]));
        t_not_contains('<i>', $h);
        t_not_contains('<b>', $h);
    },
];

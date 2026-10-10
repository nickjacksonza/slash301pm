<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\CheckboxProps;
use App\View\ui\InputProps;
use App\View\ui\LabelProps;
use App\View\ui\TextareaProps;

return ui_fx_cases('input', [
    'input' => static fn (): string => ui_input(new InputProps(type: 'text', name: 'title', id: 'title', placeholder: 'Task title')),
    'input.email' => static fn (): string => ui_input(new InputProps(type: 'email', name: 'email', id: 'email', placeholder: 'you@example.com', required: true)),
    'input.disabled' => static fn (): string => ui_input(new InputProps(type: 'text', name: 'x', value: 'Locked', disabled: true)),
    'input.formbound' => static fn (): string => ui_input(new InputProps(type: 'text', name: 'title', id: 'task-title', formId: 'task-form')),
    'input.extra' => static fn (): string => ui_input(new InputProps(type: 'search', class: 'max-w-xs', attrs: ['data-bind' => '$search', 'autocomplete' => 'off'])),
    'input.notype' => static fn (): string => ui_input(new InputProps(name: 'q')),
    'textarea' => static fn (): string => ui_textarea(new TextareaProps(name: 'notes', id: 'notes', placeholder: 'Notes', rows: 3)),
    'textarea.value' => static fn (): string => ui_textarea(new TextareaProps(name: 'notes', value: 'Hello', required: true, borderless: true, formId: 'brief-form')),
    'textarea.default' => static fn (): string => ui_textarea(new TextareaProps()),
    'label' => static fn (): string => ui_label(new LabelProps(for: 'title'), 'Title'),
    'label.plain' => static fn (): string => ui_label(new LabelProps(class: 'mb-1'), 'Title'),
    'checkbox' => static fn (): string => ui_checkbox(new CheckboxProps(id: 'agree', name: 'agree', checked: true)),
    'checkbox.off' => static fn (): string => ui_checkbox(new CheckboxProps(id: 'task-done', name: 'done', disabled: true, class: 'mt-1')),
]) + [
    'input value is escaped' => static function (): void {
        $h = ui_input(new InputProps(value: '"><script>x</script>'));
        t_not_contains('<script>', $h);
    },
    'textarea value is escaped as text' => static function (): void {
        t_contains('&lt;b&gt;', ui_textarea(new TextareaProps(value: '<b>')));
    },
];

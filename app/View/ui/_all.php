<?php
declare(strict_types=1);

// Loads every ui_* partial. Props classes (App\View\ui\*Props) are autoloaded by app/autoload.php.
require_once __DIR__ . '/_support.php';
foreach ([
    'button', 'input', 'textarea', 'label', 'select', 'checkbox', 'badge', 'card', 'dialog', 'sheet', 'dropdown',
    'tabs', 'tooltip', 'avatar', 'toast', 'table', 'skeleton', 'combobox',
] as $ui_component) {
    require_once __DIR__ . '/' . $ui_component . '.php';
}
unset($ui_component);

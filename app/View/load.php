<?php
declare(strict_types=1);

// Templates are plain functions (Go: templ components), so the autoloader
// cannot find them; load them all once. Order does not matter.

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/layout.php';
foreach (['partials', 'pages'] as $s301Dir) {
    foreach (glob(__DIR__ . '/' . $s301Dir . '/*.php') ?: [] as $s301File) {
        require_once $s301File;
    }
}
unset($s301Dir, $s301File);
// DatastarUI ports (owned by the UI kit); ui/_all.php loads every ui_* function.
if (is_file(__DIR__ . '/ui/_all.php')) {
    require_once __DIR__ . '/ui/_all.php';
}

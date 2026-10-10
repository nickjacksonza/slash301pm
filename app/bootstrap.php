<?php
declare(strict_types=1);

// The only file with process-wide setup. Returns the Config.
// Go: the top of cmd/web/main.go.

date_default_timezone_set('Africa/Johannesburg');
mb_internal_encoding('UTF-8');

// Every warning or notice becomes an exception. Deprecations are only logged:
// the server runs PHP 8.4, where the legacy session option sid_length (kept so
// both apps share one session) is deprecated but still works.
set_error_handler(static function (int $errno, string $message, string $file, int $line): bool {
    if ((error_reporting() & $errno) === 0) {
        return false;
    }
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        error_log("[slash301pm] deprecated: $message at $file:$line");
        return true;
    }
    throw new ErrorException($message, 0, $errno, $file, $line);
});

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/View/load.php';

$s301Config = require __DIR__ . '/config.php';
$s301Logs = $s301Config->logsDir();
if (!is_dir($s301Logs)) {
    @mkdir($s301Logs, 0750, true);
}
foreach ($s301Config->iniSettings(date('Y-m-d')) as $s301Key => $s301Value) {
    if ($s301Key === 'error_log' && !is_dir($s301Logs)) {
        continue;   // keep the host default rather than lose the log
    }
    ini_set($s301Key, $s301Value);
}
app_base_path($s301Config->basePath);

return $s301Config;

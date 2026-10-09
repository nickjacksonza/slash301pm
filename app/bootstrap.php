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
if ($s301Config->isLive()) {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
app_base_path($s301Config->basePath);

return $s301Config;

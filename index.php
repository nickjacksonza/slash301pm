<?php
declare(strict_types=1);

// Front controller for the new app (everything under /slash301pm/ except
// legacy/, api/ and public/, see .htaccess). Go: cmd/web/main.go.

use App\Clock\SystemClock;
use App\Http\Deps;
use App\Http\ErrorResponse;
use App\Http\Kernel;
use App\Http\Middleware\SecurityHeaders;
use App\Http\PhpSession;
use App\Http\Request;
use App\Logs\AppLog;

$config = require __DIR__ . '/app/bootstrap.php';

$clock = new SystemClock();
$log = new AppLog($config->logsDir(), $clock);
$requestId = AppLog::newRequestId();
$session = new PhpSession($config);
$request = Request::fromGlobals($config->basePath);
try {
    $deps = Deps::build($config, $clock);
    $app = Kernel::build(require __DIR__ . '/app/routes.php', $session);
    $response = $app($request, $deps);
} catch (Throwable $e) {
    // Full detail to data/logs only; the user sees the request id (display_errors is off, see Config::iniSettings).
    $uid = $session->get('user_id');
    $log->exception($requestId, $e, $request->method(), $request->path(), is_string($uid) ? $uid : null);
    $response = SecurityHeaders::apply(ErrorResponse::for($request->isDatastar(), $requestId), $config);
}
// Release the session lock before writing, so a slow response never blocks other tabs.
$session->close();
try {
    $response->send($config->transport);   // renders fully before the first header, so a LogicException leaves nothing half-sent
} catch (Throwable $e) {
    $log->exception($requestId, $e, $request->method(), $request->path(), null);
    if (!headers_sent()) {
        SecurityHeaders::apply(ErrorResponse::for($request->isDatastar(), $requestId), $config)->send($config->transport);
    }
}

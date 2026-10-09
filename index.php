<?php
declare(strict_types=1);

// Front controller for the new app (everything under /slash301pm/ except
// legacy/, api/ and public/, see .htaccess). Go: cmd/web/main.go.

use App\Clock\SystemClock;
use App\Http\Deps;
use App\Http\Kernel;
use App\Http\PhpSession;
use App\Http\Request;
use App\Http\Response;

$config = require __DIR__ . '/app/bootstrap.php';

$session = new PhpSession($config);
$request = Request::fromGlobals($config->basePath);
try {
    $deps = Deps::build($config, new SystemClock());
    $app = Kernel::build(require __DIR__ . '/app/routes.php', $session);
    $response = $app($request, $deps);
} catch (Throwable $e) {
    error_log('[slash301pm] ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    $detail = $config->isLive() ? 'Something went wrong on our side. Try again in a moment.' : get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine();
    $response = Response::page(page_error(500, 'Server error', $detail), 500);
}
// Release the session lock before writing, so a slow response never blocks other tabs.
$session->close();
try {
    $response->send($config->transport);   // renders fully before the first header, so a LogicException leaves nothing half-sent
} catch (Throwable $e) {
    error_log('[slash301pm] send: ' . get_class($e) . ': ' . $e->getMessage());
    if (!headers_sent()) {
        Response::page(page_error(500, 'Server error', $config->isLive() ? 'Something went wrong on our side.' : $e->getMessage()), 500)->send($config->transport);
    }
}

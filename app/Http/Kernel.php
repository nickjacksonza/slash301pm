<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Auth;
use App\Http\Middleware\BetaGate;
use App\Http\Middleware\Csrf;
use App\Http\Middleware\MigrateGate;
use App\Http\Middleware\StartSession;
use Closure;

/**
 * The middleware chain, outermost first: MigrateGate, StartSession, Auth, Csrf,
 * BetaGate, then the router. Used by index.php and the integration tests.
 * Go: the handler chain in main.go.
 */
final class Kernel
{
    /** @param list<array{0:string,1:callable}> $routes */
    public static function build(array $routes, Session $session): Closure
    {
        $h = Router::fromTable($routes)->handler();
        $h = BetaGate::wrap($h);
        $h = Csrf::wrap($h);
        $h = Auth::wrap($h);
        $h = StartSession::wrap($h, $session);
        return MigrateGate::wrap($h);
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Policy;
use App\Http\Deps;
use App\Http\Redirect;
use App\Http\Request;
use App\Http\Response;
use Closure;

/** Roles outside Config::newUiRoles (AM, COO, ECD) keep using the legacy app at /legacy/. */
final class BetaGate
{
    private const EXEMPT = ['/login', '/logout', '/demo-login', '/healthz', '/account/password'];

    public static function wrap(Closure $next): Closure
    {
        return static function (Request $r, Deps $d) use ($next): Response {
            $user = $r->user();
            if ($user === null || in_array($r->path(), self::EXEMPT, true)) {
                return $next($r, $d);
            }
            if (!Policy::canUseNewUi($user, $d->config->newUiRoles)->allowed) {
                return $r->isDatastar() ? Response::events(new Redirect(url('/legacy/'))) : Response::redirect(url('/legacy/'), 302);
            }
            return $next($r, $d);
        };
    }
}

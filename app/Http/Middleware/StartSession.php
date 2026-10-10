<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use Closure;

/** Starts the shared legacy session (see PhpSession) and makes sure it holds a CSRF token. */
final class StartSession
{
    public static function wrap(Closure $next, Session $session): Closure
    {
        return static function (Request $r, Deps $d) use ($next, $session): Response {
            if ($r->path() === '/healthz') {
                return $next($r, $d);
            }
            $session->start();
            $token = $session->get('csrf_token');
            if (!is_string($token) || $token === '') {
                $session->set('csrf_token', bin2hex(random_bytes(32)));
            }
            return $next($r->withSession($session), $d);
        };
    }
}

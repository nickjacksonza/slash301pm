<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\Config;
use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use Closure;

/**
 * Every non-GET request must be same-origin (Origin and Sec-Fetch-Site, when
 * the browser sends them) and carry the session csrf_token:
 * - Datastar actions: X-CSRF-Token header (with Datastar-Request: true),
 * - plain HTML forms: a hidden _csrf field.
 * Datastar failures are HTTP 200 with an error toast (Datastar ignores the
 * body of any other status).
 */
final class Csrf
{
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    public static function wrap(Closure $next): Closure
    {
        return static function (Request $r, Deps $d) use ($next): Response {
            if (in_array($r->method(), self::SAFE, true)) {
                return $next($r, $d);
            }
            $problem = self::problem($r, $d->config);
            if ($problem !== '') {
                error_log('[slash301pm] CSRF rejected ' . $r->method() . ' ' . $r->path() . ': ' . $problem);
                return self::reject($r);
            }
            return $next($r, $d);
        };
    }

    /** '' when the request passes, otherwise why it failed (for the log only). */
    public static function problem(Request $r, Config $c): string
    {
        $site = strtolower($r->header('sec-fetch-site'));
        if ($site !== '' && $site !== 'same-origin') {
            return 'Sec-Fetch-Site ' . $site;
        }
        $origin = $r->header('origin');
        if ($origin !== '' && $origin !== $c->expectedOrigin($r->scheme, $r->host())) {
            return 'Origin ' . $origin;
        }
        $expected = $r->csrfToken();
        if ($expected === '') {
            return 'no session token';
        }
        if ($r->isDatastar()) {
            $token = $r->header('x-csrf-token');
        } elseif ($r->isFormPost()) {
            $token = $r->form('_csrf');
        } else {
            return 'neither a Datastar action nor a form post';
        }
        if ($token === '' || !hash_equals($expected, $token)) {
            return 'token mismatch';
        }
        return '';
    }

    private static function reject(Request $r): Response
    {
        if ($r->isDatastar()) {
            return Response::events(Toast::error('Security check failed. Reload the page and try again.'));
        }
        // Stale tokens are normal here (idle timeout ends the login and rotates the token).
        if ($r->path() === '/login' || $r->path() === '/logout') {
            return Response::redirect(url('/login', ['expired' => '1']));
        }
        return Response::page(page_error(403, 'This form has expired', 'Go back, reload the page and try again.'), 403);
    }
}

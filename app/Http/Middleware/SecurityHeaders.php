<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\Config;
use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use Closure;

/**
 * The one place the security headers are decided (docs/adr/0007-security-headers.md).
 * Outermost in the Kernel chain, so maintenance pages, redirects and Datastar
 * answers all carry them; index.php applies the same set to the responses it
 * builds itself (uncaught errors). Go: a middleware setting w.Header().
 *
 * CSP trade-offs:
 * - script-src 'unsafe-eval': Datastar compiles data-* expressions with
 *   Function(). Scripts still load only from this origin and inline scripts
 *   (including scripts patched in by Datastar) are blocked, so Redirect travels
 *   as a signal patch, never as a script.
 * - style-src-attr 'unsafe-inline': Datastar's data-show/data-attr:style and
 *   the DatastarUI ports (display:none before load, avatar colours, popover
 *   positions) use style attributes. <style> elements stay blocked
 *   (style-src-elem 'self'); style-src keeps 'unsafe-inline' only as the
 *   fallback for browsers without the CSP3 split directives.
 */
final class SecurityHeaders
{
    public const CSP = "default-src 'self'; script-src 'self' 'unsafe-eval'; "
        . "style-src 'self' 'unsafe-inline'; style-src-elem 'self'; style-src-attr 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    public const PERMISSIONS = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';

    /** Two years, as browsers' preload lists expect; live only (local runs on plain http). */
    public const HSTS = 'max-age=63072000; includeSubDomains';

    /** @return array<string,string> */
    public static function headers(Config $c): array
    {
        $h = [
            'Content-Security-Policy' => self::CSP,
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => self::PERMISSIONS,
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($c->isLive()) {
            $h['Strict-Transport-Security'] = self::HSTS;
        }
        return $h;
    }

    public static function apply(Response $r, Config $c): Response
    {
        return $r->withHeaders(self::headers($c));
    }

    public static function wrap(Closure $next): Closure
    {
        return static function (Request $r, Deps $d) use ($next): Response {
            return self::apply($next($r, $d), $d->config);
        };
    }
}

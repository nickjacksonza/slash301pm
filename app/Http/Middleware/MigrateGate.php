<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Store\MigrationLocked;
use Closure;

/**
 * First in the chain: applies pending migrations on the first request after a
 * deploy. While a migration failure waits for the owner, the new app shows a
 * maintenance page, except the pages needed to see and retry it.
 */
final class MigrateGate
{
    private const EXEMPT = ['/healthz', '/login', '/logout', '/admin/system', '/admin/system/migrate'];

    public static function wrap(Closure $next): Closure
    {
        return static function (Request $r, Deps $d) use ($next): Response {
            try {
                $result = $d->migrator->ensureCurrent();
            } catch (MigrationLocked $e) {
                return Response::page(page_maintenance('The app is being updated. This page will work again in a few seconds.'), 503)
                    ->withHeader('Retry-After', '5');
            }
            if (!$result->ok && !in_array($r->path(), self::EXEMPT, true)) {
                return Response::page(page_maintenance('An update could not be applied. The previous version of the data is safe and the old app at /legacy/ still works. An admin can see the details at /admin/system.'), 503)
                    ->withHeader('Retry-After', '60');
            }
            return $next($r, $d);
        };
    }
}

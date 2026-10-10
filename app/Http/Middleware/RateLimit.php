<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\RateLimit as Rule;
use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use Closure;

/**
 * Limits state-changing requests per user (or per IP when signed out), after
 * Auth and Csrf. Over the limit:
 * - Datastar actions get HTTP 200 with an error toast (ADR 0005: Datastar
 *   ignores the body of any other status), plus Retry-After;
 * - plain form posts get a 429 page with Retry-After.
 */
final class RateLimit
{
    public static function wrap(Closure $next): Closure
    {
        return static function (Request $r, Deps $d) use ($next): Response {
            if (!Rule::isWrite($r->method())) {
                return $next($r, $d);
            }
            $user = $r->user();
            $key = Rule::key($user?->id, $r->clientIp());
            $now = $d->clock->now()->getTimestamp();
            $w = $d->rateLimits->hit(Rule::BUCKET_WRITES, $key, $now, Rule::WINDOW_SECONDS, Rule::WRITES_PER_WINDOW);
            $decision = Rule::decide($w->count, $w->oldestAt, $now, Rule::WRITES_PER_WINDOW, Rule::WINDOW_SECONDS);
            if ($decision->allowed) {
                return $next($r, $d);
            }
            error_log('[slash301pm] rate limited ' . $r->method() . ' ' . $r->path() . ' for ' . $key);
            $msg = Rule::message($decision->retryAfter);
            $retry = (string) $decision->retryAfter;
            if ($r->isDatastar()) {
                return Response::events(Toast::error($msg))->withHeader('Retry-After', $retry);
            }
            return Response::page(page_error(429, 'Slow down', $msg), 429)->withHeader('Retry-After', $retry);
        };
    }
}

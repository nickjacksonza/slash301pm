<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Sliding-window limit on state-changing requests (POST, PUT, PATCH, DELETE),
 * keyed by the signed-in user, or by client IP when nobody is signed in. A
 * window holds the hit times of the last WINDOW_SECONDS; a request is allowed
 * while fewer than the limit are inside it. Logins keep their own, stricter
 * counter (LoginRules). Pure: the caller passes the counts and the time.
 */
final class RateLimit
{
    public const BUCKET_WRITES = 'writes';
    public const WRITES_PER_WINDOW = 120;
    public const WINDOW_SECONDS = 60;

    /** A user id when signed in, otherwise the client IP. */
    public static function key(?string $userId, string $clientIp): string
    {
        if ($userId !== null && $userId !== '') {
            return 'user:' . $userId;
        }
        return 'ip:' . ($clientIp !== '' ? $clientIp : 'unknown');
    }

    public static function isWrite(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /** True while fewer than $limit hits are inside the window (this request not counted yet). */
    public static function allows(int $hitsInWindow, int $limit): bool
    {
        return $hitsInWindow < $limit;
    }

    /**
     * $hitsInWindow: hits already inside the window; $oldestAt: the oldest of
     * them (unix seconds). Over the limit, retryAfter is when the oldest hit
     * leaves the window (at least 1 second).
     */
    public static function decide(int $hitsInWindow, ?int $oldestAt, int $now, int $limit, int $windowSeconds): RateLimitDecision
    {
        if (self::allows($hitsInWindow, $limit)) {
            return new RateLimitDecision(true, 0);
        }
        $retry = $oldestAt === null ? $windowSeconds : ($oldestAt + $windowSeconds) - $now;
        return new RateLimitDecision(false, max(1, min($windowSeconds, $retry)));
    }

    public static function message(int $retryAfter): string
    {
        return 'Too many changes in a short time. Wait ' . $retryAfter . ' second' . ($retryAfter === 1 ? '' : 's') . ' and try again.';
    }
}

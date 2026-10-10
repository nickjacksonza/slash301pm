<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\RateLimit;

/** rate_limits (migration 0011): the sliding-window hit log. */
final class RateLimitStore
{
    /** Stale keys (other users, old IPs) are dropped once they are this old. */
    public const STALE_SECONDS = 3600;

    public function __construct(private readonly Db $db) {}

    /**
     * In one BEGIN IMMEDIATE: drop this key's hits that left the window, count
     * the rest, and record this hit only when RateLimit::allows() says so (so a
     * flood never grows the table past the limit). Returns the hits that were
     * inside the window BEFORE this request.
     */
    public function hit(string $bucket, string $key, int $now, int $windowSeconds, int $limit): AttemptWindow
    {
        return $this->db->txImmediate(function (Db $tx) use ($bucket, $key, $now, $windowSeconds, $limit): AttemptWindow {
            $since = $now - $windowSeconds;
            $tx->exec('DELETE FROM rate_limits WHERE bucket = :b AND rl_key = :k AND hit_at <= :s', ['b' => $bucket, 'k' => $key, 's' => $since]);
            $row = $tx->one('SELECT COUNT(*) AS cnt, MIN(hit_at) AS oldest FROM rate_limits WHERE bucket = :b AND rl_key = :k', ['b' => $bucket, 'k' => $key]);
            $count = $row === null ? 0 : (int) $row['cnt'];
            $oldest = $row === null || $row['oldest'] === null ? null : (int) $row['oldest'];
            if ($count === 0) {
                // First hit of a fresh window for this key: a cheap moment to drop everyone's stale rows.
                $tx->exec('DELETE FROM rate_limits WHERE hit_at < :old', ['old' => $now - self::STALE_SECONDS]);
            }
            if (RateLimit::allows($count, $limit)) {
                $tx->exec('INSERT INTO rate_limits (bucket, rl_key, hit_at) VALUES (:b, :k, :t)', ['b' => $bucket, 'k' => $key, 't' => $now]);
            }
            return new AttemptWindow($count, $oldest);
        });
    }

    public function count(string $bucket, string $key): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM rate_limits WHERE bucket = :b AND rl_key = :k', ['b' => $bucket, 'k' => $key]);
    }
}

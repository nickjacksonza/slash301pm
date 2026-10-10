<?php
declare(strict_types=1);

namespace App\Store;

/**
 * login_attempts(ip, attempted_at) is shared with the legacy app. Plain IPs use
 * the ip column as legacy does, so both apps count the same IP failures.
 * Per-username counters go in the same column as 'user:<name>' keys, which no
 * legacy query ever matches (no schema change needed).
 */
final class LoginAttemptStore
{
    public function __construct(private readonly Db $db) {}

    public function recordFailure(string $key, int $at): void
    {
        $this->db->txImmediate(function (Db $db) use ($key, $at): void {
            $db->exec('INSERT INTO login_attempts (ip, attempted_at) VALUES (:k, :t)', ['k' => $key, 't' => $at]);
        });
    }

    public function failuresSince(string $key, int $since): AttemptWindow
    {
        $row = $this->db->one(
            'SELECT COUNT(*) AS cnt, MIN(attempted_at) AS oldest FROM login_attempts WHERE ip = :k AND attempted_at > :s',
            ['k' => $key, 's' => $since],
        );
        $count = $row === null ? 0 : (int) $row['cnt'];
        $oldest = $row === null || $row['oldest'] === null ? null : (int) $row['oldest'];
        return new AttemptWindow($count, $oldest);
    }

    public function clear(string $key): void
    {
        $this->db->exec('DELETE FROM login_attempts WHERE ip = :k', ['k' => $key]);
    }

    public function pruneBefore(int $before): void
    {
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < :b', ['b' => $before]);
    }
}

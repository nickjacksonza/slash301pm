-- 0011_rate_limits: hit log for the sliding-window limit on state-changing
-- requests (App\Domain\RateLimit, App\Store\RateLimitStore). One row per
-- allowed write: bucket ('writes'), rl_key ('user:<id>' or 'ip:<addr>') and
-- hit_at (unix seconds). Rows older than the window are pruned on write, so the
-- table stays small (at most the limit per key, plus stale keys for an hour).
-- New table only, written by the new app only; legacy never reads it.

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket TEXT NOT NULL,
    rl_key TEXT NOT NULL,
    hit_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_key ON rate_limits(bucket, rl_key, hit_at);
CREATE INDEX IF NOT EXISTS idx_rate_limits_at ON rate_limits(hit_at);

-- 0010_user_seen: when each user last pressed "Mark all seen" on /today, so the
-- "Changed by others" section shows only newer activity. One row per user,
-- written by the new app only; legacy never reads it. Additive: no existing
-- table is touched. (0009 is reserved for the Phase 3 work; the Migrator
-- applies any file above the current version, so the gap is harmless.)

CREATE TABLE IF NOT EXISTS user_seen (
    user_id TEXT PRIMARY KEY,
    today_seen_at TEXT NOT NULL
);

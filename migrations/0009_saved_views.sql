-- 0009_saved_views: named grid and board views (filters, sort, group, columns).
-- A new table only; the legacy app never reads it. screen is 'jobs' or 'board'
-- and state_json is a JobQuery state built and validated in PHP (no CHECK and
-- no JSON1, so later screens need no rebuild). is_default marks the owner's
-- default per screen (the app keeps at most one). is_shared views are listed
-- for everyone; only manager and admin roles may share (Policy).
-- ON DELETE CASCADE so a legacy user delete still succeeds with foreign_keys on.

CREATE TABLE IF NOT EXISTS saved_views (
    id TEXT PRIMARY KEY,
    owner_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    screen TEXT NOT NULL DEFAULT 'jobs',
    name TEXT NOT NULL,
    state_json TEXT NOT NULL,
    is_shared INTEGER NOT NULL DEFAULT 0,
    is_default INTEGER NOT NULL DEFAULT 0,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_saved_views_owner ON saved_views(owner_id, screen);
CREATE INDEX IF NOT EXISTS idx_saved_views_shared ON saved_views(screen, is_shared);

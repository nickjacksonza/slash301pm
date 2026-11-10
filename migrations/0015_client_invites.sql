-- 0015_client_invites: the client portal's own sign-in (ADR 0006, owner
-- decisions 2026-10-10 and 2026-10-13; docs/portal.md).
--
-- users.auth_subject: the identity provider's subject ("sub") for a later
-- OIDC sign-in (Authorizer). NULL for every user today: everyone signs in with
-- the app's own password. A partial unique index keeps two users from sharing
-- a subject without forbidding the many NULLs. Additive; the legacy app's
-- users INSERTs name their columns, so they keep working.
--
-- invites: single-use invite links for client contacts. Only the sha256 (hex)
-- of the token is stored, never the token itself. A link is valid while
-- used_at IS NULL and expires_at (UTC 'Y-m-d H:i:s') is in the future.
-- Accepting it sets the password, marks the row used and activates the user in
-- one transaction. Issuing a new link for the same pending user expires the
-- older unused rows (expires_at = now); used_at marks an accepted link only.
-- Written and read by the new app only.
--
-- Reused, not added: users.brand_id (the client's one brand), users.is_active
-- (an invited user stays inactive until the link is accepted) and
-- users.client_signoff (0008: 0 Marketing Manager, 1 Brand Director).
--
-- ON DELETE CASCADE / SET NULL so a legacy delete of a user still succeeds
-- with foreign_keys on.

ALTER TABLE users ADD COLUMN auth_subject TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_auth_subject ON users(auth_subject) WHERE auth_subject IS NOT NULL;

CREATE TABLE IF NOT EXISTS invites (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used_at TEXT,
    created_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_invites_user ON invites(user_id, created_at);

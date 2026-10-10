-- 0012_asset_publications: one Social publication record per asset and
-- platform (docs/roles.md 2.13, PLAN.md "Social publishing"). Written by the
-- new app only; the legacy app never reads it, and the job's legacy status
-- stays 'Approved (External)' for every social stage (Stage::toLegacy).
--
-- status: checking | ready_to_schedule | scheduled | live | archived. No CHECK,
-- so a later value needs no rebuild; Domain\PublicationStatus validates it.
-- 'checking' is the row before the final check is complete (the checklist has
-- to live somewhere before Ready to schedule).
-- platform: a Domain\Platform value (facebook, instagram, ...), a config list.
-- scheduled_at: the wall-clock time Social typed, 'Y-m-d H:i' in South African
-- time (not UTC), because that is the time the platform tool shows.
-- checklist_json: built and validated in PHP (no JSON1 on the server):
-- {"copy":{"ok":true,"note":""},"image":...,"link":...,"hashtags":...,"test_result":...}.
-- promoted: paid boost per platform (Q24: a checkbox and a note, never spend).
-- ON DELETE CASCADE / SET NULL so legacy deletes of assets, jobs or users
-- still succeed with foreign_keys on.

CREATE TABLE IF NOT EXISTS asset_publications (
    id TEXT PRIMARY KEY,
    asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    job_id TEXT NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    platform TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'checking',
    scheduled_at TEXT,
    live_url TEXT,
    promoted INTEGER NOT NULL DEFAULT 0,
    promoted_at TEXT,
    promoted_note TEXT,
    checklist_json TEXT,
    created_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    updated_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    row_version INTEGER NOT NULL DEFAULT 1,
    UNIQUE (asset_id, platform)
);

CREATE INDEX IF NOT EXISTS idx_asset_publications_job ON asset_publications(job_id, status);
CREATE INDEX IF NOT EXISTS idx_asset_publications_status ON asset_publications(status, scheduled_at);

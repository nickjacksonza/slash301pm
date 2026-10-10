-- 0004_brief_versions: immutable snapshots of every sent brief (ADR 0003).
-- version is the text form ("1.2.0"); the parts are kept for ordering.
-- bump_level: initial (first send, or the imported baseline), major, minor, patch.
-- snapshot_json and diff_json are built in PHP. Rows are never updated.

CREATE TABLE IF NOT EXISTS brief_versions (
    id TEXT PRIMARY KEY,
    brief_id TEXT NOT NULL REFERENCES briefs(id) ON DELETE CASCADE,
    job_id TEXT NOT NULL,
    version TEXT NOT NULL,
    major INTEGER NOT NULL,
    minor INTEGER NOT NULL,
    patch INTEGER NOT NULL,
    bump_level TEXT NOT NULL,
    note TEXT,
    snapshot_json TEXT NOT NULL,
    diff_json TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL,
    UNIQUE (brief_id, version)
);

CREATE INDEX IF NOT EXISTS idx_brief_versions_job ON brief_versions(job_id);

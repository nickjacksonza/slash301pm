-- 0003_briefs: one brief per job (ADR 0003). The row is the working copy; sent
-- versions are immutable snapshots in brief_versions (0004).
-- title and campaign_id live here too, so edits after the first send stay in the
-- working copy until "Send update" mirrors them to jobs.
-- mandatories and references_json are JSON built in PHP (no JSON1 on the server).
-- Draft briefs are 0.1.0; the first send makes 1.0.0. Jobs already past the brief
-- stage are backfilled as sent 1.0.0 (their baseline snapshot is captured by the
-- app on the first edit). Legacy-created jobs get a brief from a trigger.
-- ON DELETE CASCADE so a legacy job delete still succeeds with foreign_keys on.

CREATE TABLE IF NOT EXISTS briefs (
    id TEXT PRIMARY KEY,
    job_id TEXT NOT NULL UNIQUE REFERENCES jobs(id) ON DELETE CASCADE,
    title TEXT NOT NULL DEFAULT '',
    campaign_id TEXT,
    brief_date TEXT,
    due_date TEXT,
    first_go_live TEXT,
    last_go_live TEXT,
    creative_direction TEXT,
    mandatories TEXT,
    references_json TEXT,
    brief_pdf_url TEXT,
    server_link TEXT,
    budget REAL,
    hours_estimate REAL,
    version_major INTEGER NOT NULL DEFAULT 0,
    version_minor INTEGER NOT NULL DEFAULT 1,
    version_patch INTEGER NOT NULL DEFAULT 0,
    has_unsent_changes INTEGER NOT NULL DEFAULT 0,
    sent_at TEXT,
    sent_by TEXT,
    created_by TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_by TEXT,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    row_version INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_briefs_created_by ON briefs(created_by);

INSERT INTO briefs (id, job_id, title, campaign_id, brief_date, due_date, creative_direction, hours_estimate,
                    version_major, version_minor, version_patch, sent_at, created_by, created_at, updated_at)
SELECT lower(hex(randomblob(16))), j.id, j.title, j.campaign_id, j.brief_date, j.delivery_date, j.creative_direction, j.hours_estimate,
       CASE WHEN j.stage = 'draft' THEN 0 ELSE 1 END,
       CASE WHEN j.stage = 'draft' THEN 1 ELSE 0 END,
       0,
       CASE WHEN j.stage = 'draft' THEN NULL ELSE COALESCE(j.created_at, datetime('now')) END,
       j.created_by,
       COALESCE(j.created_at, datetime('now')),
       COALESCE(j.updated_at, datetime('now'))
FROM jobs j
WHERE NOT EXISTS (SELECT 1 FROM briefs b WHERE b.job_id = j.id);

CREATE TRIGGER IF NOT EXISTS jobs_brief_on_insert
AFTER INSERT ON jobs FOR EACH ROW
BEGIN
    INSERT INTO briefs (id, job_id, title, campaign_id, brief_date, due_date, creative_direction, hours_estimate,
                        version_major, version_minor, version_patch, sent_at, created_by)
    SELECT lower(hex(randomblob(16))), NEW.id, NEW.title, NEW.campaign_id, NEW.brief_date, NEW.delivery_date,
           NEW.creative_direction, NEW.hours_estimate,
           CASE WHEN NEW.status IN ('Inbox', 'Brief') THEN 0 ELSE 1 END,
           CASE WHEN NEW.status IN ('Inbox', 'Brief') THEN 1 ELSE 0 END,
           0,
           CASE WHEN NEW.status IN ('Inbox', 'Brief') THEN NULL ELSE datetime('now') END,
           NEW.created_by
    WHERE NOT EXISTS (SELECT 1 FROM briefs b WHERE b.job_id = NEW.id);
END;

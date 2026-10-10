-- 0006_activity: the event log, written in the same transaction as each change.
-- Notifications (docs/roles.md section 3) are rendered from these rows: verb is
-- the catalogue event name (brief_sent, brief_updated, assigned_to_job, ...);
-- data_json (built in PHP) carries details and the resolved recipients.
-- No foreign keys: the log outlives deleted jobs and users.

CREATE TABLE IF NOT EXISTS activity (
    id TEXT PRIMARY KEY,
    job_id TEXT,
    actor_id TEXT,
    verb TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    data_json TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_activity_job ON activity(job_id, created_at);
CREATE INDEX IF NOT EXISTS idx_activity_created ON activity(created_at);

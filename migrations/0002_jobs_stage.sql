-- 0002_jobs_stage: the new app's workflow stage next to the legacy status column
-- (ADR 0002). Additive only: new nullable columns, a backfill, three triggers.
--
-- Columns (the legacy client strip list in api/api.php names exactly these):
--   stage            draft | briefed | in_progress | waiting | on_hold | in_review
--                    | approved_internal | approved_client | done | archived | cancelled
--   stage_changed_at when stage last changed
--   waiting_on       am | client | creative | third_party (stage waiting only)
--   waiting_reason   free text (stage waiting only)
--   resume_stage     where waiting / on_hold returns to on resume
--   row_version      optimistic concurrency; bumped once per changing UPDATE
--   am_user_id       mirror of the AM slot in job_assignments
--   updated_by       last new-app writer (legacy writes leave it alone)
--
-- Trigger rules (recursive_triggers stays off):
--   * jobs_stage_on_insert: a legacy INSERT (no stage) gets its stage from status.
--   * jobs_stage_from_status: a legacy UPDATE of status that leaves stage alone maps
--     the new status to a stage. No-op when the mapped stage equals the old one, so
--     Today / This Week / In Progress round trips never touch stage.
--   * jobs_row_version: bumps row_version when a legacy column changed and the
--     statement did not bump row_version itself. The new app always writes
--     row_version = row_version + 1, so its writes bump exactly once. The nested
--     updates of jobs_updated_at and the stage triggers only touch updated_at and
--     new columns, which are not in the list, so they never bump again.

ALTER TABLE jobs ADD COLUMN stage TEXT;
ALTER TABLE jobs ADD COLUMN stage_changed_at TEXT;
ALTER TABLE jobs ADD COLUMN waiting_on TEXT;
ALTER TABLE jobs ADD COLUMN waiting_reason TEXT;
ALTER TABLE jobs ADD COLUMN resume_stage TEXT;
ALTER TABLE jobs ADD COLUMN row_version INTEGER NOT NULL DEFAULT 1;
ALTER TABLE jobs ADD COLUMN am_user_id TEXT;
ALTER TABLE jobs ADD COLUMN updated_by TEXT;

-- Backfill (idempotent: only rows without a stage). Waiting and On Hold have no
-- stored resume point in legacy; in_progress is the safe default.
UPDATE jobs SET
    stage = CASE status
        WHEN 'Inbox' THEN 'draft' WHEN 'Brief' THEN 'draft'
        WHEN 'To Do' THEN 'briefed'
        WHEN 'In Progress' THEN 'in_progress' WHEN 'Today' THEN 'in_progress' WHEN 'This Week' THEN 'in_progress'
        WHEN 'Waiting' THEN 'waiting' WHEN 'On Hold' THEN 'on_hold'
        WHEN 'In Review' THEN 'in_review'
        WHEN 'Approved (Internal)' THEN 'approved_internal' WHEN 'Approved (External)' THEN 'approved_client'
        WHEN 'Done' THEN 'done' WHEN 'Archived' THEN 'archived' WHEN 'Cancelled' THEN 'cancelled'
        ELSE 'draft' END,
    stage_changed_at = COALESCE(updated_at, created_at, datetime('now')),
    resume_stage = CASE WHEN status IN ('Waiting', 'On Hold') THEN 'in_progress' ELSE NULL END
WHERE stage IS NULL;

CREATE INDEX IF NOT EXISTS idx_jobs_stage ON jobs(stage);
CREATE INDEX IF NOT EXISTS idx_jobs_am_user ON jobs(am_user_id);

CREATE TRIGGER IF NOT EXISTS jobs_stage_on_insert
AFTER INSERT ON jobs FOR EACH ROW
WHEN NEW.stage IS NULL
BEGIN
    UPDATE jobs SET
        stage = CASE NEW.status
            WHEN 'Inbox' THEN 'draft' WHEN 'Brief' THEN 'draft'
            WHEN 'To Do' THEN 'briefed'
            WHEN 'In Progress' THEN 'in_progress' WHEN 'Today' THEN 'in_progress' WHEN 'This Week' THEN 'in_progress'
            WHEN 'Waiting' THEN 'waiting' WHEN 'On Hold' THEN 'on_hold'
            WHEN 'In Review' THEN 'in_review'
            WHEN 'Approved (Internal)' THEN 'approved_internal' WHEN 'Approved (External)' THEN 'approved_client'
            WHEN 'Done' THEN 'done' WHEN 'Archived' THEN 'archived' WHEN 'Cancelled' THEN 'cancelled'
            ELSE 'draft' END,
        stage_changed_at = datetime('now'),
        resume_stage = CASE WHEN NEW.status IN ('Waiting', 'On Hold') THEN 'in_progress' ELSE NULL END
    WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS jobs_stage_from_status
AFTER UPDATE OF status ON jobs FOR EACH ROW
WHEN NEW.status IS NOT OLD.status
  AND NEW.stage IS OLD.stage
  AND (CASE NEW.status
        WHEN 'Inbox' THEN 'draft' WHEN 'Brief' THEN 'draft'
        WHEN 'To Do' THEN 'briefed'
        WHEN 'In Progress' THEN 'in_progress' WHEN 'Today' THEN 'in_progress' WHEN 'This Week' THEN 'in_progress'
        WHEN 'Waiting' THEN 'waiting' WHEN 'On Hold' THEN 'on_hold'
        WHEN 'In Review' THEN 'in_review'
        WHEN 'Approved (Internal)' THEN 'approved_internal' WHEN 'Approved (External)' THEN 'approved_client'
        WHEN 'Done' THEN 'done' WHEN 'Archived' THEN 'archived' WHEN 'Cancelled' THEN 'cancelled'
        ELSE 'draft' END) IS NOT OLD.stage
BEGIN
    UPDATE jobs SET
        stage = CASE NEW.status
            WHEN 'Inbox' THEN 'draft' WHEN 'Brief' THEN 'draft'
            WHEN 'To Do' THEN 'briefed'
            WHEN 'In Progress' THEN 'in_progress' WHEN 'Today' THEN 'in_progress' WHEN 'This Week' THEN 'in_progress'
            WHEN 'Waiting' THEN 'waiting' WHEN 'On Hold' THEN 'on_hold'
            WHEN 'In Review' THEN 'in_review'
            WHEN 'Approved (Internal)' THEN 'approved_internal' WHEN 'Approved (External)' THEN 'approved_client'
            WHEN 'Done' THEN 'done' WHEN 'Archived' THEN 'archived' WHEN 'Cancelled' THEN 'cancelled'
            ELSE 'draft' END,
        stage_changed_at = datetime('now'),
        resume_stage = CASE
            WHEN NEW.status NOT IN ('Waiting', 'On Hold') THEN NULL
            WHEN OLD.stage IN ('waiting', 'on_hold') THEN COALESCE(OLD.resume_stage, 'in_progress')
            ELSE COALESCE(OLD.stage, 'in_progress') END,
        waiting_on = CASE WHEN NEW.status = 'Waiting' THEN OLD.waiting_on ELSE NULL END,
        waiting_reason = CASE WHEN NEW.status = 'Waiting' THEN OLD.waiting_reason ELSE NULL END
    WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS jobs_row_version
AFTER UPDATE ON jobs FOR EACH ROW
WHEN NEW.row_version IS OLD.row_version AND (
       NEW.job_number IS NOT OLD.job_number OR NEW.campaign_id IS NOT OLD.campaign_id
    OR NEW.title IS NOT OLD.title OR NEW.description IS NOT OLD.description
    OR NEW.status IS NOT OLD.status OR NEW.creative_direction IS NOT OLD.creative_direction
    OR NEW.brief_date IS NOT OLD.brief_date OR NEW.delivery_date IS NOT OLD.delivery_date
    OR NEW.hours_estimate IS NOT OLD.hours_estimate
    OR NEW.internal_approved_by IS NOT OLD.internal_approved_by OR NEW.internal_approved_at IS NOT OLD.internal_approved_at
    OR NEW.client_approved_by IS NOT OLD.client_approved_by OR NEW.client_approved_at IS NOT OLD.client_approved_at
    OR NEW.all_tasks_completed_at IS NOT OLD.all_tasks_completed_at
    OR NEW.client_feedback IS NOT OLD.client_feedback OR NEW.client_feedback_by IS NOT OLD.client_feedback_by
    OR NEW.client_feedback_at IS NOT OLD.client_feedback_at OR NEW.client_feedback_status IS NOT OLD.client_feedback_status
    OR NEW.client_feedback_assigned_to IS NOT OLD.client_feedback_assigned_to
    OR NEW.client_feedback_assigned_role IS NOT OLD.client_feedback_assigned_role
    OR NEW.client_feedback_actioned_by IS NOT OLD.client_feedback_actioned_by
    OR NEW.client_feedback_actioned_at IS NOT OLD.client_feedback_actioned_at
    OR NEW.internal_feedback IS NOT OLD.internal_feedback OR NEW.internal_feedback_by IS NOT OLD.internal_feedback_by
    OR NEW.internal_feedback_at IS NOT OLD.internal_feedback_at
    OR NEW.sort_order IS NOT OLD.sort_order OR NEW.created_by IS NOT OLD.created_by
)
BEGIN
    UPDATE jobs SET row_version = OLD.row_version + 1 WHERE id = NEW.id;
END;

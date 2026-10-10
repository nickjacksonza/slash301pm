-- 0014_reviews: internal reviews and approvals (owner spec 2026-10; docs/roles.md
-- 2.3 ECD, 2.7 CD, the Reviews block of section 4). Written by the new app only;
-- the legacy app never reads these tables. No column is added to jobs, so the
-- legacy client field strip list (api/api.php CLIENT_HIDDEN_JOB_FIELDS) needs no
-- change before this runs.
--
-- asset_submissions: one row per asset and round. Makers hand in a link and
-- copy text (no uploads). The highest round of an asset is its current state;
-- a save after a rejection opens the next round, copying the other part
-- forward so an approved part stays approved. States per part (no CHECK, so a
-- later value needs no rebuild; Domain\PartState validates them):
-- missing | submitted | approved | rejected_with_feedback | rejected.
-- media_kind: image | video | link, derived from media_url in PHP.
-- row_version guards each asset's current round against concurrent writes.
--
-- asset_reviews: every decision on one part (copy or media), with the round
-- and the sent brief version the reviewer checked it against. History only.
--
-- asset_part_makers: who made each part (the last person to hand it in), so a
-- rejection is routed to them deterministically even after a slot changes.
--
-- job_reviews: one row per job in review: the job round, when every asset was
-- handed in, the CD and ECD approvals, the ECD review request, the person the
-- review was reassigned to, ready for client and sent to client.
--
-- ON DELETE CASCADE / SET NULL so legacy deletes of jobs, assets or users
-- still succeed with foreign_keys on.

CREATE TABLE IF NOT EXISTS asset_submissions (
    id TEXT PRIMARY KEY,
    asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    job_id TEXT NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    round INTEGER NOT NULL,
    copy_text TEXT,
    media_url TEXT,
    media_kind TEXT,
    hashtags TEXT,
    link_url TEXT,
    note TEXT,
    copy_state TEXT NOT NULL DEFAULT 'missing',
    media_state TEXT NOT NULL DEFAULT 'missing',
    copy_at TEXT,
    media_at TEXT,
    submitted_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    submitted_at TEXT NOT NULL,
    review_requested_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    review_requested_at TEXT,
    row_version INTEGER NOT NULL DEFAULT 1,
    UNIQUE (asset_id, round)
);

CREATE INDEX IF NOT EXISTS idx_asset_submissions_job ON asset_submissions(job_id, asset_id, round);

CREATE TABLE IF NOT EXISTS asset_reviews (
    id TEXT PRIMARY KEY,
    asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    job_id TEXT NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    submission_id TEXT REFERENCES asset_submissions(id) ON DELETE CASCADE,
    part TEXT NOT NULL,
    round INTEGER NOT NULL,
    decision TEXT NOT NULL,
    feedback TEXT,
    reviewer_id TEXT REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TEXT NOT NULL,
    brief_version TEXT
);

CREATE INDEX IF NOT EXISTS idx_asset_reviews_asset ON asset_reviews(asset_id, reviewed_at);
CREATE INDEX IF NOT EXISTS idx_asset_reviews_job ON asset_reviews(job_id, reviewed_at);

CREATE TABLE IF NOT EXISTS asset_part_makers (
    asset_id TEXT NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    part TEXT NOT NULL,
    maker_user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
    set_at TEXT NOT NULL,
    PRIMARY KEY (asset_id, part)
);

CREATE INDEX IF NOT EXISTS idx_asset_part_makers_user ON asset_part_makers(maker_user_id);

CREATE TABLE IF NOT EXISTS job_reviews (
    job_id TEXT PRIMARY KEY REFERENCES jobs(id) ON DELETE CASCADE,
    job_round INTEGER NOT NULL DEFAULT 1,
    all_done_at TEXT,
    ecd_requested_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    ecd_requested_at TEXT,
    cd_approved_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    cd_approved_at TEXT,
    ecd_approved_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    ecd_approved_at TEXT,
    client_ready_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    client_ready_at TEXT,
    sent_to_client_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    sent_to_client_at TEXT,
    review_assignee_id TEXT REFERENCES users(id) ON DELETE SET NULL,
    review_assigned_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    review_assigned_at TEXT,
    row_version INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_job_reviews_assignee ON job_reviews(review_assignee_id);

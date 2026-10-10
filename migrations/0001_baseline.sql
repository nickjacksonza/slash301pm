-- 0001_baseline: the schema api/db.php createSchema() builds, verbatim, with
-- IF NOT EXISTS everywhere. On the live database every statement is a no-op;
-- on a fresh file it creates the same schema the legacy app would.
-- The Migrator wraps this file in BEGIN IMMEDIATE ... COMMIT.

CREATE TABLE IF NOT EXISTS brands (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    prefix TEXT NOT NULL UNIQUE,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT,
    role TEXT NOT NULL CHECK (role IN (
        'COO','ECD','Traffic','PM','Producer','CD',
        'Copywriter','Designer','QA','Client',
        'AM','Developer','SEO','Social'
    )),
    color TEXT DEFAULT '#3b82f6',
    brand_id TEXT REFERENCES brands(id) ON DELETE SET NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS campaigns (
    id TEXT PRIMARY KEY,
    brand_id TEXT NOT NULL REFERENCES brands(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT DEFAULT 'active',
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS jobs (
    id TEXT PRIMARY KEY,
    job_number TEXT NOT NULL UNIQUE,
    campaign_id TEXT REFERENCES campaigns(id),
    title TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'Inbox' CHECK (status IN (
        'Inbox','Brief','To Do','In Progress','Today','This Week',
        'Waiting','On Hold','In Review',
        'Approved (Internal)','Approved (External)',
        'Done','Archived','Cancelled'
    )),
    creative_direction TEXT,
    brief_date TEXT,
    delivery_date TEXT,
    hours_estimate REAL,
    internal_approved_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    internal_approved_at TEXT,
    client_approved_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    client_approved_at TEXT,
    all_tasks_completed_at TEXT,
    client_feedback TEXT,
    client_feedback_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    client_feedback_at TEXT,
    client_feedback_status TEXT CHECK (
        client_feedback_status IS NULL OR
        client_feedback_status IN ('pending','actioned','dismissed')
    ),
    client_feedback_assigned_to TEXT REFERENCES users(id) ON DELETE SET NULL,
    client_feedback_assigned_role TEXT,
    client_feedback_actioned_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    client_feedback_actioned_at TEXT,
    internal_feedback TEXT,
    internal_feedback_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    internal_feedback_at TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS job_assignments (
    id TEXT PRIMARY KEY,
    job_id TEXT NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_on_job TEXT NOT NULL CHECK (role_on_job IN (
        'COO','ECD','Traffic','PM','Producer','CD',
        'Copywriter','Designer','QA','Client',
        'AM','Developer','SEO','Social'
    )),
    UNIQUE(job_id, role_on_job)
);

CREATE TABLE IF NOT EXISTS tasks (
    id TEXT PRIMARY KEY,
    job_id TEXT NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    type TEXT NOT NULL CHECK (type IN ('copy','media','qa','review-internal','review-client')),
    status TEXT NOT NULL DEFAULT 'Not Started' CHECK (status IN (
        'Not Started','Backlog','To Do','In Progress','Waiting','Done','On Hold','In Review'
    )),
    content TEXT,
    assigned_to TEXT REFERENCES users(id) ON DELETE SET NULL,
    character_count INTEGER,
    file_url TEXT,
    file_type TEXT CHECK (file_type IS NULL OR file_type IN ('image','video','document','audio')),
    internal_feedback TEXT,
    feedback_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    feedback_at TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    completed_at TEXT,
    completed_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS assets (
    id TEXT PRIMARY KEY,
    job_id TEXT NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    campaign_id TEXT REFERENCES campaigns(id),
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    template_id TEXT,
    status TEXT NOT NULL DEFAULT 'Inbox',
    assigned_to TEXT REFERENCES users(id) ON DELETE SET NULL,
    due_date TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS wiki_pages (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    content TEXT,
    template_id TEXT,
    parent_id TEXT REFERENCES wiki_pages(id) ON DELETE SET NULL,
    type TEXT,
    tags TEXT,
    created_by TEXT REFERENCES users(id) ON DELETE SET NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS wiki_page_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wiki_page_id TEXT NOT NULL REFERENCES wiki_pages(id) ON DELETE CASCADE,
    entity_type TEXT NOT NULL CHECK (entity_type IN ('job','project')),
    entity_id TEXT NOT NULL,
    UNIQUE(wiki_page_id, entity_type, entity_id)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id TEXT PRIMARY KEY,
    user_id TEXT REFERENCES users(id),
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS login_attempts (
    ip TEXT NOT NULL,
    attempted_at INTEGER NOT NULL
);

CREATE TRIGGER IF NOT EXISTS jobs_updated_at
AFTER UPDATE ON jobs FOR EACH ROW
BEGIN UPDATE jobs SET updated_at = datetime('now') WHERE id = NEW.id; END;

CREATE TRIGGER IF NOT EXISTS tasks_updated_at
AFTER UPDATE ON tasks FOR EACH ROW
BEGIN UPDATE tasks SET updated_at = datetime('now') WHERE id = NEW.id; END;

CREATE TRIGGER IF NOT EXISTS assets_updated_at
AFTER UPDATE ON assets FOR EACH ROW
BEGIN UPDATE assets SET updated_at = datetime('now') WHERE id = NEW.id; END;

CREATE TRIGGER IF NOT EXISTS wiki_pages_updated_at
AFTER UPDATE ON wiki_pages FOR EACH ROW
BEGIN UPDATE wiki_pages SET updated_at = datetime('now') WHERE id = NEW.id; END;

CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status);

CREATE INDEX IF NOT EXISTS idx_jobs_campaign ON jobs(campaign_id);

CREATE INDEX IF NOT EXISTS idx_job_assignments_user ON job_assignments(user_id);

CREATE INDEX IF NOT EXISTS idx_job_assignments_job ON job_assignments(job_id);

CREATE INDEX IF NOT EXISTS idx_tasks_job ON tasks(job_id);

CREATE INDEX IF NOT EXISTS idx_tasks_assigned ON tasks(assigned_to);

CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);

CREATE INDEX IF NOT EXISTS idx_assets_job ON assets(job_id);

CREATE INDEX IF NOT EXISTS idx_wiki_page_links_page ON wiki_page_links(wiki_page_id);

CREATE INDEX IF NOT EXISTS idx_audit_log_entity ON audit_log(entity_type, entity_id);

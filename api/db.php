<?php
// ============================================================================
// db.php -- SQLite3 connection, PRAGMAs, auto-create schema
// ============================================================================

// Database file location -- in data/ directory protected by .htaccess
define('DB_PATH', __DIR__ . '/../data/slash301pm.db');

/**
 * Get a SQLite3 database connection with all PRAGMAs set.
 * Auto-creates tables on first run.
 *
 * @return SQLite3
 */
function getDb(): SQLite3 {
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $dbDir = dirname(DB_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0750, true);
    }

    $isNew = !file_exists(DB_PATH);
    $db = new SQLite3(DB_PATH);

    // Required PRAGMAs (run on every connection)
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA cache_size = -16000');
    $db->exec('PRAGMA temp_store = MEMORY');

    if ($isNew) {
        createSchema($db);
    }

    return $db;
}

/**
 * Create all tables, triggers, and indexes.
 */
function createSchema(SQLite3 $db): void {
    $db->exec('BEGIN TRANSACTION');

    $db->exec("
        CREATE TABLE IF NOT EXISTS brands (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            prefix TEXT NOT NULL UNIQUE,
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS campaigns (
            id TEXT PRIMARY KEY,
            brand_id TEXT NOT NULL REFERENCES brands(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'active',
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS wiki_page_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            wiki_page_id TEXT NOT NULL REFERENCES wiki_pages(id) ON DELETE CASCADE,
            entity_type TEXT NOT NULL CHECK (entity_type IN ('job','project')),
            entity_id TEXT NOT NULL,
            UNIQUE(wiki_page_id, entity_type, entity_id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id TEXT PRIMARY KEY,
            user_id TEXT REFERENCES users(id),
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            old_value TEXT,
            new_value TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            ip TEXT NOT NULL,
            attempted_at INTEGER NOT NULL
        )
    ");

    // Triggers for updated_at
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS jobs_updated_at
        AFTER UPDATE ON jobs FOR EACH ROW
        BEGIN UPDATE jobs SET updated_at = datetime('now') WHERE id = NEW.id; END
    ");

    $db->exec("
        CREATE TRIGGER IF NOT EXISTS tasks_updated_at
        AFTER UPDATE ON tasks FOR EACH ROW
        BEGIN UPDATE tasks SET updated_at = datetime('now') WHERE id = NEW.id; END
    ");

    $db->exec("
        CREATE TRIGGER IF NOT EXISTS assets_updated_at
        AFTER UPDATE ON assets FOR EACH ROW
        BEGIN UPDATE assets SET updated_at = datetime('now') WHERE id = NEW.id; END
    ");

    $db->exec("
        CREATE TRIGGER IF NOT EXISTS wiki_pages_updated_at
        AFTER UPDATE ON wiki_pages FOR EACH ROW
        BEGIN UPDATE wiki_pages SET updated_at = datetime('now') WHERE id = NEW.id; END
    ");

    // Indexes (critical for performance)
    $db->exec('CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_jobs_campaign ON jobs(campaign_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_job_assignments_user ON job_assignments(user_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_job_assignments_job ON job_assignments(job_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_tasks_job ON tasks(job_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_tasks_assigned ON tasks(assigned_to)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_assets_job ON assets(job_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_wiki_page_links_page ON wiki_page_links(wiki_page_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_entity ON audit_log(entity_type, entity_id)');

    $db->exec('COMMIT');
}

/**
 * Send a JSON response and exit.
 */
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // Phase 3.4: XSS prevention via JSON_HEX_TAG|HEX_AMP flags
    // These encode < > & as unicode escapes in the JSON stream, preventing
    // script injection when JSON is embedded in HTML contexts.
    // React/JSX already handles HTML-escaping on render -- do NOT apply
    // htmlspecialchars() here or it will double-encode apostrophes etc.
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

/**
 * Send a JSON error response and exit.
 */
function jsonError(string $message, int $status = 400): void {
    jsonResponse(['error' => $message], $status);
}

/**
 * Generate a unique ID (UUID v4 style).
 */
function generateId(): string {
    return bin2hex(random_bytes(16));
}

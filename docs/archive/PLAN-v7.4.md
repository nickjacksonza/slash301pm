# Slash 301 PM - Project Plan

**Version:** 7.4 | **Updated:** February 19, 2026 | **Cache:** v=19
**Status:** Phases 1-3.4 Complete (63 bugs fixed) | **Next: Phase 4 (Stabilize)**

---

## Enhancement Summary

**Deepened on:** February 19, 2026
**Research agents used:** 12 (security-sentinel, architecture-strategist, data-integrity-guardian, performance-oracle, code-simplicity-reviewer, julik-frontend-races-reviewer, spec-flow-analyzer, ux-design-expert, product-delivery-lead, pattern-recognition-specialist, php-sqlite-best-practices-researcher, react-cdn-patterns-researcher)

### Key Improvements
1. **Security controls moved from Phase 4 to Phase 3** -- CSRF, XSS output encoding, session hardening, and input validation are now Phase 3 blocking requirements (not deferred polish)
2. **Phase 3 split into two deployments** (3A: data layer, 3B: auth+permissions) to reduce blast radius and enable independent validation
3. **Schema hardened** with 9 missing fields, 7 missing indexes, CHECK constraints, `updated_at` triggers, and `PRAGMA foreign_keys = ON`
4. **10 race conditions identified** for the localStorage-to-API migration, with concrete mitigation strategies
5. **36 spec gaps documented** with default assumptions -- critical questions flagged for resolution before implementation
6. **Deployment verification checklist** created for shared hosting FTP constraints

### New Considerations Discovered
- Shared hosting session hijacking risk (co-tenant attacks via shared `/tmp`) -- mitigated by custom session path or DB-backed sessions
- `unzipper.php` is an unauthenticated remote code execution vector -- must be deleted or auth-gated after each deploy
- Schema is missing `assets`, `wiki_pages`, and `scheduled_emails` tables that exist in the current data model
- The `clientFeedback*` field family (9 fields) used by the review workflow is absent from the planned schema
- Internal "Not Approved" rejection currently does nothing visible -- needs a defined behavior before Phase 3
- No transition from "Approved (External)" to "Done" exists -- workflow dead-ends
- "Brief" vs "Inbox" initial status discrepancy between plan and code

---

## Vision

Project management for **small creative agencies (5-20 people)** -- full workflow from brief to delivery with role-based permissions. Desktop-optimized, responsive.

---

## Architecture

**Stack:** React 18 CDN (`React.createElement`, no JSX/build step) | `useReducer` + localStorage | Custom CSS | FTP deploy to `projects.slash301.com/slash301pm/` with `?v=N` cache busting

**Backend (Phase 3):** PHP 8.4.18 + SQLite 3.34.1 on shared hosting. This is the production backend, not a stepping stone.

### Server Capabilities (probed Feb 19, 2026)

Host: `www28.cpt4.host-h.net` -- shared hosting, FTP deploy to `/public_html/projects.slash301.com/slash301pm/`

| Capability | Value |
|------------|-------|
| **PHP** | 8.4.18 (FPM-FCGI) on Apache/Linux |
| **SQLite3** | 3.34.1 -- write test passed |
| **Disk** | ~128 GB free |
| **Memory limit** | 256 MB |
| **Extensions** | curl, openssl, mbstring, sessions, hash, bcmath, gd, zip, json |

#### Research Insights: Shared Hosting Constraints

**Security ceiling:** On shared hosting, other tenants on `www28.cpt4.host-h.net` may be able to read files via symlink attacks or shared `/tmp`. Verify with the host that `open_basedir` is set per-user and `FollowSymLinks` is restricted. If not, application-level mitigations (custom session path, DB outside web root, file permissions 0600) are the only defense.

**Deployment risk:** FTP uploads are non-atomic. During upload, users may get a mix of old and new files. Mitigate by uploading protection files first (`.htaccess`), then backend, then frontend last. See Deployment Verification section.

**Performance baseline:** SQLite opens in sub-millisecond on local disk. PHP-FPM creates a fresh process per request -- no persistent connections, no connection pool needed. This is fine for 5-20 users.

### File Map
```
/
+-- index.html         # Entry point, CDN script tags (?v=N cache bust)
+-- styles.css         # Custom CSS design system
+-- scripts.js         # Dev/test utilities
+-- unzipper.php       # PHP deployment utility for FTP archive extraction
+-- src/
|   +-- app.js             # Root component, routing, tab counts, user switching
|   +-- constants.js       # ROLE_PERMISSIONS, statuses, tabs
|   +-- data.js            # dataReducer (UPDATE_TASK/JOB/DELETE_*), localStorage I/O
|   +-- utils.js           # generateId, asset name parser, formatDate, debounce
|   +-- test-helpers.js    # Workflow test helpers
|   +-- components/
|       +-- ui.js              # StatusBadge, KanbanCard, ErrorBoundary
|       +-- dashboard.js       # "My Work", "Ready to Schedule"
|       +-- work.js            # Unified Work tab (Projects/Jobs/Assets merged)
|       +-- capacity.js        # Workload, calendar, email scheduling
|       +-- panels.js          # DetailPanel, TaskList (passes editedBy/editedByRole)
|       +-- reviews.js         # Internal Review -- CD/ECD approve/reject
|       +-- reviews-unified.js # Sub-tab switcher (Internal/Client)
|       +-- operations.js      # ClientReviewTab, Operations Dashboard
|       +-- more-menu.js       # "More" dropdown (fixed-position)
|       +-- wiki.js            # Wiki knowledge base
|       +-- modals-brief.js    # Brief form with role validation
|       +-- modals-person.js   # Person CRUD, UserSelector
|       +-- views.js           # Table/Kanban views
```

#### Research Insights: Global Scope Management

**Pattern:** All 15 JS files share a global scope via `<script>` tags. ~80+ global constants/functions. The `scripts.js` concatenated bundle (6,806 lines) and the `src/` source files are a dual-source risk.

**Recommendations:**
- Wrap each source file in an IIFE to prevent namespace leaks: `(function() { /* file contents */ window.ComponentName = ComponentName; })();`
- Use `scripts.js` as the production deployment artifact (1 request vs 17)
- Automate concatenation with a shell script: `cat src/constants.js src/utils.js src/data.js ... > scripts.js`
- Add a dependency check in `app.js` that verifies critical components loaded before rendering

**Code duplication found:** `TableView`/`WorkTableView` and `KanbanBoard`/`WorkKanbanView` are near-identical (~600 lines duplicated). `getProject()`, `getJobTasks()`, `getPerson()` helpers are redefined in 3+ files. Consolidate during Phase 3.

### Navigation: 5 Tabs
1. **Dashboard** -- Role-based landing, "My Work", recent activity
2. **Work** -- Jobs by status, detail panels, "New Brief"
3. **Capacity** -- Workload, calendar (Traffic/PM/Producer)
4. **Reviews** -- Internal (CD/ECD) / Client (Client/Traffic/COO)
5. **More** -- People, Wiki, Operations

#### Research Insights: Information Architecture

**UX recommendation:** Consider restructuring "More" -- People, Wiki, and Operations serve very different purposes. Move People to a user menu dropdown, Wiki to a persistent sidebar icon, and Operations to a COO-only Dashboard variant. This eliminates the "junk drawer" tab.

**Client experience gap:** Client users see only Reviews. If no reviews are pending, they see "All Caught Up!" with nothing else. Consider adding a minimal read-only "Client Dashboard" showing their brand's job statuses.

### Capacity (`capacity.js`)

Workload calendar for Traffic, PM, and Producer roles. Shows team availability by person per week, identifies who's free, and allows task assignment. "Send by Email" button triggers a notification confirmation (currently simulated -- real email in Phase 5). Traffic uses this as the scheduling control tower: see who's overloaded, who has room, and assign jobs accordingly.

**Planned improvements:**
- Wire "Send by Email" to real PHP `mail()` or transactional email (Phase 5)
- Calendar export via iCal feed (Phase 5)
- Drag-and-drop task reassignment between people

#### Research Insights: Capacity

- The "Send by Email" button currently shows "Email scheduled for 9am" with no indication it is simulated. Add visual distinction (e.g., "(Demo)" label) until real email is wired in Phase 5.
- Capacity calculation uses `max(assets * 0.25h, 0.25h)` but ignores the `hoursEstimate` field from the brief form. Use `job.hoursEstimate` when available, fall back to formula.

### Wiki (`wiki.js`)

Knowledge base with collapsible sidebar navigation. Stores agency-level reference content (brand guidelines, process docs, templates). Available under the More tab. Frozen for now -- current implementation is sufficient for demos. No new Wiki work until users request it.

**Planned improvements:**
- Persist wiki content to SQLite alongside app state (Phase 3.2)
- Search within wiki articles
- Role-based edit permissions (managers can edit, creatives read-only)

#### Research Insights: Wiki Security

- Wiki pages store HTML content that may be rendered unsafely. If using `dangerouslySetInnerHTML` or `innerHTML`, this is an XSS vector. React's default string escaping protects content rendered via `React.createElement`, but wiki content specifically needs audit.

### Operations Dashboard (`operations.js`)

Agency-wide overview under the More tab. Shows stat cards (active jobs, people, review queue depth) and the Client Review management interface. Traffic and COO use this for high-level monitoring. Also contains the `ClientReviewTab` component with approve/reject/feedback actions.

**Planned improvements:**
- Live stats from SQLite queries instead of client-side counting (after Phase 3.2)
- Filterable by brand, date range, status
- Export operations summary (CSV or PDF)

#### Research Insights: Code Organization

- `operations.js` contains BOTH `OperationsDashboard` AND `ClientReviewTab` AND `canAccessOperations`/`canAccessClientReview` guard functions -- three distinct concerns in one file. `ClientReviewTab` logically belongs with reviews. Consider splitting during Phase 3.

### Roles (16, grouped into 6 categories)

**Executive**
| Role | Access |
|------|--------|
| COO | Everything, both review tabs, people management |
| ECD | All views, job editing, internal review, creative oversight |

**Client**
| Role | Access |
|------|--------|
| Client | Client Review only, own brand's jobs |

**Operations**
| Role | Access |
|------|--------|
| AM | Account management, client liaison, job oversight |
| PM | Jobs, capacity, internal review, brief creation |
| Traffic | Jobs, capacity, both review tabs (all jobs), scheduling |
| QA | Assigned items, quality checks |

**Creative**
| Role | Access |
|------|--------|
| CD | All views, own status, internal review (approve/reject) |
| Copywriter | Assigned items, task completion (copy) |
| Designer | Assigned items, task completion (media) |

**Development**
| Role | Access |
|------|--------|
| Developer | Assigned items, technical implementation |
| SEO | Assigned items, SEO audits and optimisation |

**Production**
| Role | Access |
|------|--------|
| Producer | Jobs, capacity, internal review, production oversight |
| Social | Assigned items, social content scheduling and publishing |

**Permission tiers** (for server-side checks in Phase 3.4):
- **Admin** -- COO, ECD: full read/write on all data
- **Manager** -- AM, PM, Traffic, Producer: read all, write on managed/assigned jobs
- **Creative** -- CD, Copywriter, Designer, QA, Developer, SEO, Social: read/write only where assigned
- **Client** -- Client: read own brand's jobs, submit review feedback only

*Currently 10 roles in code (COO, ECD, Traffic, PM, Producer, CD, Copywriter, Designer, QA, Client). New roles (AM, Developer, SEO, Social) to be added to `constants.js` ROLE_PERMISSIONS and seed data in Phase 3.*

#### Research Insights: Permission Model

**ECD tier inconsistency:** PLAN.md classifies ECD as "Admin" tier but `constants.js` gives ECD `level: 'manager'` with `canCreateJobs: false`. Decide before Phase 3.4: should ECD be Admin or Manager?

**"Managed" is undefined:** Manager tier says "write on managed/assigned jobs" but no `managed_by` field exists. Define: "managed" = user has an entry in `job_assignments` for that job, regardless of `role_on_job`.

**QA has no workflow gate:** QA is assigned to jobs but has no "reject"/"flag" action and is not a prerequisite for Internal Review. Either formalize QA as a blocking gate or document it as advisory.

**Guard function fragmentation:** `canAccessOperations`, `canAccessClientReview`, `canAccessJobReview` are defined in component files separately from centralized permissions in `constants.js`. Move all guard functions to `constants.js` before Phase 3.

---

## Review Workflow

### Status Flow
```
Brief -> In Progress -> [All Tasks Done] -> Internal Review -> Approved (Internal) -> Client Review -> Approved (External) -> Done
              ^                                  |                                     |
              +---- Rejected (with feedback) ----+                                     |
              +---- Client Feedback -----------------------------------------------+
```

### Auto-Routing (data.js UPDATE_TASK)
| Editor Role | All Done? | Routes To |
|-------------|-----------|-----------|
| Copywriter/Designer | Yes | Internal Review -- sets `allTasksCompletedAt` |
| CD/ECD | Yes | Client Review -- sets `Approved (Internal)` |
| Other | Yes | Stays In Progress (manual) |
| Any | No | Reverts to In Progress, clears approvals |

Payloads carry `editedBy`/`editedByRole` from `panels.js` -> consumed by `data.js`.

*Note: Auto-routing currently only handles Copywriter/Designer/CD/ECD. New roles (AM, Developer, SEO, Social, Producer) fall into the "Other" bucket. Brief form assignment fields also need updating. Both deferred to Phase 3.*

#### Research Insights: Workflow Gaps

**Critical gap 1 -- Internal rejection does nothing:** `handleReject()` in `reviews.js` sets `internalRejected: true` but does NOT change job status, does NOT reset tasks, and does NOT remove the job from the review queue. The job reappears identically. **Decision needed before Phase 3:** Should a bare rejection reset tasks (matching client rejection behavior) or require feedback?

**Critical gap 2 -- No path from "Approved (External)" to "Done":** No code or UI action transitions jobs to "Done." Jobs accumulate at "Approved (External)" indefinitely. **Add:** A "Mark as Done" button for Traffic/PM/COO on jobs with status "Approved (External)."

**Critical gap 3 -- No "Cancelled" status:** There is no way to cancel a job at any stage. Add "Cancelled" to `STATUSES` and the SQL CHECK constraint. Add "Cancel Job" action in DetailPanel for PM/Traffic/COO.

**Gap 4 -- "Brief" vs "Inbox" discrepancy:** Plan says initial status is "Brief" but the brief form creates jobs with status "Inbox." Reconcile before Phase 3.

**Gap 5 -- Internal rejection with feedback sets `completed: false` but NOT `status: 'In Progress'`:** The auto-routing logic checks `task.status === 'Done'`, not `task.completed`. So rejected tasks still pass the "all tasks done" check. Fix in Phase 3.

**Gap 6 -- No revision history:** Each rejection overwrites previous feedback. Add a `job_feedback` table or at minimum a `revision_count` field.

**Gap 7 -- New roles and auto-routing:** Developer/SEO/Social task completions should follow the "creative" routing path (same as Copywriter/Designer). AM should follow the "manager" bucket. Document before Phase 3.4.

### Queue Filters
- **Internal Review:** status in `[In Progress, Today, This Week, In Review]` AND `!internalApprovedBy` AND Copy+Media tasks Done
- **Client Review:** status in `[Approved (Internal), In Review]` -- Clients see own jobs, Traffic/COO see all

#### Research Insights: Race Conditions in Multi-User Review

**Race condition 1 -- Simultaneous last-task completion:** Two users mark the last two tasks Done simultaneously. Each reads the job state, sees one task pending, does not trigger the auto-transition. Both tasks end up Done but no status transition occurs. **Mitigation:** The auto-transition check must run inside the same SQLite transaction that marks the task Done, re-reading task states within that transaction.

**Race condition 2 -- Dual-state management divergence:** When the frontend dispatches to `useReducer` and calls the API in parallel, if the API returns 403 or 500, local state is updated but server state is not. **Mitigation:** Implement optimistic update + rollback pattern. Snapshot state before dispatch, roll back on API failure.

**Race condition 3 -- Session expiry mid-edit:** A user filling out a brief form may have the session expire. The `check_session` call returns 401 but unsaved form data is lost. **Mitigation:** On 401, save form state to `sessionStorage`, redirect to login, restore after re-auth.

---

## Brief Requirements

### Required Fields
Job number (auto `{CAMPAIGN}-{SEQ}`), Campaign, Brief/Delivery dates, Creative Direction, PM, Copywriter, Designer, CD. Client is optional (warns if missing).

### Asset Naming
`{JobNumber}-{Client}-{Campaign}-{AssetType}{Seq}-{Version}-{YYYYMMDD}`

#### Research Insights: Brief Form Gaps

- **No "Edit Brief" capability:** BriefModal is create-only. Fields set at creation (briefPdfUrl, serverLink, firstGoLiveDate, hoursEstimate) cannot be edited later.
- **No delivery date validation:** Bug #30 allows `delivery_date < brief_date`. Critical for Phase 3.
- **Duplicate job number risk:** `generateJobNumber()` uses `{projectCode}-{jobCount+1}`. Two simultaneous briefs could collide. The SQLite UNIQUE constraint catches this server-side, but add a retry mechanism.
- **Project code collision:** `getProjectCode("Summer Campaign")` = "SC" collides with `getProjectCode("Social Content")` = "SC". Use `brands.prefix` from DB in Phase 3.

---

## Completed Phases

| Phase | What | Bugs Fixed |
|-------|------|------------|
| 1 | Cleanup, dedup, document | -- |
| 2 | 5-tab nav, Dashboard, Work, Brief form, Capacity | -- |
| 2.5a | Static analysis (Opus 4 + 3-agent review) | 26 |
| 2.5c | 1st Playwright E2E | 11 |
| 2.5d | 2nd Playwright E2E regressions | 3 |
| 2.5e | 3rd Playwright E2E -- workflow routing | 6 |
| 2.5f | Demo bug fixes (workflow + Demo Script B) | 5 |
| 2.5g | UX audit -- Playwright visual review | 7 |
| 2.5h | Dashboard & filter fixes | 5 |

**Total: 63 bugs fixed across 8 phases.**

---

## Roadmap

### Phase 3 -- Make It Real

Merge former phases 2.5i (demo data), 2.5j (login), and 2.5k (SQLite backend) into a single "make the prototype real" phase. ~~One sprint, one deploy.~~ **Two deployments** to reduce blast radius and enable independent validation.

#### Research Insights: Phasing Strategy

**Product delivery recommendation:** Split Phase 3 into two deployments:

**Deployment 3A (Data Layer Foundation):**
- 3A.1: Demo Data
- 3A.2: SQLite + PHP API (no auth -- open access for testing)
- Deploy to staging only. Validate data layer works before adding auth.

**Deployment 3B (Auth + Permissions):**
- 3B.1: Login + Auth + CSRF + Session Hardening
- 3B.2: Server-Side Permissions + New Roles
- Deploy to production after staging validation.

This isolates "does the data layer work?" from "does auth work?" Debugging is vastly simpler when you are not troubleshooting both at once. Rollback for each deployment is independent.

#### 3.1 -- Demo Data (`data.js` seed data only)

Replace 3 generic brands with 10 hotel brands from DEMO.md. Replace seed people with 13 agency team + 20 client contacts. Create seed jobs matching Demo Script B (MERC-001, etc.). Update cache to v=16.

**Acceptance:** Demo Script B (16 steps) completes without improvisation.

**Additional acceptance criteria:**
- [x] All 10 hotel brands appear in client dropdown
- [x] All 13 agency team members appear in team roster
- [x] All 20 client contacts associated with correct brands
- [x] Seeded jobs span at least 3 brands and 5 team members (10 brands, 29 team members)
- [x] No placeholder text ("Brand 1", "Client A") remains

#### 3.2 -- SQLite + PHP API

Move all data from localStorage to SQLite via a thin PHP API with per-entity endpoints. The `.db` file lives **outside the web root** (or protected by `.htaccess`). Per-entity endpoints allow server-side permission checks to actually work (a JSON blob approach would let any logged-in user overwrite everything).

**API endpoints (all via `api.php?action=...`):**

| Endpoint | Method | What |
|----------|--------|------|
| `login` | POST | Validate username + password via `password_verify()`, start PHP session |
| `check_session` | GET | Return current user or 401 (for page reloads) |
| `logout` | POST | Destroy PHP session |
| `get_users` | GET | List all users (admin/manager only) |
| `add_user` | POST | Create user (admin only) |
| `get_brands` | GET | List brands |
| `get_jobs` | GET | List jobs (filtered by role -- clients see own brand, creatives see assigned) |
| `get_job` | GET | Single job with tasks + assignments (denormalized -- include tasks, assignments, campaign, brand inline) |
| `add_job` | POST | Create job from brief form (PM/Traffic/admin) |
| `update_job` | POST | Update job fields/status (role-checked) |
| `update_task` | POST | Update task status/content (assigned user or admin) |
| `approve_internal` | POST | **NEW** -- Enforce state machine: only valid from correct prerequisite status |
| `approve_client` | POST | **NEW** -- Enforce state machine server-side |
| `reject_with_feedback` | POST | **NEW** -- Reset tasks + record feedback in transaction |
| `batch` | POST | **NEW** -- Wrap multiple operations in a single SQLite transaction |
| `csrf_token` | GET | **NEW** -- Return CSRF token for frontend |

Additional endpoints added as needed. Start with these 16.

#### Research Insights: API Design

**Denormalize `get_job` response:** Return the full job payload with tasks, assignments (with person names), campaign, and brand inline. Avoid waterfall requests where the frontend calls `get_job`, then `get_tasks`, then `get_assignments`.

**Add workflow action endpoints:** `approve_internal`, `approve_client`, `reject_with_feedback` enforce valid state transitions server-side. Do not rely on generic `update_job` with arbitrary status values -- the server must validate that a job can only transition from "In Progress" to "Approved (Internal)", never skip steps.

**Add batch endpoint:** The `handleRejectWithFeedback` workflow dispatches 3 separate writes. Without batching, that is 3 network round-trips (150-400ms) replacing a single synchronous localStorage write (<5ms). `api.php?action=batch` wraps multiple operations in one SQLite transaction.

**Frontend integration:** Replace `loadFromStorage()` with fetches to `get_jobs`, `get_users`, `get_brands` on app init. Replace `saveToStorage()` dispatch side-effect with per-action API calls (`update_job`, `update_task`, etc.) in each component. The `useReducer` stays for local state management -- API calls happen alongside dispatches.

**Optimistic update pattern:** (1) Snapshot current state, (2) Apply update to reducer immediately, (3) Send API request, (4) On failure, dispatch `ROLLBACK` action with snapshot, show error toast.

**Schema:**

```sql
-- Required PRAGMAs (run on every connection in db.php)
PRAGMA journal_mode = WAL;           -- Concurrent reads during writes
PRAGMA busy_timeout = 5000;          -- Wait up to 5s instead of instant SQLITE_BUSY
PRAGMA synchronous = NORMAL;         -- Safe with WAL, better performance
PRAGMA foreign_keys = ON;            -- CRITICAL: SQLite does NOT enforce FKs by default
PRAGMA cache_size = -16000;          -- 16MB cache
PRAGMA temp_store = MEMORY;          -- Temp tables in memory

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

CREATE TABLE IF NOT EXISTS brands (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    prefix TEXT NOT NULL UNIQUE,
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
    client_feedback_status TEXT CHECK (client_feedback_status IS NULL OR client_feedback_status IN ('pending','actioned','dismissed')),
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

-- Triggers for updated_at
CREATE TRIGGER IF NOT EXISTS jobs_updated_at
AFTER UPDATE ON jobs FOR EACH ROW
BEGIN UPDATE jobs SET updated_at = datetime('now') WHERE id = NEW.id; END;

CREATE TRIGGER IF NOT EXISTS tasks_updated_at
AFTER UPDATE ON tasks FOR EACH ROW
BEGIN UPDATE tasks SET updated_at = datetime('now') WHERE id = NEW.id; END;

-- Indexes (critical for performance)
CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status);
CREATE INDEX IF NOT EXISTS idx_jobs_campaign ON jobs(campaign_id);
CREATE INDEX IF NOT EXISTS idx_job_assignments_user ON job_assignments(user_id);
CREATE INDEX IF NOT EXISTS idx_job_assignments_job ON job_assignments(job_id);
CREATE INDEX IF NOT EXISTS idx_tasks_job ON tasks(job_id);
CREATE INDEX IF NOT EXISTS idx_tasks_assigned ON tasks(assigned_to);
CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
```

#### Research Insights: Schema Changes from Original

| Change | Reason |
|--------|--------|
| Added `PRAGMA foreign_keys = ON` | SQLite does NOT enforce FKs by default. Without this, every constraint is decorative. |
| Added `PRAGMA journal_mode = WAL` | Required for concurrent reads during writes. Single most important performance optimization. |
| Added `PRAGMA busy_timeout = 5000` | Prevents instant `SQLITE_BUSY` errors. |
| Changed `users.brand` to `users.brand_id REFERENCES brands(id)` | Was a loose TEXT field with no FK. |
| Added `users.email` | Needed for capacity email feature. |
| Added `users.is_active` | Soft-delete instead of hard-delete for assigned users. |
| Added `jobs.description` | Present in current data model, missing from schema. |
| Added `jobs.hours_estimate` | Brief form captures this but schema didn't store it. |
| Added `jobs.client_feedback_*` fields (7 fields) | 9 feedback tracking fields used by review workflow were absent. |
| Added `jobs.internal_feedback_by/at` | Tracking who gave internal feedback. |
| Added `jobs.sort_order` | For list ordering in UI. |
| Added `tasks.character_count, file_url, file_type` | Used by review display, absent from schema. |
| Added `tasks.internal_feedback, feedback_by, feedback_at` | Written by review workflow, absent from schema. |
| Added `tasks.sort_order` | For task ordering in UI. |
| Added CHECK constraints on `jobs.status`, `tasks.status`, `tasks.type`, `job_assignments.role_on_job` | Prevents invalid values. |
| Added ON DELETE clauses on all FKs | Prevents orphaned records and constraint violations on user deletion. |
| Added `updated_at` triggers | `DEFAULT (datetime('now'))` only fires on INSERT, not UPDATE. |
| Added 7 indexes | Without indexes, all role-filtered queries do full table scans. |
| Added `assets` table | Present in current data model, completely missing from schema. |
| Added `audit_log` table | Required for review approval tracking and debugging. |
| Added `login_attempts` table | For rate limiting login attempts. |
| Added `Cancelled` to jobs status CHECK | No way to cancel a job existed. |
| Changed default job status to `Inbox` | Matches code behavior (brief form sets `Inbox`, not `Brief`). |

**File structure:**
```
api/
+-- api.php           # Router -- switch on ?action=, whitelist valid actions
+-- db.php            # SQLite3 connection, PRAGMAs, auto-create tables
+-- auth.php          # Session management, CSRF, rate limiting
+-- permissions.php   # authorize($userId, $action, $entityId) function
+-- seed.php          # One-time seed data insertion (delete after use)
+-- .htaccess         # Deny direct access to .db file
```

The `.db` file should live above the web root if the host allows it. If not, `.htaccess` with `Deny from all` on the file.

**Acceptance criteria for Phase 3.2:**
- [ ] All API endpoints return 200 for valid requests
- [ ] API returns 400 for malformed requests with error message
- [ ] Data written via API persists after page refresh
- [ ] Data round-trips correctly (write then read back)
- [ ] App does NOT fall back to localStorage
- [ ] `.db` file returns 403 on direct HTTP request
- [ ] `.db-wal` and `.db-shm` files also return 403/404
- [ ] API response times <200ms for single-record reads
- [ ] No PHP error messages visible in API responses

#### 3.3 -- Login + Auth

Replace the user-switcher dropdown with a login screen. Usernames are `firstname-lastname-role` (lowercase, hyphenated). Passwords hashed with `password_hash()` from day one -- no plain text, ever.

**Username format:** `generateUsername("Sarah Chen", "PM")` -> `sarah-chen-pm`

**Login flow:**
1. No active PHP session -> show login screen (username + password fields)
2. POST to `api.php?action=login` -> `password_verify()` against `users.password_hash`
3. On success: `session_regenerate_id(true)`, PHP session stores `user_id` + `role`, frontend loads app
4. Logout: destroy session, show login screen

**Registration:** COO-only or open (configurable). Fields: Name, Role (dropdown), Password. Username auto-generated and previewed.

**Demo mode toggle:** COO can enable a "Demo Mode" that restores the user-switcher for stakeholder presentations.

**Default password for seed users:** Hash of `Password123!` via `password_hash('Password123!', PASSWORD_DEFAULT)`.

#### Research Insights: Auth Security (BLOCKING -- Must Ship with Phase 3.3)

**Session hardening (required):**
```php
session_start([
    'name'                   => 'SLASH301PM_SID',
    'cookie_lifetime'        => 0,              // Session cookie
    'cookie_path'            => '/slash301pm/',
    'cookie_domain'          => 'projects.slash301.com',
    'cookie_secure'          => true,           // HTTPS only
    'cookie_httponly'         => true,           // No JavaScript access
    'cookie_samesite'        => 'Lax',          // CSRF mitigation layer
    'use_strict_mode'        => true,           // Reject uninitialized IDs
    'use_only_cookies'       => true,           // No session ID in URLs
    'sid_length'             => 48,
    'gc_maxlifetime'         => 3600,           // 1 hour timeout
]);
```

**Custom session path (required on shared hosting):**
On shared hosting, PHP sessions default to a shared `/tmp` directory readable by all tenants. Set a custom path:
```php
$session_dir = '/home/username/slash301pm_sessions';
ini_set('session.save_path', $session_dir);
```
Or use SQLite-backed sessions (eliminates filesystem session attacks entirely).

**CSRF protection (MOVED FROM PHASE 4 -- required in Phase 3.3):**
Generate token on login, return via `csrf_token` endpoint, frontend sends as `X-CSRF-Token` header on every POST. Validate with `hash_equals()`.

**Login rate limiting (required):**
Track failed attempts in `login_attempts` table. Block IP after 10 failures in 15 minutes. Return 429 with `Retry-After` header.

**Timing-safe login:** Always call `password_verify()` even if username not found (prevents user enumeration via response time).

**Password requirements:** Minimum 12 characters, maximum 128 characters.

**Security headers (add to every API response):**
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self' https://unpkg.com; style-src 'self' 'unsafe-inline'
```

**Disable error display:**
```php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
```

**HTTPS enforcement:**
```php
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
```

**Files to create/modify:**

| File | Change |
|------|--------|
| `api/api.php` | Login endpoint with `password_verify()`, CSRF validation, action whitelist |
| `api/auth.php` | **New** -- Session config, CSRF helper, rate limiting, `validate_session()` |
| `api/db.php` | SQLite connection, PRAGMAs, schema creation |
| `api/permissions.php` | **New** -- `authorize($userId, $action, $entityId)` |
| `api/seed.php` | Seed users + state with hashed passwords |
| `src/utils.js` | Add `generateUsername(name, role)` |
| `src/data.js` | Replace `loadFromStorage`/`saveToStorage` with API calls |
| `src/components/login.js` | **New** -- Login + Registration components |
| `src/app.js` | Auth gate, logout button, demo mode toggle |
| `styles.css` | Login screen styles |
| `index.html` | Add `login.js` script tag |

**Acceptance criteria for Phase 3.3:** ✅ ALL PASS (verified Feb 19, 2026)
- [x] Login screen appears on first load (no session)
- [x] Valid credentials grant access; invalid show generic "Invalid credentials" error
- [x] Session persists across page refresh (`check_session` returns 200)
- [x] Logout destroys session and returns to login screen
- [x] Session cookie has `Secure`, `HttpOnly`, `SameSite=Lax` flags
- [x] Session ID regenerated on login (`session_regenerate_id(true)` at auth.php:264)
- [x] 10+ rapid failed logins blocked (Cloudflare edge rate-limit + PHP app-layer)
- [x] Passwords are bcrypt hashed (never plaintext in DB, logs, or network tab)
- [x] CSRF token required on all POST endpoints (missing/wrong token → 403)
- [x] No PHP warnings/errors visible in any API response

#### 3.4 -- Server-Side Permission Checks + New Roles

Add the 4 new roles (AM, Developer, SEO, Social) to `constants.js` ROLE_PERMISSIONS, seed data, and the brief form's role dropdown. Update auto-routing logic in `data.js` to handle new role types.

API validates role on every write endpoint. Check `$_SESSION['user_role']` in PHP before executing writes:
- **Admin** (COO, ECD): all operations
- **Manager** (AM, PM, Traffic, Producer): create/update jobs they manage
- **Creative** (CD, Copywriter, Designer, QA, Developer, SEO, Social): update only tasks assigned to them
- **Client**: read own brand's jobs, submit review feedback only

This fixes: **#14 (no server-side permission enforcement)**.

#### Research Insights: Permission Implementation

**Object-level authorization (IDOR prevention):** Every data-access function must check authorization at the object level. A Client requesting `get_job&id=5` must only succeed if job 5 belongs to their brand. A Creative requesting `update_task&id=7` must only succeed if they are assigned to that task. Implement a single `authorize($userId, $action, $entityId)` function called at the top of every write endpoint.

**Explicit column selection:** Never use `SELECT *` for user queries. Always exclude `password_hash` from API responses.

**Input validation (MOVED FROM PHASE 4 -- required in Phase 3.4):** Use prepared statements for every query. Validate input types, lengths, and enum values server-side. Create a `validate_input($rules, $data)` helper.

**XSS output encoding (MOVED FROM PHASE 4 -- required in Phase 3.4):** Apply `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` on all user-supplied content in API responses, or use `JSON_HEX_TAG | JSON_HEX_AMP` flags on `json_encode()`.

**Acceptance criteria for Phase 3.4:** ✅ ALL PASS (verified Feb 19, 2026)
- [x] All 14 roles defined in database (AM, Developer, SEO, Social seed users added)
- [x] Unauthenticated write attempts return 401
- [x] Unauthorized write attempts return 403 (Client → create_job: 403, update_task: 403)
- [x] Role escalation via API parameter manipulation returns 403 (query params ignored, session used)
- [x] Client can only see own brand's jobs (server-enforced in get_jobs + IDOR 404 on get_job)
- [x] Creative can only edit tasks assigned to them (non-assigned task → 403)
- [x] `get_users` never returns `password_hash` field (explicit column SELECT)
- [x] All input validated server-side (type, length, enum — invalid → 400)
- [x] All output HTML-encoded (`sanitizeOutput()` + `JSON_HEX_TAG|JSON_HEX_AMP`)

**Exit criteria for Phase 3:** App loads from SQLite. Login works with hashed passwords. localStorage no longer used for app state. Demo Script B passes. `.db` file not accessible from browser. Server rejects unauthorized writes. All 14 roles functional. CSRF tokens active on all POST endpoints. XSS output encoding in place. Security headers set.

---

### Phase 4 -- Stabilize

Bug fixes and validation that depend on the PHP backend being in place.

- [ ] Fix: WF-08 -- "Action & Submit" inline editing
- [ ] Fix: #22 -- Fragile detail panel type detection
- [ ] Fix: #28-29 -- Input validation (~~server-side, in PHP~~ remaining client-side validation)
- [ ] Fix: #30 -- Date range validation (`delivery_date >= brief_date`)
- [ ] Fix: E2E-11 -- Task-level status filter on Work tab
- [ ] Fix: #32 -- Debounce rapid saves (300ms trailing)
- [ ] ~~XSS prevention~~ (moved to Phase 3.4)
- [ ] Extract `getAssignmentForRole(job, role)` utility -- replaces duplicated assignment lookups
- [ ] Add error handling for failed API calls (toast + retry)
- [ ] ~~CSRF token on all POST endpoints~~ (moved to Phase 3.3)
- [ ] Consolidate duplicated Table/Kanban view components (~600 lines)
- [ ] Centralize helper functions (getProject, getJobTasks, getPerson) into utils.js
- [ ] Replace `alert()`/`confirm()` with toast/notification component (14 occurrences)
- [ ] Move inline styles to CSS classes (33 occurrences)
- [ ] Add ErrorBoundary wrappers around all major tabs (currently only wraps WorkTab)
- [ ] Add loading states/skeleton screens for async API calls
- [ ] Add confirmation dialog before destructive actions in Client Review

---

### Phase 5 -- Whatever Users Ask For

No speculative features. Build what users request after Phase 4 ships. Possible directions based on the architecture:

- ~~Normalize SQLite schema (per-entity tables) when the JSON blob approach hits limits~~ (already per-entity)
- ~~Per-entity API endpoints when specific queries are needed~~ (already per-entity)
- Email notifications (PHP `mail()` or a transactional service)
- Reporting / analytics
- Mobile-friendly layout
- Multi-user conflict handling (if concurrent editing becomes a problem)
- Migration to PostgreSQL or managed hosting (if SQLite file locking becomes an issue at scale)
- Wiki persistence to SQLite (wiki_pages table)
- Scheduled emails persistence to SQLite

---

## Deferred Bugs

| # | Bug | Sev | Phase |
|---|-----|-----|-------|
| #14 | No server-side permission enforcement | Crit | 3.4 |
| WF-08 | "Action & Submit" skips inline editing | High | 4 |
| #28-29 | Missing full form validation | High | 4 |
| #30 | No date range validation | Med | 4 |
| #32 | Rapid dispatch race condition | Med | 4 |
| #22 | Fragile detail panel type detection | Low | 4 |
| E2E-11 | Task-level status filter on Work tab | Low | 4 |

---

## Known Limitations

| Issue | Severity | Fix Phase |
|-------|----------|-----------|
| No authentication | Critical | 3.3 |
| Client-side permissions only | Critical | 3.4 |
| localStorage (single-user, 5-10 MB limit) | High | 3.2 |
| No input validation | High | ~~4~~ 3.4 |
| No ES modules (global scope, script tags) | Medium | -- (works fine for this scale) |
| Side effects in reducer (auto-routing logic) | Medium | Extract when it causes problems |
| XSS on user content fields | High | ~~4~~ 3.4 |
| No CSRF protection | Critical | ~~4~~ 3.3 |
| Shared hosting session hijacking risk | High | 3.3 (custom session path) |
| `unzipper.php` unauthenticated | Critical | 3.2 (delete or auth-gate) |

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Permission bypass via DevTools | Critical | Phase 3.4: server-side checks |
| localStorage quota at scale | High | Phase 3.2: SQLite |
| XSS via review feedback content | High | ~~Phase 4~~ Phase 3.4: output encoding |
| SQLite file locking under load | Low | WAL mode + busy_timeout(5000). Not a problem at 5-20 users. |
| Rapid state corruption | Medium | Phase 4: debounced saves |
| CSRF on all POST endpoints | Critical | Phase 3.3: synchronizer token pattern |
| Shared hosting co-tenant attacks | High | Custom session path, DB outside web root, file permissions 0600 |
| `.db` file downloadable from browser | Critical | `.htaccess` deny + store outside web root |
| `unzipper.php` remote code execution | Critical | Delete after each deploy or add auth gate |
| Session fixation | High | `session_regenerate_id(true)` on login |
| FTP partial upload leaves inconsistent state | Medium | Upload order: protection files first, frontend last |
| Optimistic update divergence (API fails, local state updated) | Medium | Rollback pattern with state snapshot |
| Concurrent last-task completion race condition | Medium | Auto-transition inside same SQLite transaction |

---

## Deployment Verification Checklist (Phase 3)

### Before ANY Phase 3 Deploy
- [ ] Download full backup of `/slash301pm/` via FTP
- [ ] Export localStorage data as JSON backup (browser console)
- [ ] Record baseline counts (projects, jobs, tasks, users)
- [ ] Upload `_probe.php` to verify PHP 8.4, SQLite3, display_errors=Off, writable directory
- [ ] DELETE `_probe.php` after verification

### Phase 3.2 Deploy Order (FTP Partial-State Mitigation)
1. Create `/data/` directory
2. Upload `/data/.htaccess` (DENY ALL) -- protection goes up FIRST
3. **TEST:** Browse to `/data/` -- must return 403
4. Upload empty `/data/index.html` (secondary protection)
5. Upload `/data/slash301pm.db`
6. **TEST:** Browse to `/data/slash301pm.db` -- must return 403
7. Upload `/api/` directory and all PHP files
8. **TEST:** Hit a read-only API endpoint
9. Upload updated frontend JS files (LAST)

### Post-Deploy Security Checks (Execute Within 5 Minutes)
- [ ] `https://projects.slash301.com/slash301pm/data/slash301pm.db` returns 403
- [ ] `https://projects.slash301.com/slash301pm/data/slash301pm.db-wal` returns 403/404
- [ ] `https://projects.slash301.com/slash301pm/data/` returns 403 (not directory listing)
- [ ] API returns JSON, not PHP errors
- [ ] `_verify_data.php`: integrity_check = "ok", row counts match baseline, foreign_keys_enabled = 1

### Post-Deploy Cleanup (REQUIRED)
- [ ] DELETE `_probe.php`
- [ ] DELETE `_verify_data.php`
- [ ] DELETE `_session_probe.php`
- [ ] DELETE `_auth_verify.php`
- [ ] DELETE or auth-gate `unzipper.php`
- [ ] DELETE `seed.php` (or rename to prevent re-execution)

### Rollback Plan
- **Phase 3.1 (demo data):** Re-upload backed-up JS files. No data risk.
- **Phase 3.2 (SQLite):** Re-upload backed-up frontend JS files. Old frontend reads localStorage. SQLite/API files are inert.
- **Phase 3.3 (auth):** Re-upload pre-auth frontend and API files. Auth disabled.
- **Phase 3.4 (permissions):** Re-upload pre-permissions API files. Permissions disabled.

---

## Critical Questions (Resolve Before Phase 3)

These questions were surfaced by spec flow analysis and review agents. Each has a default assumption that will be used if no decision is made.

| # | Question | Default Assumption |
|---|----------|--------------------|
| Q1 | What should "Not Approved" (no feedback) do in Internal Review? | Reset tasks + status to "In Progress" (match client rejection) |
| Q2 | How does a job go from "Approved (External)" to "Done"? | "Mark as Done" button for Traffic/PM/COO |
| Q3 | Should CSRF ship with Phase 3.3 or Phase 4? | **Phase 3.3** (decided -- moved up) |
| Q4 | What happens to unsaved form data when session expires? | Save to sessionStorage, restore after re-login |
| Q5 | How should localStorage -> SQLite migration handle existing data? | One-time PHP endpoint accepts JSON blob, inserts into tables |
| Q6 | What constitutes "managed" jobs for the Manager tier? | User has entry in `job_assignments` for that job |
| Q7 | Can multiple people hold the same role on one job? | No (keep UNIQUE constraint). Revisit in Phase 5. |
| Q8 | Should initial job status be "Brief" or "Inbox"? | "Inbox" (matches code). Remove "Brief" from flow diagram. |
| Q9 | Should QA task be auto-created when QA assigned? | Yes -- auto-create QA task, add as prerequisite for Internal Review |
| Q10 | How should new roles (AM, Dev, SEO, Social) interact with auto-routing? | Dev/SEO/Social = creative routing. AM = manager bucket. |

---

*v7.0-deepened -- Enhanced from v6.1 via 12-agent parallel research. Security controls (CSRF, XSS, session hardening, input validation) moved from Phase 4 to Phase 3. Phase 3 split into two deployments. Schema hardened with missing fields, indexes, constraints, triggers. 36 spec gaps documented. Deployment verification checklist added. 10 race conditions identified. All original content preserved.*

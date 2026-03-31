# Slash 301 PM - Project Plan (Deepened)

**Version:** 5.0-deepened | **Updated:** February 19, 2026 | **Cache:** v=15
**Status:** Phases 1–2.5h Complete (63 bugs fixed) / Phase 2.5b Queued
**Enhancement:** 14-agent parallel research synthesis — architecture, security, performance, UX, product, spec flows, frontend design, patterns, simplicity, race conditions, migration, Supabase, real-time, data integrity

---

## Enhancement Summary

### What Changed
This plan was deepened by 14 specialized research and review agents analyzing every section in parallel. The original structure is preserved; new content is marked with **[DEEPENED]** tags. Key enhancements:

1. **Phase resequencing** — Vite migration moved before DataService (agents unanimous: build tooling unblocks everything else)
2. **New Phase 2.5i** — Demo data alignment micro-sprint (seed data mismatch is a demo blocker)
3. **Phase 3 split into sub-phases** — 3a (Vite), 3b (Auth), 3c (Database), 3d (RLS) for incremental delivery
4. **Detailed Supabase schema** — PostgreSQL tables, RLS policies for all 10 roles, junction table for assignments
5. **Race condition strategy** — Priority-based debounce for localStorage, optimistic UI patterns for Supabase migration
6. **47 workflow permutations documented** — 8 critical clarification questions raised by spec flow analysis
7. **Performance quick wins** — Debounced saves, `useMemo`/`useCallback` targets, lazy loading strategy
8. **Risk register expanded** — 5 new risks identified (stale closure in dispatch, XSS in review content, reducer side effects blocking migration)

### Conflicting Advice (Resolved)
| Topic | Agent A | Agent B | Resolution |
|-------|---------|---------|------------|
| DataService abstraction | Architecture: "Refine it (Repository Pattern)" | Simplicity: "Delete it (YAGNI)" | **Keep it minimal** — thin adapter interface (`load`/`save`/`subscribe`) without Repository/Strategy overhead. Needed for Supabase swap. |
| Number of roles | Simplicity: "Reduce to 3-4" | Product/Demo: "All 10 required for demo" | **Keep 10 roles** — demo script B requires all 10. Implement as permission *tiers* (Admin/Manager/User/Client) with role labels, reducing permission matrix complexity. |
| CSS framework | Frontend Design: "Keep custom CSS" | UX: "Consider component library" | **Keep custom CSS** — it works, it's small, and the design tokens are already partially implemented. Add missing spacing/typography tokens. |
| Wiki feature | Simplicity: "Defer entirely" | Product: "Useful for demos" | **Keep but freeze** — no new Wiki work until Phase 4. Current implementation is sufficient for demos. |

---

## Vision

Project management for **small creative agencies (5-20 people)** — full workflow from brief to delivery with role-based permissions. Desktop-optimized, responsive.

**[DEEPENED] Product Definition:**
- **MVP** = end of Phase 3 (auth + persistence + server permissions). Everything before is prototype.
- **Target demo audience:** Agency leadership + client-side guests (per DEMO.md)
- **Success metric:** Complete Demo Script B (16 steps, all 10 roles) without manual workarounds
- **Non-goal (Phases 1-3):** Mobile-first, offline support, real-time collaboration. Desktop demo fidelity is the priority.

---

## Architecture

**Stack:** React 18 CDN (`React.createElement`, no JSX/build step) | `useReducer` + localStorage | Custom CSS | FTP deploy to `projects.slash301.com/slash301pm/` with `?v=N` cache busting

**Permissions:** Role-based, UI-only enforcement (no auth — critical gap, Phase 3)

### File Map
```
/
├── index.html         # Entry point, CDN script tags (?v=N cache bust), no build step
├── styles.css         # Custom CSS design system (variables, components, responsive)
├── scripts.js         # Dev/test utilities: testPMWorkflow(), role test runners
├── unzipper.php       # PHP deployment utility for FTP archive extraction
├── src/
│   ├── app.js             # Root component, routing, tab counts, user switching
│   ├── constants.js       # ROLE_PERMISSIONS, statuses, tabs
│   ├── data.js            # dataReducer (UPDATE_TASK/JOB/DELETE_*), localStorage I/O
│   ├── utils.js           # generateId, asset name parser, formatDate, debounce
│   ├── test-helpers.js    # Workflow test helpers (overlaps with scripts.js)
│   └── components/
│       ├── ui.js              # StatusBadge, KanbanCard, ErrorBoundary
│       ├── dashboard.js       # "My Work", "Ready to Schedule"
│       ├── work.js            # Unified Work tab (Projects/Jobs/Assets merged)
│       ├── capacity.js        # Workload, calendar, email scheduling
│       ├── panels.js          # DetailPanel, TaskList (passes editedBy/editedByRole)
│       ├── reviews.js         # Internal Review — CD/ECD approve/reject
│       ├── reviews-unified.js # Sub-tab switcher (Internal/Client), useEffect reset on user switch
│       ├── operations.js      # ClientReviewTab, canAccessClientReview/JobReview, Operations Dashboard
│       ├── more-menu.js       # "More" dropdown (fixed-position, JS-calculated coords)
│       ├── wiki.js            # Wiki knowledge base with collapsible sidebar
│       ├── modals-brief.js    # Brief form with role validation
│       ├── modals-person.js   # Person CRUD, UserSelector
│       └── views.js           # Table/Kanban views
```

**[DEEPENED] Architecture Observations:**

**Anti-patterns identified (Pattern Recognition agent):**
- **Side effects in reducer** — `data.js` UPDATE_TASK handler contains auto-routing logic (status transitions, timestamp writes). This is the single biggest blocker for Supabase migration. Must be extracted to middleware or a separate `WorkflowEngine` before Phase 3.
- **Props drilling 4-5 levels deep** — `editedBy`/`editedByRole` flows from `app.js` → `panels.js` → `TaskList` → dispatch payload. React Context or composition pattern needed.
- **14 instances of duplicated assignment logic** — `job.assignments.Copywriter`, `job.assignments.Designer`, etc. scattered across `dashboard.js`, `work.js`, `reviews.js`, `operations.js`. Extract to shared `getAssignmentForRole(job, role)` utility.
- **Monolithic scripts.js** — 6,588 lines concatenated from `src/`. No tree-shaking, no code splitting. Vite migration eliminates this.

**Recommended decomposition (Architecture agent):**
```
src/
├── domain/
│   ├── workflow-engine.js    # Extract auto-routing from reducer
│   ├── permissions.js        # Extract from constants.js, make testable
│   └── assignment-resolver.js # Shared assignment lookup
├── data/
│   ├── data-service.js       # Thin adapter: load/save/subscribe
│   ├── local-adapter.js      # localStorage implementation
│   └── supabase-adapter.js   # Phase 3 replacement
└── components/ (existing)
```

### Navigation: 5 Tabs
1. **Dashboard** — Role-based landing, "My Work", recent activity
2. **Work** — Jobs by status, detail panels, "New Brief"
3. **Capacity** — Workload, calendar (Traffic/PM only)
4. **Reviews** — Internal (CD/ECD) / Client (Client/Traffic/COO)
5. **More** — People, Wiki, Operations

### Roles (10 in code)

| Role | Level | Access |
|------|-------|--------|
| COO | Admin | Everything, both review tabs |
| Traffic | Manager | Jobs, capacity, both review tabs (all jobs) |
| PM | Manager | Jobs, capacity, internal review |
| Producer | Manager | Jobs, capacity, internal review (separate from PM) |
| ECD | Manager | All views, job editing, internal review |
| CD | User+ | All views, own status, internal review (approve/reject) |
| Copywriter | User | Assigned items, task completion |
| Designer | User | Assigned items, task completion |
| QA | User | Assigned items, quality checks (no task template yet — see DB-04) |
| Client | Minimal | Client Review only, own assigned jobs |

**[DEEPENED] Permission Tier Model (recommended for Phase 3 RLS):**

| Tier | Roles | DB Policy |
|------|-------|-----------|
| `admin` | COO | `SELECT *` / `UPDATE *` on all tables |
| `manager` | Traffic, PM, Producer, ECD | `SELECT *` on jobs/tasks, `UPDATE` on assigned + managed jobs |
| `creative` | CD, Copywriter, Designer, QA | `SELECT`/`UPDATE` only where `user_id` in `job_assignments` |
| `client` | Client | `SELECT` only where `brand_id` matches their `client_profiles.brand_id` |

This reduces 10 separate RLS policies to 4 tier-based policies with role-specific refinements.

---

## Review Workflow

### Status Flow
```
Brief → In Progress → [All Tasks Done] → Internal Review → Approved (Internal) → Client Review → Approved (External) → Done
              ↑                                  |                                     |
              └──── Rejected (with feedback) ────┘                                     |
              └──── Client Feedback ───────────────────────────────────────────────────┘
```

### Auto-Routing (data.js UPDATE_TASK)
| Editor Role | All Done? | Routes To |
|-------------|-----------|-----------|
| Copywriter/Designer | Yes | Internal Review — keeps `In Progress`, sets `allTasksCompletedAt` |
| CD/ECD | Yes | Client Review — sets `Approved (Internal)`, auto-approves internally |
| Other | Yes | Stays In Progress (manual) |
| Any | No | Reverts to In Progress, clears approvals |

Payloads carry `editedBy`/`editedByRole` from `panels.js` → consumed by `data.js`.

### Queue Filters
- **Internal Review:** status in `[In Progress, Today, This Week, In Review]` AND `!internalApprovedBy` AND Copy+Media tasks Done
- **Client Review:** status in `[Approved (Internal), In Review]` — Clients see own jobs, Traffic/COO see all

**[DEEPENED] Workflow Permutation Analysis (Spec Flow agent):**

47 distinct flow permutations identified across the review workflow. Critical paths:

| # | Permutation | Current Handling | Gap |
|---|-------------|------------------|-----|
| 1 | CD rejects → Copywriter revises → CD approves → Client rejects | Handled | Task reset on client rejection uses same logic as internal rejection — may need different feedback labels |
| 2 | ECD overrides CD rejection (approves anyway) | Not handled | No precedence logic between CD and ECD approvals |
| 3 | Client non-response after 7 days | Not handled | No escalation or reminder mechanism |
| 4 | Both Copy and Media rejected, only one revised | Partially handled | Job stays in "In Progress" but no indicator of which task needs attention |
| 5 | QA finds issue after internal approval | Not handled | No "QA Reject" flow — QA can only view, not reject |
| 6 | PM reassigns Copywriter mid-task | Not handled | Task ownership doesn't transfer; original assignee still sees it |
| 7 | Multiple revision rounds (3+ rejections) | Handled | But no revision counter — demo may show confusing history |
| 8 | Job cancelled after client review | Not handled | No "Cancelled" status in the flow |

**8 Critical Questions Requiring Clarification:**
1. Can a CD reject a job that the ECD has already approved?
2. What happens when a client doesn't respond within the delivery deadline?
3. Should QA have a formal reject/flag action, or is verbal feedback sufficient?
4. Can a PM cancel a job at any stage? What happens to in-progress tasks?
5. If a Copywriter is reassigned mid-job, does the new Copywriter see the old copy?
6. Should there be a maximum revision count before escalation?
7. Does "Done" mean "Approved (External)" or is there a separate delivery/archive step?
8. Can Traffic override a client rejection and mark as "Done" anyway?

---

## Brief Requirements

### Required Fields
Job number (auto `{CAMPAIGN}-{SEQ}`), Campaign, Brief/Delivery dates, Creative Direction, PM, Copywriter, Designer, CD. Client is optional (warns if missing).

### Asset Naming
`{JobNumber}-{Client}-{Campaign}-{AssetType}{Seq}-{Version}-{YYYYMMDD}`
Types: Hero1-2, Tactical1-4, Organic1-2, Competition, Comp-Winners, Wrapup
Sizes: 1x1, 4x5, 16x9, 9x16

**[DEEPENED] Brief Validation Gaps (Data Integrity agent):**
- No date range validation — delivery date can precede brief date
- No duplicate job number detection — same `{CAMPAIGN}-{SEQ}` could be generated if campaigns share prefixes
- Client field is optional but the review workflow requires a client for Client Review routing — jobs without a client will get stuck at "Approved (Internal)" with no path to "Approved (External)"
- No character limits on Creative Direction field — could overflow UI in review cards

---

## Known Limitations

| Issue | Severity | Fix Phase |
|-------|----------|-----------|
| No authentication | Critical | 3 |
| Client-side permissions only | Critical | 3 (RLS) |
| localStorage 5-10MB limit | High | 3 (PostgreSQL) |
| No full input validation | High | 2.5b/3 |
| No ES modules (global scope, script tags) | Medium | 3 (Vite) |
| Rapid dispatch race condition | Medium | 2.5b |

**[DEEPENED] Additional Limitations Identified:**

| Issue | Severity | Fix Phase | Source |
|-------|----------|-----------|--------|
| Side effects in reducer block Supabase migration | High | 2.5b | Pattern Recognition |
| Synchronous `saveToStorage()` blocks UI on large state | High | 2.5b | Performance |
| No XSS protection on review feedback/copy content fields | High | 3 | Security |
| Stale closure in `useReducer` dispatch (rapid clicks) | Medium | 2.5b | Race Conditions |
| No error recovery if localStorage write fails (quota exceeded) | Medium | 2.5b | Data Integrity |
| Seed data doesn't match demo hotel brands | Medium | 2.5i | Product |
| No loading/skeleton states during data operations | Low | 3 | UX |
| Props drilling 4-5 levels deep | Low | 2.5b/3 | Pattern Recognition |

---

## Roadmap

### Completed
| Phase | What | Bugs Fixed |
|-------|------|------------|
| 1 | Cleanup, dedup, document | — |
| 2 | 5-tab nav, Dashboard, Work, Brief form, Capacity | — |
| 2.5a | Static analysis (Opus 4 + 3-agent review) | 26 |
| 2.5c | 1st Playwright E2E | 11 |
| 2.5d | 2nd Playwright E2E regressions | 3 |
| 2.5e | 3rd Playwright E2E — workflow routing | 6 |
| 2.5f | Demo bug fixes (workflow + Demo Script B) | 5 |
| 2.5g | UX audit — Playwright visual review | 7 |
| 2.5h | Dashboard & filter fixes | 5 |

### Phase 2.5f — Demo Bug Fixes (Complete)

Found during 10-scene E2E workflow test (Feb 19) and 16-scene Demo Script B run (Feb 19).

#### Fixed (5)

| # | Bug | Fix |
|---|-----|-----|
| WF-09 | Client feedback not visible on task detail | Surfaced `clientFeedback` in DetailPanel "Feedback" banner — shows both Client and Internal feedback labels |
| WF-10 | Dashboard "My Work" count vs visible cards mismatch | Count now matches visible cards — excluded statuses not rendered are also excluded from the header count |
| WF-11 | Reviews badge stale after user switch | Added `useEffect` dependency on `currentUser.id` to force re-render on user switch |
| WF-12 | No confirmation on "Send by Email" | Added confirmation div — "Email scheduled for 9am" message appears for 3 seconds |
| DB-05 | Client sees jobs from multiple brands | Client Review filters by `job.assignments.Client === currentUser.id` plus brand-based scoping |

#### Deferred to Phase 2.5b+ (6)

| # | Bug | Severity | Reason |
|---|-----|----------|--------|
| WF-07 | Feedback doesn't uncheck tasks | **High** | Requires workflow state machine refactor — task reset on feedback needs careful re-routing logic |
| WF-08 | "Action & Submit" skips inline editing | **High** | Needs inline editor component in review screen — significant UI work |
| DB-01 | COO Dashboard shows 0 jobs | **High** | Dashboard filter logic needs COO to see all brands — blocked on role-based scoping refactor |
| DB-02 | Producer Dashboard scoped to assigned jobs only | **High** | Same root cause as DB-01 — Dashboard `getMyJobs` needs role-aware expansion |
| DB-03 | Work tab "My Work" filter broken for Copywriter/Designer | **High** | Filter matches on person name but field names vary (copywriter vs designer) — needs field mapping |
| DB-04 | QA role cannot see unassigned jobs | **Medium** | Brief form has no QA assignment field; requires form schema change |

### Phase 2.5g — UX Audit (Complete)

17-screenshot Playwright visual audit (Feb 19). Focused on polish issues visible during demos.

#### Fixed (7)

| # | Issue | Fix | Files |
|---|-------|-----|-------|
| UX-01 | Wiki sidebar shows raw `&#9654;` HTML entity | Replaced HTML entity with Unicode `\u25B6` / `\u25BC` triangle characters | `wiki.js` |
| UX-02 | Operations "1 people" grammar error | Added singular/plural logic: "1 person" vs "N people" | `operations.js` |
| UX-03 | Operations "Client Feedback" label → "Client Review" | Changed heading label to match tab name for consistency | `operations.js` |
| UX-04 | Duplicate "Sarah Chen" in Work tab team avatars | Fixed Traffic role person ID from `'p1'` (Sarah) to `'p9'` (correct Traffic person) in seed data | `data.js` |
| UX-06 | Client Review header dark navy, Internal Review sage green | Changed Client Review gradient to sage green `var(--accent-primary)` matching Internal Review | `styles.css` |
| UX-07 | Operations stat card emoji render as empty boxes | Replaced SMP emoji with BMP geometric symbols for universal font support | `operations.js`, `styles.css` |
| UX-09 | "More" dropdown clipped by `overflow: hidden` ancestor | Changed dropdown from `position: absolute` to `position: fixed` with JS `getBoundingClientRect()` positioning | `more-menu.js`, `styles.css` |

### Phase 2.5h — Dashboard & Filter Fixes (Complete)

Low-hanging-fruit sprint targeting deferred bugs from 2.5f. Scoped via 3-agent parallel analysis (Feb 19).

#### Fixed (5)

| # | Bug | Fix | Files |
|---|-----|-----|-------|
| DB-01 + DB-02 | COO/Producer Dashboard shows 0 jobs | Added `permissions.canViewAll` conditional — admin/manager roles see all active jobs, others see assigned only. Added explicit Producer permissions entry. | `dashboard.js`, `constants.js` |
| DB-03 | Work tab "My Work" filter broken for Copywriter/Designer | Changed `filterMode` default from `'all'` to permission-based — non-admin roles default to `'mine'` | `work.js` |
| E2E-08 | CD sees all jobs in Internal Review queue | Added CD-specific filter — CDs only see jobs where `job.assignments.CD === currentUser.id` | `reviews.js` |
| WF-07 | Client feedback rejection doesn't uncheck tasks | Added UPDATE_TASK dispatches in both `handleReject` and `handleRejectWithFeedback` — resets Copy/Media task `status` from `'Done'` to `'In Progress'` | `operations.js` |
| DB-04 | QA role not visible in brief form | Added `'QA'` to `KEY_ROLES` array so QA appears in the primary team assignments section | `modals-brief.js` |

---

### Phase 2.5i — Demo Data Alignment (NEW)

**[DEEPENED] Rationale (Product Delivery agent):**
DEMO.md describes 10 hotel brands, 13 agency team members, and 20 client contacts. Current seed data has 3 generic companies ("Acme Corp", "TechStart", "Ralph Wiggum Inc") with non-matching personnel. This is a **demo blocker** — Demo Script B cannot be performed as written.

**Scope:** Data-only change in `data.js` seed data. No logic changes.

- [ ] Replace 3 generic brands with 10 hotel brands from DEMO.md
- [ ] Replace seed people with 13 agency team members + 20 client contacts from DEMO.md
- [ ] Create seed jobs matching Demo Script B expectations (MERC-001, etc.)
- [ ] Ensure job statuses span the workflow (some In Progress, some In Review, one Approved External)
- [ ] Verify Demo Script B runs end-to-end with new seed data (Playwright)
- [ ] Update cache version to v=16

**Acceptance criteria:** Demo Script B (16 steps) completes without improvisation. All user switches show correct scoped data.

**Estimated effort:** Small (1-2 hours). No risk to existing logic.

---

### Phase 2.5b — Data Abstraction & Bug Fixes

**[DEEPENED] Expanded scope based on agent findings:**

#### 2.5b.1 — Extract Workflow Engine (prerequisite for everything)
- [ ] Extract auto-routing logic from `data.js` reducer into `domain/workflow-engine.js`
- [ ] Reducer becomes pure state updates only (no side effects)
- [ ] Workflow engine called as middleware: `dispatch → reducer → workflowEngine → side effects`
- [ ] Test: All existing E2E workflow tests pass unchanged

**Why first:** Every agent flagged reducer side effects as the #1 migration blocker. Supabase triggers/functions cannot replicate in-reducer side effects. Extracting them makes the reducer portable.

#### 2.5b.2 — DataService Adapter (thin, not Repository Pattern)
- [ ] `DataService` interface: `load()`, `save(state)`, `subscribe(callback)`
- [ ] `LocalStorageAdapter` implements DataService — wraps current `saveToStorage()`/`loadFromStorage()`
- [ ] Debounced save: 300ms trailing debounce with immediate flush on `beforeunload`
- [ ] Error handling: catch `QuotaExceededError`, show user warning, prevent silent data loss
- [ ] Wire into `app.js` — replace direct localStorage calls

**Why thin:** Simplicity agent correctly flagged that a full Repository Pattern with Adapters/Strategy is over-engineering for this stage. A thin interface is sufficient to swap localStorage for Supabase later.

#### 2.5b.3 — Race Condition Fixes
- [ ] Debounce `saveToStorage()` — 300ms trailing with priority levels:
  - **Immediate** (0ms): Task status changes, review approvals (user expects instant feedback)
  - **Normal** (300ms): Field edits, copy content, media paths
  - **Low** (1000ms): Filter changes, view preferences
- [ ] Guard against stale closure in rapid dispatch — use `useRef` for latest state in dispatch callbacks
- [ ] Add `requestAnimationFrame` guard for batch DOM updates after rapid state changes

#### 2.5b.4 — Remaining Bug Fixes
- [ ] Fix: WF-08 — "Action & Submit" inline editing (add inline editor component to review cards)
- [ ] Fix: #22 — Fragile detail panel type detection (use explicit `panel.type` field)
- [ ] Fix: #32 — Rapid dispatch race condition (covered by 2.5b.3)
- [ ] Fix: E2E-11 — Task-level status filter on Work tab
- [ ] Fix: #24 — Build system docs (document the `src/` → `scripts.js` concatenation process)

#### 2.5b.5 — Shared Utilities
- [ ] Extract `getAssignmentForRole(job, role)` — replaces 14 duplicated assignment lookups
- [ ] Extract `getJobsForUser(jobs, user, permissions)` — replaces scattered filter logic in dashboard/work/reviews

**Exit criteria:** All E2E tests pass. Demo Script B still works. `saveToStorage` is debounced. Reducer has no side effects.

---

### Phase 3 — Backend (Split into Sub-Phases)

**[DEEPENED] Phase 3 is too large as a single unit. Split into 4 sub-phases for incremental delivery:**

#### Phase 3a — Vite Migration

**Why first:** Every agent agreed — build tooling must come before backend integration. You cannot add Supabase SDK, environment variables, or ES module imports without a build system.

- [ ] Initialize Vite project: `npm create vite@latest -- --template react`
- [ ] Move `src/` files to Vite structure with ES module `import`/`export`
- [ ] Replace CDN React with npm React 18
- [ ] Convert `React.createElement` calls to JSX (automated via codemod or find-replace)
- [ ] Configure Vite for production build: code splitting, minification, source maps
- [ ] Set up `vite.config.js` with environment variable handling (`.env` files)
- [ ] Update deployment: FTP of `dist/` folder instead of monolithic `scripts.js`
- [ ] Route-based code splitting (lazy load Reviews, Capacity, Wiki, Operations)

**Key decisions:**
- Hosting: Stay on current FTP host for now. Cloudflare Pages recommended for Phase 4+ (free, global CDN, preview deployments).
- JSX conversion: Can be automated — `React.createElement('div', {className: 'foo'}, children)` → `<div className="foo">{children}</div>`

**Exit criteria:** `npm run dev` serves the app locally. `npm run build` produces optimized bundle. All E2E tests pass against built output.

#### Phase 3b — Authentication

- [ ] Install Supabase JS client (`@supabase/supabase-js`)
- [ ] Configure Supabase project: create project, get `SUPABASE_URL` and `SUPABASE_ANON_KEY`
- [ ] Implement email/password auth: sign up, sign in, sign out, password reset
- [ ] Create `profiles` table linked to `auth.users` — stores role, display name
- [ ] Replace user-switcher dropdown with real login flow
- [ ] Add "Demo Mode" toggle — preserves current user-switcher for demos without real auth
- [ ] Store JWT in httpOnly cookie (not localStorage) via Supabase auth helpers
- [ ] Add auth state listener — redirect to login on session expiry

**Supabase Auth configuration:**
```sql
-- Custom access token hook to embed role in JWT
CREATE OR REPLACE FUNCTION public.custom_access_token_hook(event jsonb)
RETURNS jsonb LANGUAGE plpgsql AS $$
DECLARE
  user_role text;
BEGIN
  SELECT role INTO user_role FROM public.profiles WHERE id = (event->>'user_id')::uuid;
  event := jsonb_set(event, '{claims,user_role}', to_jsonb(user_role));
  RETURN event;
END;
$$;
```

**Exit criteria:** Users can sign in with email/password. JWT contains role claim. Demo mode still works for stakeholder presentations.

#### Phase 3c — Database Migration

- [ ] Design PostgreSQL schema (see **Database Schema** section below)
- [ ] Create Supabase migrations for all tables
- [ ] Write seed data migration (matching Phase 2.5i hotel brand data)
- [ ] Replace `DataService.LocalStorageAdapter` with `DataService.SupabaseAdapter`
- [ ] Implement optimistic UI: update local state immediately, sync to Supabase, rollback on error
- [ ] Add loading states (skeleton screens) for initial data fetch
- [ ] Handle offline gracefully: queue writes, sync on reconnect

**Exit criteria:** App reads/writes from Supabase PostgreSQL. localStorage is no longer used for state. Offline queue works for brief periods.

#### Phase 3d — Row Level Security

- [ ] Enable RLS on all tables (`ALTER TABLE ... ENABLE ROW LEVEL SECURITY`)
- [ ] Default-deny: no access without explicit policy
- [ ] Implement 4-tier policies (see **RLS Policies** section below)
- [ ] Client isolation: clients see only their brand's jobs
- [ ] Test: attempt cross-brand data access as Client role — must fail
- [ ] Add security headers: CSP, X-Frame-Options, Strict-Transport-Security
- [ ] Fix: #14 (server-side permission enforcement — now handled by RLS)
- [ ] Fix: #28-30 (validation — add PostgreSQL CHECK constraints + Supabase edge function validation)

**Exit criteria:** All 10 roles tested against RLS policies. No cross-brand data leakage. DevTools cannot bypass permissions.

---

### **[DEEPENED] Database Schema (Phase 3c Reference)**

```sql
-- Core tables
CREATE TABLE brands (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL UNIQUE,          -- "The Meridian Collection"
  prefix TEXT NOT NULL UNIQUE,        -- "MERC"
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE profiles (
  id UUID PRIMARY KEY REFERENCES auth.users(id),
  display_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN (
    'COO','Traffic','PM','Producer','ECD','CD',
    'Copywriter','Designer','QA','Client'
  )),
  brand_id UUID REFERENCES brands(id),  -- NULL for agency staff, set for clients
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE campaigns (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  brand_id UUID NOT NULL REFERENCES brands(id),
  name TEXT NOT NULL,                    -- "Grand Opening London"
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE jobs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_number TEXT NOT NULL UNIQUE,       -- "MERC-001"
  campaign_id UUID NOT NULL REFERENCES campaigns(id),
  title TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'Brief' CHECK (status IN (
    'Brief','Inbox','Today','This Week','In Progress',
    'In Review','Approved (Internal)','Approved (External)',
    'On Hold','Done','Cancelled'
  )),
  creative_direction TEXT,
  brief_date DATE,
  delivery_date DATE CHECK (delivery_date >= brief_date),
  internal_approved_by UUID REFERENCES profiles(id),
  internal_approved_at TIMESTAMPTZ,
  client_approved_by UUID REFERENCES profiles(id),
  client_approved_at TIMESTAMPTZ,
  all_tasks_completed_at TIMESTAMPTZ,
  client_feedback TEXT,
  internal_feedback TEXT,
  created_by UUID REFERENCES profiles(id),
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);

-- Junction table for assignments (NOT JSONB — enables RLS filtering)
CREATE TABLE job_assignments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  profile_id UUID NOT NULL REFERENCES profiles(id),
  role_on_job TEXT NOT NULL CHECK (role_on_job IN (
    'PM','Traffic','Copywriter','Designer','CD','ECD','QA','Client'
  )),
  assigned_at TIMESTAMPTZ DEFAULT now(),
  UNIQUE(job_id, role_on_job)  -- one person per role per job
);

CREATE TABLE tasks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  type TEXT NOT NULL CHECK (type IN ('Copy','Media','QA')),
  status TEXT NOT NULL DEFAULT 'Not Started' CHECK (status IN (
    'Not Started','In Progress','Done'
  )),
  content TEXT,                          -- copy text or media URL
  assigned_to UUID REFERENCES profiles(id),
  completed_at TIMESTAMPTZ,
  completed_by UUID REFERENCES profiles(id),
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);

-- Audit trail (append-only, never delete)
CREATE TABLE audit_log (
  id BIGSERIAL PRIMARY KEY,
  table_name TEXT NOT NULL,
  record_id UUID NOT NULL,
  action TEXT NOT NULL CHECK (action IN ('INSERT','UPDATE','DELETE')),
  old_data JSONB,
  new_data JSONB,
  changed_by UUID REFERENCES profiles(id),
  changed_at TIMESTAMPTZ DEFAULT now()
);

-- Indexes
CREATE INDEX idx_jobs_status ON jobs(status);
CREATE INDEX idx_jobs_campaign ON jobs(campaign_id);
CREATE INDEX idx_job_assignments_profile ON job_assignments(profile_id);
CREATE INDEX idx_job_assignments_job ON job_assignments(job_id);
CREATE INDEX idx_tasks_job ON tasks(job_id);
CREATE INDEX idx_tasks_assigned ON tasks(assigned_to);
CREATE INDEX idx_audit_record ON audit_log(table_name, record_id);
```

**Key design decisions (Data Integrity agent):**
- **Junction table for assignments** instead of JSONB — enables RLS to filter "show me only jobs where I'm assigned" via a simple JOIN, rather than parsing JSONB in every policy
- **Audit trail** via append-only `audit_log` — triggered by PostgreSQL `AFTER INSERT/UPDATE/DELETE` triggers, not application code
- **`delivery_date >= brief_date`** CHECK constraint — prevents the validation gap identified in Brief Requirements
- **`job_number` UNIQUE constraint** — prevents duplicate job numbers
- **No `job_count` on campaigns** — compute dynamically with `COUNT(*)` to avoid stale counters

---

### **[DEEPENED] RLS Policies (Phase 3d Reference)**

```sql
-- Enable RLS on all tables
ALTER TABLE brands ENABLE ROW LEVEL SECURITY;
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE jobs ENABLE ROW LEVEL SECURITY;
ALTER TABLE job_assignments ENABLE ROW LEVEL SECURITY;
ALTER TABLE tasks ENABLE ROW LEVEL SECURITY;

-- Helper function: get current user's role from JWT
CREATE OR REPLACE FUNCTION auth.user_role()
RETURNS TEXT LANGUAGE sql STABLE AS $$
  SELECT coalesce(
    current_setting('request.jwt.claims', true)::jsonb->>'user_role',
    'anonymous'
  );
$$;

-- Helper function: check if user is assigned to a job
CREATE OR REPLACE FUNCTION public.is_assigned_to_job(job_uuid UUID)
RETURNS BOOLEAN LANGUAGE sql STABLE SECURITY DEFINER AS $$
  SELECT EXISTS (
    SELECT 1 FROM job_assignments
    WHERE job_id = job_uuid AND profile_id = auth.uid()
  );
$$;

-- TIER 1: Admin (COO) — full access
CREATE POLICY "admin_full_access" ON jobs
  FOR ALL USING (auth.user_role() = 'COO');

-- TIER 2: Manager (Traffic, PM, Producer, ECD) — see all, edit managed
CREATE POLICY "manager_select_all" ON jobs
  FOR SELECT USING (auth.user_role() IN ('Traffic','PM','Producer','ECD'));

CREATE POLICY "manager_update_assigned" ON jobs
  FOR UPDATE USING (
    auth.user_role() IN ('Traffic','PM','Producer','ECD')
    AND public.is_assigned_to_job(id)
  );

-- TIER 3: Creative (CD, Copywriter, Designer, QA) — assigned only
CREATE POLICY "creative_assigned_only" ON jobs
  FOR SELECT USING (
    auth.user_role() IN ('CD','Copywriter','Designer','QA')
    AND public.is_assigned_to_job(id)
  );

-- TIER 4: Client — brand-scoped only
CREATE POLICY "client_brand_scoped" ON jobs
  FOR SELECT USING (
    auth.user_role() = 'Client'
    AND campaign_id IN (
      SELECT c.id FROM campaigns c
      JOIN brands b ON c.brand_id = b.id
      WHERE b.id = (SELECT brand_id FROM profiles WHERE id = auth.uid())
    )
  );

-- Task policies follow same tier pattern via job_id JOIN
CREATE POLICY "task_via_job_access" ON tasks
  FOR ALL USING (
    EXISTS (
      SELECT 1 FROM jobs WHERE jobs.id = tasks.job_id
      -- This leverages the job-level RLS policies
    )
  );
```

---

### Phase 4 — Multi-User

- [ ] Supabase DataService replaces localStorage
- [ ] Real-time sync (filtered WebSockets, optimistic UI)
- [ ] Field-level conflict resolution
- [ ] Client portal with scoped tokens, email rate limiting

**[DEEPENED] Real-Time Sync Strategy (Real-Time Sync agent):**

**Supabase Realtime configuration:**
```javascript
// Subscribe to job changes with RLS-filtered channel
const channel = supabase
  .channel('job-changes')
  .on('postgres_changes', {
    event: '*',
    schema: 'public',
    table: 'jobs',
    // RLS automatically filters — clients only receive their brand's events
  }, (payload) => {
    handleRealtimeUpdate(payload);
  })
  .subscribe();
```

**Conflict Resolution — Last Write Wins (LWW) with field granularity:**
- Each field change carries a timestamp
- On conflict: latest timestamp wins at the field level (not record level)
- Exception: status transitions use a state machine — cannot go backwards (e.g., "Approved" cannot be overwritten by "In Progress" from a stale client)
- Show "This job was updated by [Name]" toast when a conflict is resolved

**Optimistic UI pattern:**
1. User action → update local state immediately (instant feedback)
2. Send mutation to Supabase
3. On success: no-op (local state already correct)
4. On failure: rollback local state, show error toast
5. On conflict: merge with server state using LWW

**Client Portal:**
- Separate URL: `projects.slash301.com/slash301pm/client/`
- Scoped JWT with `brand_id` claim — cannot access agency-side routes
- Read-only except for Client Review actions (Approve/Reject/Feedback)
- Email-based magic link login (no password for clients)
- Rate limit: max 10 feedback submissions per hour per client

### Phase 5 — Scale
- [ ] Virtual scrolling, DB aggregation
- [ ] Reporting/analytics, integrations (Slack, email, calendar)

**[DEEPENED] Performance Targets (Performance agent):**

| Metric | Current (localStorage) | Phase 3 Target | Phase 5 Target |
|--------|----------------------|----------------|----------------|
| Initial load | ~200ms (cached) | <1s (Supabase fetch) | <500ms (cached + prefetch) |
| Tab switch | ~50ms | <200ms (with RLS query) | <100ms (cached) |
| Save operation | ~5ms (sync localStorage) | <500ms (Supabase round-trip) | <100ms (optimistic) |
| Max jobs | ~200 (5MB localStorage) | 10,000+ (PostgreSQL) | 100,000+ (virtual scroll) |
| Bundle size | 6,588 lines (no splitting) | <200KB initial (code split) | <150KB (tree-shaken) |

**Phase 5 specifics:**
- Virtual scrolling for Work tab when >50 jobs visible (use `react-window`)
- Aggregation queries for Dashboard stats (don't fetch all jobs to count them)
- Background job processing for email notifications (Supabase Edge Functions)
- Calendar integration via iCal feed (read-only export of capacity data)

---

## Deferred Bugs

| # | Bug | Sev | Phase |
|---|-----|-----|-------|
| #14 | No server-side permission enforcement | Crit | 3d |
| WF-08 | "Action & Submit" skips inline editing | High | 2.5b |
| #28-29 | Missing full form validation | High | 3c |
| #30 | No date range validation | Med | 3c |
| E2E-15 | localStorage schema migration | Med | 3c |
| #32 | Rapid dispatch race condition | Med | 2.5b |
| #22 | Fragile detail panel type detection | Low | 2.5b |
| #24 | No build system docs | Low | 2.5b |
| E2E-11 | Task-level status filter on Work tab | Low | 2.5b |

**[DEEPENED] Bug phase assignments updated** — #14 moved to 3d (RLS), #28-30 and E2E-15 moved to 3c (database constraints + migration).

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| localStorage quota at scale | High | Phase 3c: PostgreSQL |
| Permission bypass via DevTools | Critical | Phase 3d: RLS |
| Concurrent edit conflicts | High | Phase 4: LWW conflict resolution |
| No ES modules (global scope) | Medium | Phase 3a: Vite |
| Rapid state corruption | High | Phase 2.5b: debounce + priority saves |

**[DEEPENED] New Risks Identified:**

| Risk | Impact | Mitigation | Source |
|------|--------|------------|--------|
| Reducer side effects prevent clean Supabase migration | High | Phase 2.5b: extract WorkflowEngine | Pattern Recognition |
| XSS via review feedback/copy content (rendered as innerHTML) | High | Phase 3: sanitize all user content with DOMPurify | Security |
| Stale closure in `useReducer` during rapid clicks | Medium | Phase 2.5b: `useRef` for latest state | Race Conditions |
| Seed data mismatch breaks demo credibility | Medium | Phase 2.5i: replace with hotel brand data | Product |
| Vite migration breaks existing E2E tests | Medium | Phase 3a: run E2E suite against dev server before + after | Architecture |
| Supabase cold start latency on free tier | Low | Phase 3: use Supabase Pro for demo, or pre-warm with health check | Performance |
| Client magic link emails flagged as spam | Low | Phase 4: use custom domain for email sending | Security |

---

## **[DEEPENED] UX Recommendations (for future phases)**

From UX Design and Frontend Design agents. Not blocking — improvements for polish.

| Category | Recommendation | Phase | Effort |
|----------|---------------|-------|--------|
| Feedback | Toast notifications instead of inline confirmation divs | 3a | Small |
| Loading | Skeleton screens during Supabase data fetches | 3c | Small |
| Reviews | Side-by-side layout for copy + media in review cards | 3a | Medium |
| Navigation | Badge counts on tab headers (e.g., "Reviews (3)") | Already exists | — |
| Accessibility | Add `aria-live` regions for status changes | 3a | Small |
| Accessibility | Keyboard navigation for review approve/reject actions | 3a | Small |
| Typography | Add spacing tokens to CSS: `--space-xs` through `--space-xl` | 3a | Small |
| Color | Status badges need icons alongside color for colorblind users | 3a | Small |
| Mobile | Bottom navigation bar for tablet/mobile (Phase 4+) | 4 | Medium |
| Empty states | Show helpful messages when Work tab or Reviews are empty | 2.5b | Small |

---

## **[DEEPENED] Recommended Phase Sequence**

Based on all agent inputs, the recommended execution order:

```
Current → 2.5i (demo data) → 2.5b (abstraction + bugs) → 3a (Vite) → 3b (Auth) → 3c (Database) → 3d (RLS) → 4 → 5
```

**Rationale for resequencing:**
1. **2.5i before 2.5b** — Demo data is a 1-2 hour sprint with zero risk. Gets the demo working immediately.
2. **2.5b before 3a** — Extracting the workflow engine and debouncing saves is prerequisite knowledge for how the Supabase adapter will work.
3. **3a (Vite) before 3b (Auth)** — Supabase SDK requires ES modules. Can't `import { createClient } from '@supabase/supabase-js'` without a build system.
4. **3b (Auth) before 3c (Database)** — Need authenticated users before RLS policies can reference `auth.uid()`.
5. **3c (Database) before 3d (RLS)** — Tables must exist before policies can be applied.

---

*v5.0-deepened — Enhanced by 14-agent parallel research (architecture, security, performance, UX, product, spec flows, frontend design, patterns, simplicity, race conditions, migration, Supabase docs, real-time, data integrity). Original plan preserved. New sections marked [DEEPENED]. 47 workflow permutations analyzed. PostgreSQL schema and RLS policies drafted. Phase 3 split into 4 sub-phases. Phase 2.5i added for demo data alignment. Recommended sequence: 2.5i → 2.5b → 3a → 3b → 3c → 3d → 4 → 5.*

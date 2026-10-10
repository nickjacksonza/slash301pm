# Slash 301 PM - Project Plan

**Version:** 4.6 | **Updated:** February 19, 2026 | **Cache:** v=15
**Status:** Phases 1–2.5e Complete (46 bugs fixed) / Phase 2.5f Demo Fixes (5 fixed) / Phase 2.5g UX Audit (7 fixed) / **Phase 2.5h Dashboard & Filter Fixes (5 fixed)** / Phase 2.5b Queued

---

## Vision

Project management for **small creative agencies (5-20 people)** — full workflow from brief to delivery with role-based permissions. Desktop-optimized, responsive.

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

---

## Brief Requirements

### Required Fields
Job number (auto `{CAMPAIGN}-{SEQ}`), Campaign, Brief/Delivery dates, Creative Direction, PM, Copywriter, Designer, CD. Client is optional (warns if missing).

### Asset Naming
`{JobNumber}-{Client}-{Campaign}-{AssetType}{Seq}-{Version}-{YYYYMMDD}`
Types: Hero1-2, Tactical1-4, Organic1-2, Competition, Comp-Winners, Wrapup
Sizes: 1x1, 4x5, 16x9, 9x16

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
| UX-07 | Operations stat card emoji render as empty boxes | Replaced SMP emoji (👥📊💼🕒) with BMP geometric symbols (○◎◈◔) for universal font support | `operations.js`, `styles.css` |
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

### Phase 2.5b — Data Abstraction
- [ ] DataService interface (`load`, `save`, `subscribe`)
- [ ] Wrap localStorage in DataService
- [ ] Fix: #22 (detail panel type), #24 (build docs), #32 (dispatch race), E2E-11
- [ ] Prepare for backend swap

### Phase 3 — Backend
- [ ] Supabase: Auth (email/password), PostgreSQL, RLS (default-deny)
- [ ] Vite + ES modules migration
- [ ] Security headers (CSP, HTTPS-only, JWT in cookies)
- [ ] Fix: #14 (server permissions), #28-30 (validation), E2E-15 (schema migration)

### Phase 4 — Multi-User
- [ ] Supabase DataService replaces localStorage
- [ ] Real-time sync (filtered WebSockets, optimistic UI)
- [ ] Field-level conflict resolution
- [ ] Client portal with scoped tokens, email rate limiting

### Phase 5 — Scale
- [ ] Virtual scrolling, DB aggregation
- [ ] Reporting/analytics, integrations (Slack, email, calendar)

---

## Deferred Bugs

| # | Bug | Sev | Phase |
|---|-----|-----|-------|
| #14 | No server-side permission enforcement | Crit | 3 |
| WF-08 | "Action & Submit" skips inline editing | High | 2.5b |
| #28-29 | Missing full form validation | High | 3 |
| #30 | No date range validation | Med | 3 |
| E2E-15 | localStorage schema migration | Med | 3 |
| #32 | Rapid dispatch race condition | Med | 2.5b |
| #22 | Fragile detail panel type detection | Low | 2.5b |
| #24 | No build system docs | Low | 2.5b |
| E2E-11 | Task-level status filter on Work tab | Low | 2.5b |

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| localStorage quota at scale | High | Phase 3: PostgreSQL |
| Permission bypass via DevTools | Critical | Phase 3: RLS |
| Concurrent edit conflicts | High | Phase 4: conflict resolution |
| No ES modules (global scope) | Medium | Phase 3: Vite |
| Rapid state corruption | High | Phase 2.5b: debounce |

---

*v4.6 — 63 bugs resolved across 2.5a/c/d/e/f/g/h. Phase 2.5h sprint: fixed 5 deferred bugs (DB-01/02 COO/Producer dashboard, DB-03 Work tab filter, E2E-08 CD review scope, WF-07 task reset on rejection, DB-04 QA in brief form). Deferred table reduced from 15 → 9. Cache: v=15. Next: Phase 2.5b (Data Abstraction + 5 remaining deferred bugs)*

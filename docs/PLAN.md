# Slash 301 PM: review and Datastar rebuild plan (AM first, beta-ready)

## Context
The tool was last worked on substantially in February 2026. It tracks agency work as Brand (client) → Campaign → Job, with the **Brief at the centre of every step**, and Assets requested from a Designer and Copywriter team. You want to:
- review everything,
- rebuild the UI on Datastar so the backend can later be ported to Go,
- keep the Airtable feel and the Kanban,
- build the **Account Manager (AM)** role first,
- reach a state you can beta-test in your daily work.

**Decisions already made**
| Topic | Decision |
|---|---|
| Backend | Stays PHP on Xneelo, structured so a Go port is mechanical |
| Data | Existing live data is **migrated**, not reseeded |
| Styling | Tailwind plus DatastarUI |
| Demo mode | Stays as is during the build. Turning it off and resetting passwords is a **hard beta gate** you flip by hand |
| Old React app | Moves to `/legacy/` until each area reaches parity |
| Brief route | AM → **Traffic**, who assigns the CD and the creatives |
| Brief edits after send | Versioned, with the team notified. Versions have three parts, major.minor.patch (e.g. 3.2.3) |
| Brief fields | Title, campaign, dates, team and creative direction, plus a **deliverables list with specs**, **mandatories and references**, and **budget and hours** |
| AM day-one scope | Create and edit Briefs, a job grid and Kanban, and a "My day" dashboard. Reviews and client sign-off come after the beta starts |

## Review findings (from this session)
Phase 0 writes these up in full as `docs/audit.md`, each with a severity and the phase that fixes it.

**There is no Brief entity.** A brief is a create-only modal (`src/components/modals-brief.js`). Its fields are spread across `jobs` columns (`creative_direction`, `brief_date`). The PDF link, server link, go-live dates and asset quantities are never saved.

**Most writes never reach the server.**
- The reducer writes to localStorage and syncs to the API without waiting for a reply (`src/data.js:819-827`).
- New briefs, jobs, tasks, assets, people, deletes and wiki creates are not synced at all (`src/api.js:447-503`). They disappear on reload.
- The approval endpoints exist but nothing calls them, so client approve and reject fail silently.

**The legacy app loads incomplete data.**
- Assets always arrive empty, and task content arrives empty.
- Wiki content arrives empty, so saving a wiki page in the legacy app overwrites the stored page with blank text. This is a **data-loss bug**.

**The AM role exists on paper only.** No AM users are seeded, AM can't reach Reviews, and the brief form doesn't list AM as a key role.

**The server's security has gaps.**
- `batch` has no authorization (`api/api.php:1092`).
- `update_job` lets any editor set any status, which bypasses approvals.
- The `*_by` fields are taken from the request body.
- Clients can read all users, campaigns, wiki pages and internal fields.
- `read_job` is never called.
- SQLite errors are silent, so failed writes still report success.
- Job numbers come from `COUNT`, so they can collide.
- Wiki HTML is stored unsanitised.
- The HTTPS redirect uses the Host header.
- The dummy bcrypt hash is invalid, so login timing is not constant.
- `demo_login` accepts GET.
- The audit log is never written.

**The UI and the database disagree.**
- The UI offers statuses (Scheduled, Live, Backlog) that the database rejects, and task status lists don't match.
- Some CSS tokens refer to themselves (`styles.css:85-105`), so they resolve to nothing.
- There is no dark mode.

**Dead weight.** `scripts.js` (250 KB), `backup/` and `src/test-helpers.js` are not loaded. The docs contradict the code.

**Environment facts that shape the plan**
- The server runs SQLite 3.34, so there is no `RETURNING`, `DROP COLUMN`, `STRICT` or reliable JSON1. Local SQLite is 3.45.
- `jobs.status` has a CHECK constraint, so new status values need a table rebuild. Stages therefore go in a new column, not new status values.
- The legacy `API_BASE` is relative (`src/api.js`). It must become `../api/api.php` after the move.
- Datastar is at **v1.0.4** (MIT).
- `starfederation/datastar-php` is at 1.0.1 and needs PHP 8.1 or later.
- DatastarUI (`coreycole/datastarui`, MIT, Tailwind v4, templ) provides avatar, breadcrumb, button, calendar, card, checkbox, dateinput, datepicker, dialog, dropdown, form, infinitescroll, input, label, popover, select, sheet, sidebar, tabs, textarea, themetoggle, toast and tooltip. It has **no table, badge or combobox**, so we build those in the same shadcn style.

## Target architecture (PHP that maps 1:1 to Go)
```
.htaccess   index.php (front controller → Go cmd/web/main.go)
app/        bootstrap.php config.php routes.php          (denied by .htaccess)
  Http/     Router Request Response Middleware/{Auth,Csrf,MigrateGate,BetaGate} Handlers/*
  Domain/   Stage BriefRules BriefVersion(semver) Policy JobQuery JobNumber Dates Diff Types/* Signals/*
  Store/    Db (PDO, exceptions on, txImmediate) Migrator *Store (all SQL lives here)
  View/     helpers (e, attr, js, ds_*) layout ui/* (DatastarUI ports) pages/* partials/*
migrations/ 0001_baseline.sql …           public/ css/app.css (built, committed) js/datastar.js (pinned sha256)
styles/app.css (Tailwind input)           vendor/ datastar-php + hand-written autoload (committed)
legacy/     the React app (moved)         api/ legacy endpoints (only hotfixes)   data/ db, sessions, backups, logs
tests/      run.php unit/ integration/ migration/ e2e/(Playwright, dev only)
tools/      dev-router.php build-css.sh lint-sql.php predeploy.php
docs/       audit.md adr/ archive/ (old PLAN*.md, ANALYSIS.md …)
```
**Rules that keep the Go port mechanical**
1. `strict_types` everywhere, PHP 8.3 syntax only, and no magic (no `__get`, DI container or globals).
2. Each namespace equals one Go package.
3. Data is passed in final readonly DTOs (which become Go structs). String-backed enums become Go string constants.
4. The Domain layer is pure: no I/O, and "now" is passed in. Validation returns errors instead of throwing.
5. SQL lives only in `Store/`. Use prepared statements, `BEGIN IMMEDIATE` for writes, and IDs generated in PHP.
6. Every handler has the shape `fn(Request, Deps): Response`. A Response is a full page or a list of events (PatchElements, PatchSignals, Toast, Redirect), and only `Response::send()` calls the SDK.
7. Routes use Go 1.22 ServeMux syntax (`"PATCH /jobs/{id}/fields/{field}"`), parsed by our own router.
8. Templates are functions that take a view model and never touch the database or session (they become templ components). UI ports copy DatastarUI's markup, class names and signal names, so Go can swap in the real components.
9. Signals use snake_case, namespaced by screen (`brief.due_date`, `filters.stage`, `move.to`). UI-only signals start with `_`.
10. User data enters a `data-*` expression only through `js()`, which JSON-encodes with the HEX flags. Text goes through `e()`.
11. Tailwind class strings are always written out in full, so the scanner finds them.
12. Avoid Datastar Pro-only attributes; Phase 1 confirms which ones those are. Use small vanilla JS for URL state.

**Transport**
- Every response is short (request, events, close). Nothing streams long-lived on PHP-FPM.
- Headers: `X-Accel-Buffering: no`, no-gzip, and output flushed.
- If SSE misbehaves behind Cloudflare, `config transport=html` switches to Datastar's plain `text/html` responses.
- Live refresh is a 60-second poll, never a held-open connection.

**Security**
- CSRF: an `X-CSRF-Token` header on every Datastar request, plus checks on `Datastar-Request`, `Origin` and `Sec-Fetch-Site`.
- `*_by` fields always come from the session.
- Each action has a pure function in `Policy` that returns allowed or denied, with a reason.

**Routing**
- The root `.htaccess` passes `legacy/`, `api/` and `public/` through and sends everything else to `index.php`.
- It denies internal folders and dotfiles, and redirects to a fixed HTTPS host.
- `BetaGate` sends roles outside AM, COO and ECD to `/legacy/`.
- Locally: `php -S 127.0.0.1:8301 tools/dev-router.php`, with cookie domain and path read from config. `api/auth.php` reads the same config.

## Data model and migrations
**Runner (SFTP only, no shell).** `MigrateGate` runs on the first request after a deploy:
1. Check `PRAGMA user_version`. If it is current, continue.
2. Take a `flock`.
3. Make a `VACUUM INTO data/backups/pre-NNNN-ts.db` backup and keep the last 10.
4. Run integrity and foreign-key checks.
5. Apply each file in its own transaction and record it in `schema_migrations`.
6. If anything fails, roll back and show a maintenance page in the **new app only**. Legacy keeps working because every change is additive.

`/admin/system` (COO or ECD) shows the migration level, backups, the SQLite version and whether demo mode is on. `tools/lint-sql.php` rejects any SQL that needs features newer than 3.34.

| Migration | What it adds |
|---|---|
| 0001_baseline | Current `api/db.php` DDL, all `IF NOT EXISTS`, so it does nothing on live |
| 0002_jobs_stage | `jobs.stage`, `stage_changed_at`, `waiting_on`, `waiting_reason`, `row_version`, `am_user_id`, `updated_by`. Backfills stage from status. Triggers keep `stage` in sync when legacy writes `status`, and bump `row_version` |
| 0003_briefs | `briefs` (1:1 with job): creative_direction, mandatories, references_json (links), brief_pdf_url, server_link, budget, hours_estimate, brief_date, due_date, first_go_live, last_go_live, version_major/minor/patch, has_unsent_changes, sent_at/by, created/updated_by. Backfilled from the job columns, version 1.0.0 for jobs already past the brief stage |
| 0004_brief_versions | Immutable snapshots: brief_id, version (text, "3.2.3") plus its three parts, bump_level, note (change summary), snapshot_json, diff_json, created_by/at |
| 0005_brief_assets | The deliverables list: type/template, label, qty, channel, size/format, specs, copy length, due_date, sort_order. Adds `assets.brief_asset_id`, backfilled by grouping existing assets |
| 0006_activity | Event log (actor, verb, entity, job_id, data), written in the same transaction as each change |
| 0007_job_counters | Per-brand-prefix counter, so job numbers can't collide. The legacy `add_job` is patched to use it |
| 0008_saved_views | Grid and board views: filters, sort, group, columns, shared or default |

**Brief versions (major.minor.patch).** A draft is 0.x. The first send creates **1.0.0**. Each later "Send update" asks for a bump level and suggests one from the diff:
- **major**: change of scope or a re-brief
- **minor**: deliverables, dates, budget or team changed
- **patch**: wording, references or fixes

Creatives always see the latest **sent** version, and reviews later record which version they checked. The team gets a notice reading "Brief updated to v1.2.0", with the change summary and diff.

**How deliverables become assets.** Each `brief_assets` line is a requirement, for example "3× Social Static 1080×1350, IG". On send, each line expands into `qty` rows in `assets`, named with the legacy naming scheme, and the legacy app keeps working on those rows. When a later version changes a line:
- A higher quantity adds rows.
- A lower quantity, or a removed line, cancels the extra rows that haven't been started.
- Started assets are never cancelled. The AM sees a warning instead.

**Stages** (`Domain/Stage`; the legacy `status` column is written alongside, so the legacy app keeps working):
| Stage | Legacy status |
|---|---|
| draft | Inbox / Brief |
| briefed | To Do |
| in_progress | In Progress / Today / This Week |
| waiting | Waiting |
| on_hold | On Hold |
| in_review | In Review |
| approved_internal | Approved (Internal) |
| approved_client | Approved (External) |
| done | Done |
| archived | Archived |
| cancelled | Cancelled |

**Moves an AM can make in the beta**
- draft → briefed, only through **Send**. The brief must pass the checks, and **Traffic must be assigned**; the job is then waiting on Traffic.
- briefed → draft (recall), only if no asset has started.
- Into waiting (with a reason) or on hold, and back again.
- Cancel, with a reason. Archive a job that is done or cancelled.

There is no free status editing.

**Who counts as the AM ("my jobs").** The AM is the person assigned to the job's AM role, or the brief's creator if no AM is assigned. The creator is assigned as AM automatically.

## Datastar interaction patterns
**Grid (Airtable feel)**
- Clicking a cell fetches an editor for that cell.
- Enter or blur sends the edit. The server checks the field whitelist, `Policy` and `row_version`, then returns the re-rendered row and a toast.
- If someone else changed the row in the meantime, it returns the fresh row with a "changed by someone else" toast.
- `grid-keys.js` (about 100 lines) adds keyboard movement.
- Filter, sort and group are signals, sent with a 250 ms debounce. The table body is patched and the URL updated. Sorting and filtering happen in SQL through a whitelist.

**Kanban**
- Native HTML5 drag and drop. The card moves on screen straight away, then is posted to `/jobs/{id}/move`.
- If the server refuses the move, the card snaps back with an error toast.
- Each card also has a "Move to…" menu, for touch screens and keyboard users.

**Brief editor**
- Drafts autosave (800 ms debounce, with a "Saved hh:mm" label).
- The deliverables list is a sub-fragment that is re-rendered on every change.
- "Send to Traffic" opens a confirmation dialog that lists anything still missing.
- After sending, edits go to a working copy until you choose "Send update (v1.1.0)".
- A version list, a diff view and a print view are included.

**Shared pieces.** Toasts go in `#toasts`. Dialogs and sheets are DatastarUI markup whose content is fetched into `#sheet`.

**Routes (AM scope)**
```
/login /logout /account/password
/today, /today/sections/{section}
/campaigns
POST /briefs, /jobs/{id}/brief (PATCH = autosave)
/jobs/{id}/brief/assets[/{aid}|/order], /jobs/{id}/assignments/{role}
/jobs/{id}/brief/send, /jobs/{id}/brief/versions[/{v}]
/jobs, /jobs/rows, /jobs/{id}/cells/{field}/edit
/jobs/{id}/fields/{field}, /jobs/{id}/transition
/jobs/board, /jobs/{id}/move, /jobs/{id}/sheet
/views, /admin/system, /admin/users, /healthz
```
If the Phase 1 test shows the host blocking PUT, PATCH or DELETE, those routes fall back to POST.

## How the work is delegated (models)
The main session (Opus 5.5) orchestrates: it writes specs, integrates results, makes the final diff review, and talks to you. It hands reading and well-specified building to subagents, so its own context stays small. Each subagent is defined in `.claude/agents/*.md` with a fixed model and effort, so routing is decided once, up front.

| Agent | Model | Effort | Price in/out per 1M tokens | Used for |
|---|---|---|---|---|
| `scout` | Haiku 5.5 | low | $0.10 / $0.50 | Read-only lookups: grep sweeps, "where is X used", file inventories, test and log triage, summarising fetched docs, drafting SFTP manifests from `git diff` |
| `builder` | Sonnet 5.5 | medium | $2 / $10 | Implementation from a written spec: DatastarUI component ports, Store CRUD, templates and partials, tests from tables, Tailwind markup, legacy hotfixes, doc moves |
| `architect` | Opus 5.5 | high | $4 / $20 | Hard code: `Domain` (Stage, Policy, BriefVersion, Diff), migrations and backfills, the auth port, grid conflicts and Kanban rollback, adversarial review of every PR before push. Also takes the four review checkpoints: the Phase 0 ADRs, the migration design against the live-DB profile, the Phase 2 brief and stage model, and the Phase 5 security audit |

**Rules**
- A `builder` never gets an open-ended task. Each one gets a spec naming the files to create, the interfaces, and the tests that must pass.
- A builder's output only counts once its tests are green and the main session has reviewed it.
- Builders working in parallel get disjoint files, or `isolation: worktree`.
- The `/code-review` and `/security-review` skills run on every PR before push. `/simplify` runs at the end of each phase.
**Agents by phase**
| Phase | scout (Haiku) | builder (Sonnet) | architect (Opus) |
|---|---|---|---|
| Step 0 tooling | — | hooks, scripts, skill drafts | review the guard hook |
| 0 Audit | live-DB profile queries, doc inventory | archive moves, legacy hotfixes | `audit.md`, ADRs, ADR review |
| 1 Foundation | spike results triage | UI ports, router, layout, test harness | auth port, migrator, transport |
| 2 Briefs | fixture and asset-template extraction | editor partials, Store CRUD, tests | Stage, BriefVersion, asset expansion, migrations 0002–0007, migration and brief model review |
| 3 Grid / Kanban | query plans, perf logs | grid and board partials, `grid-keys.js` | `JobQuery`, conflicts, drag rollback |
| 4 My day | — | sections, nav counts | date and timezone rules |
| 5 Hardening | predeploy manifest | headers, logs, guard script | CSP, rate limits, security audit |

- **Workflows** (many agents at once) are only used if you ask, by saying "use a workflow". The natural candidate is the Phase 1 component ports: about 15 small, independent builder tasks.

## Step 0: Claude Code tooling (first PR, before Phase 0)
Everything goes in `.claude/` and `tools/`, committed, so every cloud session starts with it.

**Hooks** (`.claude/settings.json`)
- **SessionStart** (`tools/session-start.sh`):
  - Check PHP 8.3, composer, Go and Node.
  - Install the pinned Tailwind v4 CLI. Use `npx @tailwindcss/cli@<pin>` in the cloud, since the npm registry is reachable here. Use the standalone binary on your own machine.
  - Install the Playwright test deps in `tests/e2e`, set `PLAYWRIGHT_BROWSERS_PATH`, and print a status line.
- **PreToolUse, Bash guard.** Mechanically blocks:
  - `git push` to `main`, `--force`, `reset --hard`, and `commit --amend` of pushed commits
  - any `sftp`/`scp`/`lftp`/`ssh`/`rsync`/`ftp`/`curl -T` command
  - edits under `kairosflow/` or client folders, and edits to `data/.demo_mode` or the seeded passwords

  These are your session rules, now enforced.
- **PostToolUse, after Edit or Write:**
  - `php -l` on changed `.php` files
  - `tools/lint-sql.php` on `migrations/*.sql`
  - for templates, a check that Tailwind class strings are written out in full
- **Stop:** the fast unit tests (`php tests/run.php --unit`), only when PHP files changed. Your existing unpushed-commit hook stays.

**Project skills** (`.claude/skills/`; every subagent loads the relevant one, which saves tokens re-explaining)
1. `datastar`: a Datastar 1.0.4 reference distilled from the official repo docs (`git clone` works even though the website is blocked), plus our PHP patterns: handler shape, events, signal naming, `js()`/`e()`, the html transport.
2. `go-portable-php`: the 12 portability rules as a checklist, with examples of a DTO, a handler and a template.
3. `datastarui-port`: how to port a component.
   - Render the real templ component to HTML with Go (installed locally).
   - Copy its markup, classes and signals into a PHP partial.
   - Record the source commit in the file header and add a render test.
4. `sqlite-migration`: SQLite 3.34 limits, the additive-only rules, lint, and rehearsal on `data/live-copy.db`.
5. `sftp-deploy`: builds the upload and delete list in deploy order from `git diff`, and runs `tools/predeploy.php`. This is the pre-deploy guard you asked about earlier; it fails on `unzipper.php`, `*.zip`, `seed.php`, `backup/`, `*.db` and `.demo_mode`.
6. `steward`: repo PR conventions. One PR per phase, merge commits, an SFTP checklist in every PR, never touch the server. The PR-watching rules read this file.

**Existing skills reused:** `engineering:architecture` (ADRs), `engineering:testing-strategy`, `code-review`, `security-review`, `simplify`, `run`, `session-start-hook`. **No new plugins are needed.**

**Network (your side, optional).** Allow `data-star.dev`, `datastar-ui.com`, `cdn.jsdelivr.net`, `www.cloudflare.com` and `tailwindcss.com` in the environment settings, using the steps given earlier. This lets agents read live docs instead of cloned copies, and lets us check the Cloudflare IP list.

**Exit:**
- The hooks fire: a test push to `main` is blocked, and `php -l` runs after an edit.
- The skills load.
- A `scout`/`builder` round trip completes on a toy task.

## Phases
Each phase is a branch and a PR into `main`. You deploy by SFTP, and every PR description includes an upload and delete checklist in deploy order.

**Phase 0: audit and baseline (no product code)**
- Write `docs/audit.md` and the ADRs (Datastar and the Go conventions, stages, brief versioning, the migration runner, transport).
- Move the stale docs to `docs/archive/`. Delete `scripts.js`, `backup/` and `src/test-helpers.js`.
- Rewrite `CLAUDE.md`. The only build step is the Tailwind CLI, and its output is committed.
- **You:** download a copy of the live database over SFTP. I'll profile it (stage spread, orphaned keys, job number formats, schema drift).
- **Legacy hotfixes** (separate PR; they don't touch demo mode or the seed):
  - authorize `batch` per action
  - take every `*_by` from the session
  - make `demo_login` POST-only
  - use a valid dummy bcrypt hash
  - restrict what Clients can read
  - redirect to a fixed HTTPS host
  - block saving a wiki page with empty content over existing content
- A Playwright smoke test of the legacy app.
- **Exit:** audit approved, live-database profile written, smoke test green.

**Phase 1: foundation**
- Front controller, router and middleware.
- PDO store, checking for `pdo_sqlite` on the server and falling back to SQLite3 with exceptions on.
- Port auth from `api/auth.php`: same session, cookie path and folder; rate limit by IP and username.
- Migrator with 0001, `/admin/system`, `/admin/users` and password change.
- Vendor datastar.js 1.0.4 (`type="module" data-cfasync="false"`) and datastar-php.
- Tailwind v4 standalone CLI (pinned version and sha256), using DatastarUI's theme variables, with dark mode.
- Asset URLs are versioned from content hashes, so the new app no longer needs hand-bumped `?v=N`.
- UI ports: button, input, textarea, select, label, badge, card, toast, dialog and sheet.
- Move the React app to `/legacy/` and change its `API_BASE`.
- **A `/system/spike` page** that tests every patch mode, signal patches, all HTTP methods, a 3-second slow response and the html transport, all through Cloudflare with Rocket Loader on.
- Test harness:
  - `tests/run.php` with no dependencies and table-driven tests.
  - Integration tests on a temporary database built from the migrations.
  - Playwright E2E, run on the dev machine only, against `php -S` with `PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers`.
- **Exit:** you've deployed; login works on live; `/legacy/` works; the spike passes, with the transport choice recorded; the migrator backup exists on live.

**Phase 2: Briefs (main beta value)**
- Migrations 0002 to 0007, rehearsed on your live copy (row counts unchanged, every job has a stage and a brief, assets linked, legacy smoke test green).
- The flow:
  1. Pick a campaign, or create one inline.
  2. Create the brief, then edit it with autosave.
  3. Build the deliverables list from templates (moved from `src/constants.js` `ASSET_TEMPLATES` into Domain).
  4. Add mandatories, references, budget and hours.
  5. Choose the team. Traffic is required; the CD and creatives are optional.
  6. Send to Traffic. This creates a version snapshot, expands deliverables into assets, logs the activity, and writes the legacy columns.
  7. Send updates with a semver bump, a diff and a notice.
- Versions, diff and print views.
- Tests:
  - unit: `BriefRules`, `BriefVersion`, `Stage`, `Policy`, `Diff`, `JobNumber`
  - integration: v1 and v2 asset expansion and cancellation; concurrent job numbers
  - E2E: create and send a brief, then check that Traffic and the creatives see it in `/legacy/`
- **Exit:** you create real briefs on live, and the team sees them correctly.

**Phase 3: grid and Kanban**
- `JobQuery`: filters (stage, brand, campaign, mine or all, assignee, due window, search), sort, group by (stage, brand, campaign, AM) and column choice.
- Inline edit with conflict handling; the board with drag and drop and the "Move to…" menu.
- A job sheet; saved views, with "My jobs" built in.
- Two queries per page, with no N+1.
- **Exit:**
  - E2E passes: inline edit, a conflict toast forced by a legacy write, a refused drag that snaps back, and a saved view round trip.
  - Interactions take under 300 ms (p50) on live with about 200 jobs.

**Phase 4: "My day" dashboard**
- Sections:
  - **Overdue:** your open jobs whose due date has passed (SAST).
  - **Due soon:** today and the next 3 business days.
  - **Waiting on me:** jobs waiting on the AM, unsent drafts, and briefs with unsent changes.
  - **Changed by others** since your last visit.
- Nav counts; each section refreshes on its own every 60 seconds.
- **Exit:** you use `/today` as your start page for a week, and the date and timezone tests pass.

**Phase 5: hardening and the beta gate**
- Security headers and CSP. Datastar needs `'unsafe-eval'`, so `script-src 'self'` is kept strict and inline scripts are not allowed.
- Error pages, rotated logs and backups, and rate limits on writes.
- A test that renders hostile strings through every partial.
- `tools/predeploy.php` checks:
  - `php -l` on every file and all tests passing
  - the SQL lint
  - a Tailwind rebuild matching the committed CSS
  - datastar.js matching its pinned sha256
  - every deny `.htaccess` present
  - **no** `unzipper.php`, `*.zip`, `seed.php`, `backup/`, `*.db` or `.demo_mode` in the upload set
  - an upload manifest in deploy order
- **Beta gate, which you flip by hand:**
  1. Demo mode off.
  2. Seeded passwords replaced (`/admin/users` forces a reset).
  3. Your AM user created.
  4. `/admin/system` green.

**Later, in order:** internal reviews and approvals (each recording the brief version checked) → client sign-off portal → creative "my queue" → Traffic, capacity and the other roles, plus email notifications → Wiki, with sanitized content → retire legacy area by area (then rebuild `jobs` without the old status CHECK) → Go port (routes → `routes.go`, Store → `database/sql` with modernc sqlite, View → templ with DatastarUI, Response → datastar-go).

## Main risks
| Risk | Mitigation |
|---|---|
| SSE buffered by Cloudflare or FPM | Short responses only; html transport switch; Phase 1 spike is a gate |
| Host blocks PUT, PATCH or DELETE | POST fallback routes |
| Migrating live data without a shell | Additive-only migrations; lint; backup before every run; lock; integrity checks; rehearsed on your live copy. Rollback: upload the backup file over the DB |
| Legacy and new app writing to the same rows | Triggers sync status to stage; `row_version` conflicts; the activity log shows who wrote last; legacy brief creation switched off after Phase 2 |
| DatastarUI is templ-only and lightly maintained | Port about 15 components, record the source commit in each file, and own the copies |
| Tailwind or Datastar drift | Pinned and checksummed; the predeploy rebuild and diff catches it |
| Demo mode left on at beta | Hard gate; `/admin/system` shows a banner while it's on |

## Critical files
- `api/db.php`: baseline schema, CHECKs, triggers
- `api/auth.php`: session, CSRF and demo behaviour to port and share
- `api/api.php`: `add_job`, `update_job`, `batch` (dual-write and hotfixes)
- `src/components/modals-brief.js`, `src/constants.js` (`ASSET_TEMPLATES`, naming rules), `src/utils.js` (`generateAssetName`)
- `src/api.js`: `API_BASE` for `/legacy/`
- `index.html`, `CLAUDE.md`

## Verification (each phase)
- `php tests/run.php`, which runs the unit and integration tests, plus a migration rehearsal on `data/live-copy.db`.
- `php tools/lint-sql.php` and `php tools/predeploy.php`.
- Playwright E2E against `php -S 127.0.0.1:8301 tools/dev-router.php`, which also takes screenshots of each screen in light and dark mode.
- After each of your deploys: `/healthz`, `/admin/system` (migration level, backup present), the spike page (Phase 1) and the `/legacy/` smoke test.

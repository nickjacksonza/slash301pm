# Slash 301 PM audit (Phase 0)

Scope: the code at commit `483f0c0` (branch `claude/rebuild`) before the Phase 0 hotfixes. Every claim below was checked against the code or the git history. File and line references point at that commit unless noted. Where the plan's first review was wrong or only half right, the item says so under "Corrected".

"Fixed in" values:
- **Phase 0 hotfix**: fixed by the legacy hotfix change in this branch (`api/api.php`, `api/auth.php`, `api/db.php`, covered by `tests/integration/legacy_api_test.php`).
- **Phase N rebuild**: fixed by the rebuilt app in that phase of `docs/PLAN.md`.
- **Later**: not scheduled before the beta; see the "Later" list in the plan.

Not verified here (taken from the plan or the project skills, no way to check from this machine): the live server runs SQLite 3.34, PHP 8.4 FPM, and `pdo_sqlite` availability. Local SQLite is 3.45.1 (checked).

## Critical

**C1. Anyone can become any user, including COO, while demo mode is on.**
`api/api.php:207-241` (`demo_login`) takes a `user_id` and sets the session user with no password. `api/api.php:80-94` then trusts it for every action, and `api/api.php:104` skips the CSRF check in demo mode. The user list that powers the switcher is returned to unauthenticated callers (`api/api.php:166-181`). A COO session can also call `toggle_demo_mode`.
Impact: full read and write access to all agency and client data for any visitor, plus client-side CSRF on every write.
Demo mode is enabled by the committed file `data/.demo_mode` (see M9). This is an owner decision and stays until the beta gate.
Fixed in: partly in the Phase 0 hotfix (`demo_login` is POST only and regenerates the session id; see H5). The root cause is closed only by the beta gate (demo off, passwords reset), Phase 5.

**C2. `batch` had no authorization.**
`api/api.php:1092-1195`. Any logged-in user, including a Client, could run `update_job`, `update_task` and `update_asset` on any row. The single endpoints checked permissions; the batch copy did not.
Impact: a Client could edit any job, task or asset, and set any status.
Fixed in: Phase 0 hotfix. Every operation is now validated and authorized with the same rules as the single endpoints before any data is changed; one denied operation stops the whole batch.

**C3. Every user still has the seeded password.**
`api/seed.php:22-23` hashes `Password123!` for all users. Checked against `data/live-copy.db`: 38 of 38 users still verify with it.
Impact: with demo mode off, anyone who knows the seed (it is in the public repo history) can log in as any user.
Fixed in: Phase 5 (beta gate: `/admin/users` forces a reset). Owner action. `api/seed.php` is not edited.

## High

**H1. `*_by` fields were taken from the request body.**
`api/api.php:598` (`created_by`), `947` and `990` (`approved_by`), `1025` (`feedback_by`), and the `update_job` / `update_task` / `batch` whitelists, which accept `client_feedback_by`, `internal_feedback_by`, `client_feedback_actioned_by`, `feedback_by` and `completed_by`.
Impact: anyone with write access could record an approval or feedback as someone else.
Fixed in: Phase 0 hotfix (all taken from the session; a `null` still clears a field). The rebuild keeps the rule (`docs/PLAN.md`, Security).

**H2. `update_job` and `batch` let an editor set any status, which bypasses approvals.**
`api/api.php:666-688`, `1125`. A manager or an assigned creative could set `Approved (Internal)` or `Approved (External)` directly, skipping the CD/ECD and client checks in `approve_internal` and `approve_client`.
Impact: fake approvals.
Fixed in: Phase 0 hotfix. Moving a job into either Approved status through `update_job` or `batch` returns 403, for every role. Re-sending the status the job already has is allowed, because the legacy UI sends the whole job on every save. Phase 2 replaces free status editing with stage transitions.

**H3. Clients could read other brands' data and internal fields.**
- `get_users` (`api/api.php:267-282`): all users with emails and usernames.
- `get_campaigns` (`284-308`): all brands' campaigns, and a client could pass any `brand_id`.
- `get_wiki_pages` and `get_wiki_page` (`475-523`): every page, with content.
- `get_jobs` and `get_job` (`310-473`): returned `internal_feedback`, `internal_feedback_by/at`, `hours_estimate`, client feedback routing fields, task `internal_feedback`, and assignee emails to clients.
Impact: clients see competitors' campaigns, agency staff emails, and internal comments about their own work.
Fixed in: Phase 0 hotfix. Clients get own-brand users plus agency staff names (no emails, no usernames, except their own), their own brand's campaigns, wiki pages linked to their brand's jobs or campaigns, and job reads without the internal fields. The rebuild enforces this in `Policy` and the Store queries (Phase 2 onward).

**H4. Failed writes reported success.**
`api/db.php:27`. SQLite3 exceptions were off, so a failed `execute()` returned false and the endpoint still answered `{"success": true}`.
Impact: silent data loss and unexplained "saved" states.
Fixed in: Phase 0 hotfix (`enableExceptions(true)`; an exception handler in `api/api.php` rolls back and returns a 500 JSON error). The rebuild uses PDO with exceptions (Phase 1).

**H5. `demo_login` accepted GET and did not rotate the session id.**
`api/api.php:213`, `src/api.js:123-134`. A link or image tag could switch the visitor's demo user. No `session_regenerate_id` on identity change.
Impact: login CSRF and session fixation while demo mode is on.
Fixed in: Phase 0 hotfix (POST only, JSON body, `session_regenerate_id(true)`). **The legacy UI still calls it with GET.** `demoLogin()` in `src/api.js` (moving to `legacy/src/api.js`) must switch to a POST with `{user_id}` or the demo user switcher stops working. That file belongs to the legacy move and was not edited here.

**H6. Saving a wiki page in the legacy app erased its content (data loss).**
`src/api.js:337` (`content: w.content || ''`) and the wiki list endpoint (`api/api.php:475-497`) omit `content`; the legacy app maps it to `''` and sends it back on every save (`src/api.js:455-470`). `update_wiki_page` (`api/api.php:880`) wrote it.
Impact: any wiki edit blanks the stored page.
Fixed in: Phase 0 hotfix (guard: replacing non-empty content with empty content returns 409 and saves nothing). The legacy app still cannot edit wiki pages usefully until it loads content; wiki is rebuilt after the beta (Later).

**H7. Most writes in the legacy app never reach the server.**
`src/data.js:819-827` saves to localStorage and fires `api.syncAction` without waiting for or checking a reply. `src/api.js:447-503` skips ADD_JOB (the case at 447), tasks, assets, people, deletes and wiki creates entirely. `approveInternal`, `approveClient` and `rejectWithFeedback` (`src/api.js:194-210`) are never called from any component, so approvals only ever happen as `UPDATE_JOB` status changes.
Impact: new data disappears on reload; the other users never see it.
Side effect of H2: client and internal approvals done in the legacy UI no longer persist server-side, because they were status writes through `update_job`. They were not persisted correctly before either (the approver fields were not in the whitelist).
Fixed in: Phase 2 rebuild (briefs and jobs), Later (approvals, reviews).

**H8. No Brief entity.**
A brief is a create-only modal (`src/components/modals-brief.js`). Its fields are spread over `jobs` (`creative_direction`, `brief_date`). Corrected: the form does collect a server link, PDF link and go-live dates (`modals-brief.js:63-71`, `192-195`), but the `add_job` endpoint and the `jobs` table have no columns for them, and the legacy `ADD_JOB` is never synced (`src/api.js:447-453`), so they exist only in the browser's localStorage.
Fixed in: Phase 2 (migrations 0003 to 0005).

## Medium

**M1. Reads load incomplete data in the legacy app.** `get_jobs` returns task summaries without `content` and no assets; `loadAllData` sets `assets: []` (`src/api.js:356`) and waits for `get_job`. Fixed in: Phase 2/3 rebuild.

**M2. Job numbers come from `COUNT`.** `api/api.php:569-577`: `PREFIX-` plus count plus one. Two simultaneous creates, or a delete followed by a create, collide on the `UNIQUE` job_number and (before H4) failed silently. Fixed in: Phase 2 (migration 0007, per-brand counter; the legacy `add_job` is patched to use it).

**M3. The HTTPS redirect used the Host header.** `api/auth.php:15` redirected to `https://` plus the request's own Host. Impact: host-header redirect abuse. Fixed in: Phase 0 hotfix (fixed host `projects.slash301.com`; skipped for the `cli` and `cli-server` SAPIs and for localhost requests that really come from the loopback address).

**M4. The dummy bcrypt hash was invalid.** `api/auth.php:294` used `$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012`; `password_get_info` reports algorithm `unknown`, so `password_verify` returns early and unknown usernames answer faster than known ones. Fixed in: Phase 0 hotfix (a real cost-12 hash, matching the live hashes). The rebuild also rate-limits by username (Phase 1).

**M5. The audit log is never written.** `audit_log` exists (`api/db.php:214`) but no code inserts into it (checked: no `INSERT INTO audit_log` anywhere). Fixed in: Phase 2 (migration 0006, activity log written in the same transaction as each change).

**M6. Demo-mode CSRF bypass.** `api/api.php:104`. With demo mode on, no write checks the CSRF token. Closed with C1 at the beta gate.

**M7. Creatives can read every job in the list.** `get_jobs` filters only for Clients. `get_job` now enforces `read_job` for creatives (Phase 0 hotfix: assigned jobs only; previously `read_job` was defined in `api/permissions.php:138` but never called). The list stays open for creatives because the legacy UI needs it. Fixed in: Phase 2/3 rebuild (`Policy`, `JobQuery`).

**M8. Assigned creatives can change anything on a job they are assigned to.** `update_job` lets an assigned creative edit title, status and assignments (`api/permissions.php:85-91`, `api/api.php:666`). Fixed in: Phase 2 rebuild (field whitelist per action in `Policy`).

**M9. Demo mode is enabled by a committed file.** `data/.demo_mode` is tracked in git (`git ls-files data`), so every deploy of the repo contents switches demo mode on. Owner decision: it stays on until the beta gate. `tools/predeploy.php` refuses to upload it, so the live file is whatever is on the server. Fixed in: Phase 5 (beta gate; `/admin/system` shows a banner while it is on).

**M10. The database was committed in the first commit.** Commit `fa93fde` contains `data/slash301pm.db` (188,416 bytes), along with `api/seed.php`, `unzipper.php`, a zip archive and Playwright logs. It was removed from tracking in #1 (`4251348`) but is still in git history, so anyone with the repo can read that data (and the seeded password hashes, see C3). Impact depends on whether that file held real client data. Fixed in: Later (owner decision on a history rewrite; `*.db` is now in `.gitignore`).

**M11. Wiki HTML is stored without sanitising on the server.** `api/api.php:880` stores `content` as given; `sanitizeOutput()` in `api/permissions.php:341` is defined but never called. Corrected: the legacy UI does sanitise on render (`src/components/wiki.js:372` uses DOMPurify when it is loaded), so this is a stored-XSS risk only if DOMPurify fails to load or another client renders the content. Fixed in: Later (wiki rebuild, with server-side sanitising).

**M12. Login rate limit is per IP only.** `api/auth.php:160`. A distributed attack on one username is not slowed. Fixed in: Phase 1 (IP and username).

## Low

**L1. The UI offers statuses the database rejects.** `src/constants.js:21` lists `Scheduled` and `Live` for jobs; `jobs.status` has a CHECK without them (`api/db.php:96`). Corrected: `Backlog` is allowed for tasks (`api/db.php:152`) and only a problem if a job is given it; the task status lists in the UI also differ from the table. Impact: those saves now fail loudly (500) instead of being dropped. Fixed in: Phase 2 (stage column), Later (rebuild `jobs` without the CHECK once legacy is retired).

**L2. `ROLE_PERMISSIONS` has no entries for Copywriter, Designer and QA.** `src/constants.js:73-250` defines COO, Traffic, PM, ECD, CD, Client, Producer, AM, Developer, SEO, Social and `default`. Copywriter, Designer and QA fall through to `default` (`src/constants.js:257-260`). Impact: they get the generic permissions, not role-specific ones. The server's tiers in `api/permissions.php` do cover them. Fixed in: Phase 1/2 (`Policy` replaces the table).

**L3. The AM role exists on paper only.** Corrected from the first review: `AM` has a `ROLE_PERMISSIONS` entry (`src/constants.js:173`) and the server treats it as a manager. Still true: `api/seed.php` has no AM user (the live copy has one, added by hand), `canAccessJobReview` leaves AM out (`src/components/operations.js:441`), and the brief form's `KEY_ROLES` omits AM (`src/components/modals-brief.js:286`). Fixed in: Phase 2.

**L4. Self-referencing CSS variables.** `styles.css:85-105`: `--metric-blue`, `--metric-purple`, `--review-approve` and others are defined as `var(--same-name)`, which resolves to nothing. There is no dark mode (no `prefers-color-scheme` rule). Fixed in: Phase 1 (Tailwind v4 theme with dark mode).

**L5. Dead weight.** `scripts.js` (250,421 bytes) and `backup/` (`index.html`, `scripts.js`, `scripts-v4.js`) are not loaded by `index.html`; `src/test-helpers.js` is not referenced by it either. The older docs describe a different architecture (for example `README.md` said Supabase and localStorage-only). Fixed in: Phase 0 (docs archived, README rewritten; the file deletions belong to the legacy move).

**L6. `logout` accepts GET.** `api/api.php:192`. The legacy UI logs out with GET (`src/api.js:116-120`, `logout()`), so GET stays working. Impact: a third-party page can log a user out. Fixed in: Phase 1 (POST with CSRF token in the rebuild).

**L7. Input validation is thin.** `update_job`, `update_task`, `update_asset` do not validate field types or enum values (the database CHECKs are the only guard), and `assignments` are not checked against known roles or users. Since H4, bad values return 500 instead of being swallowed. Fixed in: Phase 2 rebuild (`BriefRules`, `Policy`).

**L8. The legacy `API_BASE` is relative.** `src/api.js:6` is `'api/api.php'`; it must become `../api/api.php` when the app moves to `/legacy/`. Fixed in: Phase 1 (legacy move).

**L9. Cloudflare IP ranges are hard-coded.** `api/auth.php` (`getClientIp`) lists the ranges by hand and must be re-checked from time to time. Fixed in: Phase 5.

## Verified as correct and already handled

- Prepared statements are used for all queries; the dynamic `SET` lists use fixed whitelists, not request keys.
- Client brand isolation in `get_jobs` and `get_job` was already enforced server-side.
- Session cookies are `HttpOnly`, `SameSite=Lax`, strict mode, 48-character ids, custom save path with a deny `.htaccess`.
- `data/`, `data/sessions/` and `api/seed.php` have deny rules in `.htaccess` files.
- `getClientIp()` only trusts `CF-Connecting-IP` from Cloudflare ranges.

## Phase 5 status (2026-10-10)

- C1, C3, M6, M9: closed by the beta gate, which only the owner can flip (docs/beta-gate.md). `/admin/system` now lists every unmet gate item it can compute: the demo flag, active users still on the seed password (checked against their hashes, cached by fingerprint), no active AM user, a failed or pending migration, no backup, and the datastar.js pin. The shell shows a banner on every page while the demo flag file exists.
- Hardening added in Phase 5: one set of security headers with a Content Security Policy (docs/adr/0007-security-headers.md); a sliding-window limit of 120 writes per minute per user or IP (migration 0011); generic error pages with a request id and full detail only in `data/logs/` (rotated after 30 days); an activity row for every state-changing route, enforced by a test (docs/audit-trail.md); server folder and reference links restricted to http(s) and share paths, with only http(s) ever rendered as a link; `tools/predeploy.php` checks migration checksums, the datastar.js pin, deny files, the SQL lint, the tests and the Tailwind build.
- L9 (hard-coded Cloudflare IP ranges): still open. The ranges in `app/Http/ClientIp.php` and `api/auth.php` could not be re-checked from the sandbox (cloudflare.com is not reachable). The write rate limit for signed-out requests and the login limit both key on this IP, so re-check the list against https://www.cloudflare.com/ips/ before the beta.
- M10 and M11 stay "Later" as planned.


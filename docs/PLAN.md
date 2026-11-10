# Slash 301 PM: plan to version 1

Written 2026-11-09 after a full review of the repo. It replaces the rebuild plan, which is kept at `docs/archive/PLAN-rebuild-2026.md` (older documents such as `docs/audit.md` and `docs/roles.md` refer to that one when they say "the plan" or name a phase).

## Goal

A project management tool for a full agency team. The team creates a Brief and passes it through Jobs and Campaigns. The result is an approved social media post, made of copy and media, that can be scheduled for posting.

## Definition of version 1

A real job can go from a new Brief to a scheduled post using only the new app, by every role including the client. Every page looks like the original app (the Kanban and Airtable feel). One automated test proves the whole path. The old app stays linked for Capacity, Wiki and Operations.

## Where things stand

| Step on the main path | State |
| --- | --- |
| Campaigns, brands, brand logos | Built |
| Brief: create, autosave, deliverables, send to Traffic, versions, diff, print | Built |
| Jobs: grid (inline edit, filters, saved views) and Kanban board | Built |
| My day | Built |
| Makers hand in copy and a media link per post | Built (links only, no file upload) |
| Internal review: CD, ECD, rounds, feedback, reopen on rejection | Built |
| Client sign-off | Half built. Staff can record it. The client portal has rules and database code but no pages yet |
| Social: final check, ready, scheduled date, live link, promoted | Built (records the schedule; it does not post to platforms) |
| Users, system page, migrations, backups, security headers, rate limits | Built |

The structure is sound and nothing needs a rewrite. What is wrong:

1. **Design drift.** Later pages (review, work, social, admin, account, login) are hand-written and do not use the shared parts in `app/View/ui/`. The page frame is a left sidebar; the original has a top bar, pill tabs and a table/Kanban toggle.
2. **Small gaps.** A job's ECD cannot be assigned in the new app. Social records keep their status when a client-approved job is reopened. No test walks the whole path.
3. **Leftovers.** The spike page is in the menu. There are far more UI fixture files than needed.

## Steps

Work is on the branch `claude/rebuild`. Each step is committed and pushed on its own, and `php tests/run.php` must pass each time. Update the Status column as steps land.

| # | Step | Status |
| --- | --- | --- |
| **Stage 1: save and re-baseline** | | |
| 1 | Commit the portal groundwork (rules, policy, sign-in seam, mailer, stores) | Done |
| 2 | New `README.md`, `CLAUDE.md` and this plan; old plan archived | Done |
| 3 | Remove the spike page from the menu (route stays for COO and ECD) | Done |
| **Stage 2: one look everywhere** | | |
| 4 | Screenshot audit: each legacy screen beside each new page, 1280px and 390px | To do |
| 5 | Page frame: top bar, pill tab bar (Today, Work, Briefs, Campaigns, Reviews, Social, More). Work holds the table/Kanban toggle. More holds People, Users, System, Overrides and the old app | To do |
| 6 | Shared parts added once: page toolbar, view toggle, filter pills, metric card, side panel header and footer, traffic-light status chip, empty state | Done |
| 7 | Convert drifted pages to the shared parts: review, work, social, today, admin users, admin system, admin overrides, account, login, errors. No behaviour changes | Mostly done. Left: grid cell editors, board chips, deliverables inputs, a few checkboxes |
| 8 | Drift test: a page may not carry a hand-written button, input, select or textarea. Rebuild the CSS. Re-take and check the screenshots | Drift test done (allow-list shrinks). Final screenshot re-check of all pages still open |
| **Stage 3: finish the main path** | | |
| 9 | Client portal: staff invite page per brand; accept page (`/invite/{token}`); client frame and gates; `/portal` and `/portal/jobs/{id}` with approve, reject with feedback, and final sign-off by a Brand Director; tests for brand isolation, decisions, hidden internal fields, hostile strings | To do |
| 10 | ECD assignment on the job team form | To do |
| 11 | Reopen clean-up: Social records return to checking and the client approval stamp is cleared, both logged | To do |
| 12 | People page (`/people`): the team by role with each person's open jobs and posts, read-only | To do |
| **Stage 4: prove it and tidy** | | |
| 13 | One walk-through test from new brief to live post, checking the stage and the legacy status at every step | To do |
| 14 | Browser run of the same path with a screenshot per step | To do |
| 15 | Trim the UI fixtures to button, badge, input and table; document how to run the live-copy tests | To do |
| 16 | Update `docs/roles.md`, `docs/audit-trail.md`; write `docs/portal.md` | To do |
| **Stage 5: release** | | |
| 17 | Pre-deploy check, then a PR from `claude/rebuild` into `main` (the owner merges) | To do |
| 18 | `docs/deploy/alpha-3.md` and the upload package | To do |
| 19 | List for the owner: beta gate, email service, fresh live database copy | To do |

## Portal decisions already taken

- The client review page is `/portal/jobs/{id}`. Clients are refused every staff route.
- A job appears in the portal once it is approved internally and marked ready for the client.
- Final sign-off needs every post approved by a client contact first. Only a Brand Director with sign-off rights gives it.
- Both kinds of client rejection need feedback. A rejection reopens the job.
- Invited clients sign in with their email address. Invite links last 7 days and work once. A new link cancels older ones.
- Invite email is sent with PHP `mail()` from the owner's address, and the link is also shown once on screen to copy. Mail sent this way may land in spam.
- No password-change page for clients in version 1.

## Design reference

`legacy/styles.css` and the legacy screens are the reference. The written rules are in `app/View/ui/README.md` ("Design rules"). In short: warm off-white backgrounds, sage accent, pill buttons, inputs and tabs, 12 to 20px corner radii, soft warm shadows, uppercase muted table headers, 300px Kanban columns with rounded cards, traffic-light status colours.

## After version 1

Capacity, Wiki and Operations in the new app. Media file upload. A real email service. Authorizer with MFA for clients (ADR 0006). Posting to platforms. The Go port.

## Owner-only items

- The beta gate in `docs/beta-gate.md`: demo mode off, seeded passwords replaced, AM users created.
- Every SFTP deploy.
- Choosing an email service.
- Supplying a copy of the live database (`data/live-copy.db`, never committed) when migrations should be rehearsed on real data.

## How each step is checked

- `php tests/run.php` passes.
- `php tools/predeploy.php <live commit> WORKTREE` ends with "Pre-deploy check passed" before any release.
- For any page change: screenshots at 1280px and 390px, looked at and compared with the legacy match.

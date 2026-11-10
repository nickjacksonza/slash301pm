# Slash 301 PM

Project management for a creative agency team. The team writes a Brief, passes it through Jobs and Campaigns, and ends with an approved social media post (copy and media) that can be scheduled for posting.

Live at `projects.slash301.com/slash301pm/`.

## The path a piece of work takes

1. **Brand and Campaign.** A Brand is a client. A Campaign groups that client's jobs.
2. **Brief.** The Account Manager writes the Brief for a Job: direction, dates, budget, and a list of deliverables (the posts). They send it to Traffic. Later edits go out as numbered versions (1.0.0, 1.1.0 and so on) with a list of changes.
3. **Team.** Traffic assigns the Creative Director and the makers.
4. **Posts.** Copywriters and Designers hand in copy and a media link for each post.
5. **Internal review.** The Creative Director, and the Executive Creative Director when asked, approve or send back each part with feedback.
6. **Client sign-off.** The client's contacts approve the posts. A Brand Director gives the final sign-off.
7. **Schedule.** Social runs the final check for each platform, records the scheduled date, and later the live link.

Each Job has one stage at any time: draft, briefed, in progress, waiting, on hold, in review, approved (internal), approved (client), ready to schedule, scheduled, live, done, archived or cancelled.

## Roles

| Role | Main work |
| --- | --- |
| AM (Account Manager) | Writes and sends Briefs, follows jobs, deals with the client |
| Traffic | Assigns the team, keeps work moving |
| PM, Producer | Follow and move the jobs they own |
| CD, ECD | Review and approve the work |
| Copywriter, Designer, Developer, SEO | Make the posts |
| QA | Checks work before review |
| Social | Final check, scheduling, live links |
| COO | Sees everything, manages users and system settings |
| Client | Approves posts and signs off, in the client portal |

Who may do what is in `docs/roles.md`. In code it is one table: `app/Domain/Policy.php`.

## Two apps, one database

- **New app** at `/slash301pm/`: PHP 8.3, SQLite, Datastar, Tailwind. This is where all work happens now.
- **Old app** at `/slash301pm/legacy/`: the original React app. It stays for Capacity, Wiki and Operations until those are rebuilt. Its API is `api/api.php`.

What is built and what is left: `docs/PLAN.md`.

## Run it locally

Needs PHP 8.3 with SQLite. Node is needed only to rebuild the CSS.

| Task | Command |
| --- | --- |
| Run the app | `php -S 127.0.0.1:8301 tools/dev-router.php` |
| Run all tests (about 30 seconds) | `php tests/run.php` |
| Run one suite | `php tests/run.php --unit` or `php tests/run.php integration` |
| Rebuild the CSS (output is committed) | `bash tools/build-css.sh` |
| Check before a deploy | `php tools/predeploy.php <live-commit> <new-commit>` |

## Releasing

Deploys are done by hand over SFTP by the owner. There is no build on the server: everything uploaded is already built. `tools/predeploy.php` prints the upload and delete list in the right order. After an upload, opening `/slash301pm/healthz` runs any new database migrations and writes a backup first. See `CLAUDE.md` ("Deploy Order") and `docs/deploy/`.

Demo mode is on until the owner goes through `docs/beta-gate.md`.

## Folders

| Folder | Holds |
| --- | --- |
| `app/Domain` | The rules: stages, permissions, brief versions, reviews. No database or web code |
| `app/Store` | All SQL |
| `app/Http` | Router, middleware, request handlers |
| `app/View` | Page frame, pages, and shared parts in `ui/` |
| `migrations` | Database changes, applied in order, never edited after release |
| `public` | Built CSS and the small scripts |
| `styles` | Tailwind input and design tokens |
| `legacy`, `api` | The old app and its API |
| `tests`, `tools` | Tests and command-line helpers |
| `docs` | Plan, roles, decisions (`adr/`), deploy notes, security audit, old documents (`archive/`) |

## Documents

- `docs/PLAN.md`: the plan to version 1 and its status
- `docs/roles.md`: every role, what it sees and may do
- `docs/adr/`: the main technical decisions and why
- `docs/audit.md`, `docs/audit-trail.md`: security review and what gets logged
- `docs/beta-gate.md`: what must be true before daily use
- `CLAUDE.md`: working rules for Claude Code sessions

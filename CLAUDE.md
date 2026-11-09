# Slash 301 PM

Live at `projects.slash301.com/slash301pm/`.

**SFTP path:** `public_html/projects/slash301pm/`

**The only build step** is `bash tools/build-css.sh` (the Tailwind CLI). Its output `public/css/app.css` is committed. The server runs no build, no Composer and no npm: everything that is uploaded is already built.

## Goal

A project management tool for a full agency team. The team creates a Brief and passes it through Jobs and Campaigns. The result is an approved social media post, made of copy and media, that can be scheduled for posting.

**Version 1 is done when** a real job can go from a new Brief to a scheduled post using only the new app, by every role including the client, every page looks like the original app, and one automated test proves the path.

The plan and its status are in `docs/PLAN.md`. Read it at the start of a session, work on the next open step, and update its Status column when a step lands.

## Owner's rules (always in force)

- Work on a branch and open a PR. The working branch is `claude/rebuild` unless the session assigns another. Never push to `main`. The owner merges.
- Never force-push, never `git reset --hard`, never amend a pushed commit.
- Never touch the live Xneelo server. Deploys are SFTP by the owner, by hand. If a change needs a server step, list it for the owner.
- Do not change demo mode (`data/.demo_mode` stays as it is) or the seeded passwords (`api/seed.php`). Both are deliberate for now.
- Do not touch kairosflow or any client folders.
- Confirm with the owner before any outward-facing or destructive action.
- The owner's email address is private. In this repo it appears only as the invite sending address (`Config::MAIL_FROM`). Never send it to an outside service.
- Write to the owner in plain language (ISO 24495-1): short sentences, no em dashes, no filler.

`.claude/hooks/guard-bash.sh` enforces most of these. It also blocks some harmless commands that happen to contain a guarded word (for example a command whose text names the demo-mode file, or `pkill`). Do not work around the guard: reword the command or use the Write tool.

## How the work is run

- The lead session plans, writes specs, reviews every diff and talks to the owner. The owner runs it on Fable.
- Subagents in `.claude/agents/`: `architect` (Opus) for Domain rules, migrations and security; `builder` (Sonnet) for pages and stores from a written spec that names files and tests; `scout` (Haiku) for read-only lookups. A builder never gets an open-ended task.
- **Commit and push after every logical step.** The cloud container can be replaced at any time and unpushed work is lost. Tell subagents the same.
- Keep `php tests/run.php` passing at every commit.
- Project skills in `.claude/skills/`: load `go-portable-php` before PHP work, `datastar` before markup or handlers, `datastarui-port` for `app/View/ui/`, `sqlite-migration` before a migration, `sftp-deploy` and `steward` before a PR.

## Commands

| Task | Command |
| --- | --- |
| All tests (about 30 s) | `php tests/run.php` |
| One suite | `php tests/run.php --unit`, `php tests/run.php integration` |
| Local server | `php -S 127.0.0.1:8301 tools/dev-router.php` |
| Rebuild CSS | `bash tools/build-css.sh` |
| Migration checksums | `php tools/gen-checksums.php` (add `--check` to verify) |
| SQL lint | `php tools/lint-sql.php` |
| Pre-deploy check and upload list | `php tools/predeploy.php <from> <to or WORKTREE>` |

A temporary database for a local run or screenshots: set `S301_DB` and `S301_DATA_DIR` to paths outside the repo.

## Architecture

Two apps share one SQLite database. The new app is the front controller `index.php` plus `app/`. The old React app is in `legacy/` with its API in `api/`; change it only for hotfixes the owner asks for.

| Folder | Rule |
| --- | --- |
| `app/Domain` | Pure rules. No I/O. "Now" is passed in. Validation returns errors, it does not throw. Permissions are one table in `Policy.php`; review permissions in `ReviewPolicy.php` |
| `app/Store` | The only place with SQL. Prepared statements, `BEGIN IMMEDIATE` for writes, `row_version` checks, IDs made in PHP |
| `app/Http` | Handlers have the shape `fn(Request, Deps): Response`. A response is a full page or a list of events (patch elements, patch signals, toast, redirect) |
| `app/View` | Functions that take a view model and return HTML. They never touch the database or the session |
| `migrations` | Additive only. Never edit a migration that has been deployed. Update `CHECKSUMS.txt` with the tool |

Code rules, in short (full list in the `go-portable-php` skill; the aim is a later mechanical port to Go):

- `declare(strict_types=1)`, PHP 8.3 syntax, final readonly data classes, string-backed enums, no magic methods or globals.
- Text is escaped with `e()`, attributes with `attr()`, and user data enters a `data-*` expression only through `js()`.
- Tailwind class strings are written out in full as literals.
- No inline scripts (the Content Security Policy blocks them). Redirects from Datastar requests use the `_redirect` signal.
- Every write checks CSRF and the policy on the server. `*_by` fields come from the session, never from the request.
- Every change to a job is logged in the same transaction (`docs/audit-trail.md`).
- Stages are in `Domain/Stage`; the legacy `status` column is written alongside so the old app keeps working (ADR 0002).

## Design

The original app is the reference: `legacy/styles.css` and the legacy screens. The written rules are in `app/View/ui/README.md` ("Design rules").

- Pages are built from the shared parts in `app/View/ui/` and `app/View/partials/`. A page does not carry its own hand-written button, input, select or textarea classes. If a part is missing, add it to the shared set first.
- Frame: top bar, pill tab bar, table/Kanban toggle on the Work page.
- After any page change, take screenshots at 1280px and 390px and look at them before reporting.

## Server and environment facts

- Live: PHP 8.4, SQLite 3.46, shared Apache hosting at Xneelo behind Cloudflare. No shell on the server.
- Migration SQL stays within SQLite 3.34 features (no `RETURNING`, `DROP COLUMN`, `STRICT` or JSON1); `tools/lint-sql.php` enforces this.
- Migrations run on the first request after an upload, after a `VACUUM INTO` backup in `data/backups/`.
- Datastar 1.0.4 is vendored and pinned by sha256 (`public/js/datastar.js.sha256`). Responses are short; nothing streams.
- `data/live-copy.db` is a copy of the live database that the owner supplies. It is never committed. Without it, the live-copy tests skip themselves.
- Local PHP is 8.3. Chromium and Playwright are pre-installed in the cloud container for screenshots.

## Deploy Order

The order matters: the root `.htaccess` routes to `index.php`, `app/`, `vendor/` and `legacy/`, so uploading it early breaks the site; and `app/` uploaded before its `.htaccess` is exposed.

1. Sub-folder `.htaccess` files (`app/`, `vendor/`, `migrations/`, `api/`, `data/`, `tests/`, `tools/`, any other non-root one)
2. Backend (`api/`, `app/`, `vendor/`, `migrations/`, allowed `data/` files, `index.php`)
3. Frontend (`public/`, `legacy/`, other files)
4. The ROOT `.htaccess`, last
5. Deletes (old root `index.html`, `src/`, `styles.css`, `scripts.js`, `backup/`), after all uploads

After upload, open `/slash301pm/healthz` once; it runs pending migrations and writes a backup in `data/backups/`.

Run `php tools/predeploy.php <from> <to>` for the exact list. Increment `?v=N` on CSS/JS includes in `legacy/index.html` on each deploy for Cloudflare cache busting.

## Network access

The cloud environment for this repo allows outbound HTTPS to the domains below. Anything not listed is denied by policy. The list was checked against the proxy in an earlier session; if a host fails, check the environment's Allowed domains before assuming a bug.

Reachable, per the environment settings:

| Category | Domains |
| --- | --- |
| Code hosting | github.com, api.github.com, codeload.github.com, raw.githubusercontent.com, release-assets.githubusercontent.com, *.github.com, *.githubusercontent.com |
| npm / JS | npmjs.com, npmjs.org, www.npmjs.com, www.npmjs.org, registry.npmjs.org, registry.yarnpkg.com, yarnpkg.com, unpkg.com |
| Python | pypi.org, files.pythonhosted.org, pythonhosted.org |
| Rust | crates.io, index.crates.io, static.crates.io, docs.rs |
| Go | go.dev, golang.org, pkg.go.dev, proxy.golang.org, sum.golang.org |
| Google | *.google.com, googleapis.com, gstatic.com, support.google.com |
| Anthropic / Claude | api.anthropic.com, claude.ai, bridge.claudeusercontent.com |
| CDNs | cdn.jsdelivr.net, cdnjs.cloudflare.com |
| Reference / docs | developer.mozilla.org, w3.org, stackoverflow.com, postgresql.org, *.postgresql.org |
| Linux packages | archive.ubuntu.com, security.ubuntu.com |
| Other | *.slash301.com |
| Added: other registries | rubygems.org, index.rubygems.org, repo.maven.apache.org, plugins.gradle.org, services.gradle.org, repo.packagist.org, api.nuget.org, repo.anaconda.com, conda.anaconda.org |
| Added: toolchains and downloads | nodejs.org, dl-cdn.alpinelinux.org, releases.hashicorp.com, developer.hashicorp.com, dl.google.com |
| Added: ML and docs | huggingface.co, docs.python.org, learn.microsoft.com, kubernetes.io |

Not allowed: container registries (for example registry-1.docker.io). Pulling images will fail.

Notes:
- claude.ai and www.npmjs.com are reachable, but they return a Cloudflare bot challenge to non-browser clients, so `curl` and `WebFetch` may get a 403 there.
- crates.io returns 403 from the origin for the site root.

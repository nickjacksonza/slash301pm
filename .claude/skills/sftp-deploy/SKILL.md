---
name: sftp-deploy
description: Prepare the owner's manual SFTP deploy for Slash 301 PM - run the pre-deploy guard, produce the upload and delete checklist in deploy order, and list any server-side steps. Load when a PR is ready, when the owner asks what to upload, or before writing a PR description.
---

# SFTP deploy checklist

The owner deploys by hand over SFTP to `public_html/projects/slash301pm/` (live at `projects.slash301.com/slash301pm/`, Xneelo shared hosting behind Cloudflare). Claude never connects to the server; the Bash guard hook blocks it. Claude's job is to hand the owner an exact, ordered checklist.

## Steps
1. Find the range. `<from>` is what is live now: ask the owner if unsure, otherwise use the base of the PR (`git merge-base origin/main HEAD`) or the last commit the owner confirmed as deployed. `<to>` is the commit being deployed (usually `origin/main` after merge).
2. Run `php tools/predeploy.php <from> <to>`. It:
   - lists uploads and deletes in the safe deploy order: **1 sub-folder `.htaccess` files (`app/`, `vendor/`, `migrations/`, `api/`, `data/` and any non-root one), 2 backend in this order: `migrations/` first (the code already on the server applies new migrations on its next request, and the new code may need the new tables), then `vendor/`, `data/` allowed files, `api/`, `app/`, and `index.php` last (it loads `app/`), 3 frontend (`public/`, `legacy/`, other files), 4 the ROOT `.htaccess` last, 5 deletes (old root `index.html`, `src/`, `styles.css`, `scripts.js`, `backup/`)**. Uploading the root `.htaccess` before `index.php`, `app/`, `vendor/` and `legacy/` breaks the site, and `app/` uploaded before its `.htaccess` is exposed;
   - leaves out repo-only files (`.claude/`, `docs/`, `tests/`, `tools/`, `styles/`, `migrations/CHECKSUMS.txt`, `*.md`), and fails if one ever lands in the manifest;
   - **fails** if the upload set contains `unzipper.php`, any archive (`*.zip`, `*.tar.gz` etc.), `api/seed.php`, `backup/`, a database file, `data/.demo_mode`, any other `data/` file except the deny `.htaccess` files (logs, sessions, backups, `seed-password-check.json`), or `.env` files;
   - **fails** on a PHP syntax error (`php -l` on every PHP file to be uploaded), a migration that fails `tools/lint-sql.php`, a migration whose sha256 differs from `migrations/CHECKSUMS.txt` (or is missing from it), a shipped migration edited or deleted in the range, `public/js/datastar.js` not matching `datastar.js.sha256`, a missing deny `.htaccess` (`app/`, `vendor/`, `migrations/`, `tests/`, `tools/`, `data/`, `data/sessions/`, `data/logs/`, `api/`), or a failing `php tests/run.php` (working tree; `--no-tests` skips it);
   - **warns** (does not fail) when `public/css/app.css` was committed before the latest change in `styles/`, or an offline rebuild with the pinned Tailwind CLI differs from it (`--no-build` skips the rebuild);
   - `<to>` may be `WORKTREE` to check uncommitted and new files before the owner commits.
   New migration: run `php tools/gen-checksums.php` to add its line to `migrations/CHECKSUMS.txt` (it never rewrites an existing line).
3. If it fails, fix the cause in the repo (or explain to the owner) before giving a checklist. Never tell the owner to upload a forbidden file.
4. Add server-side steps the script cannot see:
   - Files to delete by hand that git never tracked (stray archives, `unzipper.php`, `api/seed.php`, old probe scripts). Always remind the owner to check the web root for these.
   - Migrations: after the upload, open `/slash301pm/healthz` once (it runs pending migrations and writes a backup to `data/backups/`), then check `/admin/system`.
   - Cache busting: `legacy/index.html` uses `?v=N`; bump it when legacy JS or CSS changed. The new app versions assets by content hash.
   - Anything that needs the owner's decision (demo mode, passwords) is listed as "owner only", never done by Claude.
5. Put the checklist in the PR description under a "Deploy (SFTP)" heading, and repeat it in chat after merge.

## Checklist format
```
Deploy (SFTP) - public_html/projects/slash301pm/
1. Sub-folder .htaccess files
   - upload app/.htaccess, vendor/.htaccess, migrations/.htaccess
2. Backend
   - upload api/auth.php, app/..., vendor/..., index.php
3. Frontend
   - upload legacy/index.html (bump ?v=N first), public/...
4. Root .htaccess (last)
   - upload .htaccess
5. Deletes
   - delete old root index.html, src/, styles.css, scripts.js, backup/
6. On the server, by hand
   - delete unzipper.php and any zipper-*.zip in the web root
   - delete the seed script from api/ if present; check the web root for other stray archives
7. After upload
   - open /slash301pm/healthz once (runs migrations, writes a backup in data/backups/), then /slash301pm/admin/system
```

# Slash 301 PM

Live at `projects.slash301.com/slash301pm/`.

**SFTP path:** `public_html/projects/slash301pm/`

**The only build step** is `bash tools/build-css.sh` (the Tailwind CLI). Its output `public/css/app.css` is committed. The server runs no build, no Composer and no npm: everything that is uploaded is already built.

## Deploy Order

1. `.htaccess` files first (root, `app/`, `data/`, `migrations/`, `vendor/`, `legacy/`, `api/`)
2. Backend: `index.php`, `app/`, `vendor/`, `migrations/`, `api/` (the first request after a deploy applies new migrations)
3. Frontend: `public/` (`css/app.css`, `js/`) and `legacy/` (the React app: `index.html`, `styles.css`, `src/`)

Run `php tools/predeploy.php <live-commit> <new-commit>` before uploading. It prints the upload and delete list in this order and fails if the upload set contains `unzipper.php`, `*.zip`, `seed.php`, `backup/`, `*.db` or `.demo_mode`.

Inside `legacy/`, increment `?v=N` on the CSS/JS includes in `legacy/index.html` on each deploy for Cloudflare cache busting. The new app versions its assets from content hashes.

## Working in this repo with Claude

The rebuild on Datastar (AM role first) follows `docs/PLAN.md`; decisions are in `docs/adr/`, known problems in `docs/audit.md`.

- **Standing rules** (enforced by `.claude/hooks/`): no push to `main`, no force-push, no `reset --hard`, no amending pushed commits, never connect to the server, leave `data/.demo_mode`, `api/seed.php` and kairosflow alone. Run `bash .claude/hooks/test-hooks.sh` after changing a guard.
- **Deploy checklist:** `php tools/predeploy.php <live-commit> <new-commit>` prints uploads and deletes in deploy order and fails on files that must never reach the server. See the `sftp-deploy` skill.
- **SQL for migrations** must pass `php tools/lint-sql.php` (the server runs SQLite 3.34).
- **Subagents** (`.claude/agents/`): `scout` (Haiku, read-only lookups), `builder` (Sonnet, implementation from a written spec), `architect` (Opus, Domain, migrations, auth, PR review).
- **Project skills** (`.claude/skills/`): `datastar`, `datastarui-port`, `go-portable-php`, `sqlite-migration`, `sftp-deploy`, `steward`.

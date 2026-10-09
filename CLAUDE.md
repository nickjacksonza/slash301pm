# Slash 301 PM

Live at `projects.slash301.com/slash301pm/`.

**SFTP path:** `public_html/projects/slash301pm/`

**No build step.** Open `index.html` directly in a browser. No npm, no bundler, no package manager.

## Deploy Order

1. `.htaccess` files first
2. Backend (`api/`, `data/`)
3. Frontend (`index.html`, `styles.css`, `scripts.js`)

Increment `?v=N` on CSS/JS includes in `index.html` on each deploy for Cloudflare cache busting.

## Working in this repo with Claude

A rebuild on Datastar (AM role first) is planned in phases; Phase 0 rewrites this file. Until then:

- **Standing rules** (enforced by `.claude/hooks/`): no push to `main`, no force-push, no `reset --hard`, no amending pushed commits, never connect to the server, leave `data/.demo_mode`, `api/seed.php` and kairosflow alone. Run `bash .claude/hooks/test-hooks.sh` after changing a guard.
- **Deploy checklist:** `php tools/predeploy.php <live-commit> <new-commit>` prints uploads and deletes in deploy order and fails on files that must never reach the server. See the `sftp-deploy` skill.
- **SQL for migrations** must pass `php tools/lint-sql.php` (the server runs SQLite 3.34).
- **Subagents** (`.claude/agents/`): `scout` (Haiku, read-only lookups), `builder` (Sonnet, implementation from a written spec), `architect` (Opus, Domain, migrations, auth, PR review), `oracle` (Fable, rare checkpoint reviews).
- **Project skills** (`.claude/skills/`): `datastar`, `datastarui-port`, `go-portable-php`, `sqlite-migration`, `sftp-deploy`, `steward`.

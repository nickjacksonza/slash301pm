# Slash 301 PM

Project management for a small creative agency: Brand > Campaign > Job, with the Brief at the centre and assets requested from a design and copy team.

- **New app** (PHP 8.3, SQLite, Datastar, Tailwind): `/slash301pm/`. Account Manager role first, then the other roles.
- **Legacy app** (React in the browser): `/slash301pm/legacy/`. It stays live until each area reaches parity. Its API is `api/api.php`.
- **Plan and decisions:** `docs/PLAN.md`, `docs/adr/`, `docs/audit.md`. Old docs: `docs/archive/`.

Local development (needs PHP 8.3):

- Run the app: `php -S 127.0.0.1:8301 tools/dev-router.php`
- Run the tests: `php tests/run.php` (one suite: `php tests/run.php integration`)
- Build the CSS (Tailwind CLI, output `public/css/app.css` is committed): `bash tools/build-css.sh`
- Before an SFTP deploy: `php tools/predeploy.php <live-commit> <new-commit>` prints the upload and delete checklist

Deploys are manual over SFTP. Demo mode stays on until the owner flips the beta gate (see the plan).
See `CLAUDE.md` for the working rules.

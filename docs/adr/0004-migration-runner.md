# ADR 0004: Migrations run from the web app, additive only

Status: accepted (Phase 0)

## Context
The owner deploys by SFTP and has no shell on the server. The live database (SQLite 3.34, so no `RETURNING`, `DROP COLUMN`, `STRICT` or reliable JSON1) is shared with the legacy React app, which has CHECK constraints it cannot survive being rebuilt around. The legacy `api/db.php` only creates tables on a brand new file, so schema changes need a new mechanism. A failed migration on live must not take the legacy app down.

## Decision
- Migration files live in `migrations/NNNN_snake_name.sql`, numbered, and are never edited after they ship. A fix is a new migration.
- While legacy lives, migrations are **additive only**: `CREATE TABLE/INDEX IF NOT EXISTS`, `ALTER TABLE ADD COLUMN` (nullable or constant default, no CHECK), new triggers, idempotent backfills. Never DROP, rename or rebuild a table. New values go in new columns.
- `tools/lint-sql.php` rejects SQL needing features newer than 3.34, and runs on every save.
- `MigrateGate` runs on the first request after a deploy:
  1. Compare `PRAGMA user_version` with the newest file; if current, continue.
  2. Take `flock(data/migrate.lock)`; if taken, answer 503 with Retry-After.
  3. Check free space is at least twice the database size, then `VACUUM INTO data/backups/pre-NNNN-<ts>.db`. Keep the last 10.
  4. Run `integrity_check` and `foreign_key_check` before and after.
  5. Apply each pending file in its own `BEGIN IMMEDIATE`; record `schema_migrations(version, name, sha256, applied_at, ms)`; set `user_version`.
  6. On failure, roll back, write `data/migrate-failed.json`, and show a maintenance page in the **new app only**.
- `0001_baseline` is the current `api/db.php` DDL, all `IF NOT EXISTS`, so it does nothing on live.
- Rehearsal is required before any migration PR: the owner downloads the live DB to `data/live-copy.db` (gitignored); `php tests/run.php migration` copies it, migrates it, and asserts row counts, backfills, trigger behaviour with a simulated legacy write, clean integrity checks and an idempotent second run.
- Rollback for the owner: upload the backup file over `data/slash301pm.db` by SFTP.
- `/admin/system` shows the migration level, backups, SQLite version and whether demo mode is on.

## Consequences
- Legacy keeps working through a failed migration, because nothing was removed.
- The first request after a deploy pays the migration time; backfills must be bounded so they cannot hit the shared host's time limit.
- Backups cost disk space on a shared plan; the two-times check refuses to run rather than fill the disk.
- Local SQLite is newer (3.45) than live (3.34), so passing locally is not enough; the lint is the guard.
- The "no rebuilds" rule means technical debt (the old `jobs.status` CHECK) stays until legacy is retired.

## Alternatives considered
- A separate `migrate.php` the owner opens by hand. Rejected: easy to forget after an SFTP deploy, and a half-deployed state would serve the new code on the old schema.
- Rebuilding tables in migrations for a clean schema. Rejected until legacy is retired (see Consequences).
- Keeping schema changes inside `api/db.php`. Rejected: it only runs for a new database file and has no record of what was applied.

---
name: sqlite-migration
description: How to write, lint and rehearse schema migrations for Slash 301 PM's live SQLite database (SQLite 3.34 on Xneelo, no shell access, legacy app sharing the same DB). Load before creating or reviewing anything in migrations/, app/Store/Migrator.php, or data backfills.
---

# SQLite migrations (live data, SFTP only)

## Facts that constrain every migration
- The server runs **SQLite 3.34.1** (PHP 8.4 FPM). Local PHP bundles 3.45, so SQL can pass locally and fail on live.
- Not available on the server: `RETURNING` (3.35), `ALTER TABLE DROP COLUMN` (3.35), `STRICT` tables (3.37), `unixepoch()` (3.38), the `->`/`->>` JSON operators (3.38). Treat JSON1 functions as unavailable; build JSON in PHP.
- `UPDATE ... FROM` exists from 3.33; allowed, but prefer a correlated subquery.
- `jobs.status`, `users.role`, `tasks.*` have CHECK constraints. Changing them needs a table rebuild, which the legacy React app (still live at /legacy/) cannot survive. So: **new values go in new columns**, never into a CHECKed column.
- The owner deploys by SFTP and has no shell. Migrations run from the web app (`MigrateGate`) on the first request after a deploy.

## Rules
1. Migration files: `migrations/NNNN_snake_name.sql`, numbered, never edited after they ship. A fix is a new migration.
2. Additive only while legacy lives: `CREATE TABLE IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`, `ALTER TABLE ... ADD COLUMN` (nullable, or a constant default, no CHECK, no REFERENCES with actions that legacy could trip), new triggers, `INSERT ... SELECT` / `UPDATE` backfills.
3. Never `DROP`, never rename a column or table, never rebuild a table.
4. Backfills are idempotent: `WHERE new_col IS NULL`, `INSERT ... SELECT ... WHERE NOT EXISTS (...)`.
5. Triggers that keep legacy and new columns in sync must not loop: guard with `WHEN NEW.x IS NOT OLD.x` and rely on recursive_triggers being off (the default).
6. Keep each file's work inside one transaction; the Migrator wraps it in BEGIN IMMEDIATE.
7. `tools/lint-sql.php <file>` must pass (it rejects the features above). The PostToolUse hook runs it on every save.
8. After adding a migration, run `php tools/gen-checksums.php` to append its sha256 to `migrations/CHECKSUMS.txt`. `tools/predeploy.php` fails if a file and its line differ, which is how an edited shipped migration is caught.

## What the Migrator does (MigrateGate)
1. `PRAGMA user_version` current → continue.
2. `flock(data/migrate.lock)`; if taken, 503 with Retry-After.
3. Check free space ≥ 2× DB size, then `VACUUM INTO 'data/backups/pre-NNNN-<ts>.db'`; keep the last 10.
4. `PRAGMA integrity_check` and `PRAGMA foreign_key_check` before and after.
5. Apply each pending file in BEGIN IMMEDIATE; record `schema_migrations(version, name, sha256, applied_at, ms)`; set `user_version`.
6. On failure: ROLLBACK, write `data/migrate-failed.json`, show a maintenance page in the new app only. Legacy keeps working because nothing was removed.
Rollback for the owner: upload the backup file over `data/slash301pm.db` by SFTP.

## Rehearsal (required before any migration PR)
- The owner downloads the live DB by SFTP to `data/live-copy.db` (gitignored, never committed or uploaded).
- `php tests/run.php migration` copies it to a temp file, runs all migrations, and asserts: row counts unchanged in existing tables, every new NOT NULL-in-practice column filled, triggers fire on a simulated legacy `UPDATE jobs SET status=...`, integrity checks clean, and a second run is a no-op.
- Also run the legacy smoke test against the migrated copy.

## Review questions
- Would the legacy app's existing INSERTs and UPDATEs still succeed after this migration?
- Is every statement valid on SQLite 3.34?
- Is the backfill idempotent and bounded (no full-table rewrite inside a request that could time out on shared hosting)?
- What does the owner do if this fails halfway on live?

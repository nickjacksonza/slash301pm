# ADR 0002: Job stages, mirrored to the legacy status column

Status: accepted (Phase 0)

## Context
`jobs.status` has a CHECK constraint with 14 values (`api/db.php`). The legacy React app reads and writes it directly and offers statuses the table rejects. SQLite 3.34 cannot alter a CHECK in place, so changing it means rebuilding the `jobs` table, which the live legacy app cannot survive. New workflow states for the brief-centred process (sent to Traffic, waiting on someone, cancelled with a reason) do not fit the existing values.

## Decision
- Add a `stage` column to `jobs` in migration 0002, with `stage_changed_at`, `waiting_on`, `waiting_reason`, `row_version`, `am_user_id` and `updated_by`. New values never go into a CHECKed column.
- `Domain/Stage` is the source of truth for the new app. Stages: draft, briefed, in_progress, waiting, on_hold, in_review, approved_internal, approved_client, done, archived, cancelled.
- The legacy `status` column is written alongside, so the legacy app keeps working:

| Stage | Legacy status |
|---|---|
| draft | Inbox / Brief |
| briefed | To Do |
| in_progress | In Progress / Today / This Week |
| waiting | Waiting |
| on_hold | On Hold |
| in_review | In Review |
| approved_internal | Approved (Internal) |
| approved_client | Approved (External) |
| done | Done |
| archived | Archived |
| cancelled | Cancelled |

- Triggers keep `stage` in sync when legacy writes `status`, and bump `row_version`. They guard with `WHEN NEW.x IS NOT OLD.x` so they cannot loop (recursive triggers stay off).
- There is no free status editing in the new app. An AM can: send a draft (only way from draft to briefed, needs Traffic assigned), recall a briefed job if no asset has started, move into and out of waiting or on hold, cancel with a reason, and archive a done or cancelled job.
- Approved statuses are reached only through the approval flow (the Phase 0 hotfix already blocks them via `update_job` and `batch`).

## Consequences
- Legacy and new app can both write the same rows; `row_version` makes the new app's grid detect a conflict instead of overwriting.
- Several legacy statuses collapse into one stage (Today and This Week into in_progress). The reverse mapping picks one canonical status, so a round trip can change `Today` to `In Progress`. Accepted.
- The legacy-only values the UI offers (Scheduled, Live) stay unsupported.
- When legacy is retired, `jobs` is rebuilt without the old CHECK and the mirror triggers are dropped (Later).
- The stage mapping needs unit tests in both directions, and a migration rehearsal on `data/live-copy.db` (every job gets a stage).

# Audit trail: which writes log activity

Every state-changing request in the new app writes a row in `activity` (migration 0006) in the same transaction as the change, with the actor taken from the session. The rule is enforced by `tests/integration/http/activity_coverage_test.php`: it reads every `POST`, `PUT`, `PATCH` and `DELETE` route from `app/routes.php` and fails unless the route is either covered (the test runs it and finds the expected row) or listed below as exempt with a reason. Adding a write route without updating the test and this page fails the suite.

## Covered routes

| Route | Verb written | Entity | Notes |
|---|---|---|---|
| `POST /briefs` | `job_created` | job | |
| `PATCH /jobs/{id}/brief` | `brief_edited` | brief | Autosave. One row per editing burst: the newest row of the job is brought up to date when it is the same person, verb and brief within 15 minutes (`data.fields`, `data.edits`, `data.first_at`). Anyone else's change in between starts a new row. |
| `POST /jobs/{id}/brief/assets` | `deliverable_added` | brief_asset | |
| `PATCH /jobs/{id}/brief/assets/{aid}` | `deliverable_updated` | brief_asset | Coalesced like autosave. |
| `POST /jobs/{id}/brief/assets/order` | `deliverables_reordered` | brief | |
| `DELETE /jobs/{id}/brief/assets/{aid}` | `deliverable_removed` | brief_asset | |
| `POST /jobs/{id}/assignments/{role}` | `assigned_to_job` / `unassigned_from_job` | job | |
| `POST /jobs/{id}/brief/send` | `brief_sent` (+ `deliverable_cancelled`, `started_asset_conflict`) | brief | |
| `POST /jobs/{id}/brief/update` | `brief_updated` (+ the same extras) | brief | |
| `PATCH /jobs/{id}/fields/{field}` | `brief_edited`, `job_waiting_updated`, `assigned_to_job` or `unassigned_from_job` | brief or job | Depends on the cell: brief fields, waiting fields or team slots. |
| `POST /jobs/{id}/transition` | the stage verb (`job_waiting`, `job_on_hold`, `job_resumed`, `brief_recalled`, `job_cancelled`, `job_archived`, `job_done`) | job | |
| `POST /jobs/{id}/move` | the stage verb, `data.via = board` or `grid` | job | |
| `POST /jobs/{id}/claim-am` | `assigned_to_job` (`data.claimed = true`) | job | |
| `POST /campaigns` | `campaign_created` | campaign | No job. |
| `POST /admin/users` | `user_created` | user | No job; data holds the username and role, never a user id, so these rows never reach anyone's My day. |
| `POST /admin/users/{id}/reset` | `password_reset` | user | The new password is never logged. |
| `POST /admin/users/{id}/deactivate` | `user_deactivated` | user | |
| `POST /admin/users/{id}/activate` | `user_activated` | user | |
| `POST /account/password` | `password_changed` | user | |

## Exempt routes

| Route | Why no activity row |
|---|---|
| `POST /today/seen` | Personal "last seen" stamp for My day (`user_seen`); changes no shared data. |
| `POST /login` | Sign-in. Failures are recorded in `login_attempts` for the rate limit; a success only starts a session. A bcrypt cost upgrade at sign-in is a system write without an actor. |
| `POST /logout` | Ends the session only. |
| `POST /demo-login` | Demo mode only (the owner's switch); swaps the session user and writes nothing. |
| `POST /admin/system/migrate` | Recorded in `schema_migrations` (version, sha256, applied_at) with a backup in `data/backups/`. |
| `POST /views` | Saved views are personal UI state (filters, sort, columns); no job data changes. |
| `PATCH /views/{id}` | Same as above. |
| `DELETE /views/{id}` | Same as above. |
| `POST /system/spike/method` | Diagnostics page (COO/ECD): echoes the method, writes nothing. |
| `PUT /system/spike/method` | Same as above. |
| `PATCH /system/spike/method` | Same as above. |
| `DELETE /system/spike/method` | Same as above. |

## Not covered by this rule

- The legacy app (`api/`) writes no activity rows; its changes show up in the new app only through the `jobs` triggers (stage sync, `row_version`). Legacy is retired area by area after the beta.
- Reads (`GET`) never write activity. `BriefHandlers::reconcile` may correct the `has_unsent_changes` flag during a read; that is derived state, not a change by a person.

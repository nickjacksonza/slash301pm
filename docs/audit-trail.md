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
| `POST /jobs/{id}/assignments/{role}` | `assigned_to_job` / `unassigned_from_job` (`social_assigned` for the Social slot) | job | |
| `POST /jobs/{id}/brief/send` | `brief_sent` (+ `deliverable_cancelled`, `started_asset_conflict`) | brief | |
| `POST /jobs/{id}/brief/update` | `brief_updated` (+ the same extras) | brief | |
| `PATCH /jobs/{id}/fields/{field}` | `brief_edited`, `job_waiting_updated`, `assigned_to_job` or `unassigned_from_job` | brief or job | Depends on the cell: brief fields, waiting fields or team slots. |
| `POST /jobs/{id}/transition` | the stage verb (`job_waiting`, `job_on_hold`, `job_resumed`, `brief_recalled`, `job_cancelled`, `job_archived`, `job_done`) | job | |
| `POST /jobs/{id}/move` | the stage verb, `data.via = board` or `grid` | job | |
| `POST /jobs/{id}/claim-am` | `assigned_to_job` (`data.claimed = true`) | job | |
| `POST /campaigns` | `campaign_created` | campaign | No job. |
| `POST /brands/logo` | `brand_logo_changed` | brand | No job. data: from, to (https link or empty). |
| `POST /jobs/{id}/assets/override` | `asset_status_overridden` | asset | Traffic, COO, ECD. data: kind asset, asset name, from, to, reason; recipients: asset assignee, AM (or brief creator), CD. Marked as an override in the activity feed; listed on `/admin/overrides`. |
| `POST /jobs/{id}/publications/override` | `asset_status_overridden` (+ `job_*` Social steps) | publication | As above with kind publication and the platform; the job's Social stage follows in either direction. |
| `POST /admin/system/demo-role-tasks` | `demo_role_tasks_added` | job | COO, demo mode only; one row per job that got the three role tasks; recipients: the assignees. |
| `POST /admin/users` | `user_created` | user | No job; data holds the username and role, never a user id, so these rows never reach anyone's My day. |
| `POST /admin/users/{id}/reset` | `password_reset` | user | The new password is never logged. |
| `POST /admin/users/{id}/deactivate` | `user_deactivated` | user | |
| `POST /admin/users/{id}/activate` | `user_activated` | user | |
| `POST /account/password` | `password_changed` | user | |
| `POST /social/jobs/{id}/ready` | `publication_ready` (`data.scope = brief`), plus the job's stage verb (`job_ready_to_schedule`, `data.via = social`) | job | Social publishing. N34: `data.recipients` = AM (or creator), PM and Producer slot holders. |
| `POST /social/assets/{aid}/platforms/{platform}` | `publication_added` | publication | A new post record (status `checking`). |
| `DELETE /social/publications/{pid}` | `publication_removed` | publication | Only while still being checked. |
| `PATCH /social/publications/{pid}/checklist` | `publication_checked` | publication | Coalesced per person and post within 5 minutes. |
| `PATCH /social/publications/{pid}/schedule` | `publication_rescheduled` | publication | |
| `PATCH /social/publications/{pid}/live-link` | `publication_link_added`, or `publication_link_changed` after Live | publication | After Live: N39 to Social on the job and the AM. |
| `PATCH /social/publications/{pid}/promoted` | `publication_promoted` | publication | N37 to the AM when the tick changes. |
| `POST /social/publications/{pid}/ready` | `publication_ready` (+ job stage verb when the job moves) | publication | N34. |
| `POST /social/publications/{pid}/scheduled` | `publication_scheduled` (+ `job_schedule`) | publication | N35 to the AM. |
| `POST /social/publications/{pid}/live` | `publication_live` (+ `job_go_live`) | publication | N36 to the AM; `data.live_url`. |
| `POST /social/publications/{pid}/archive` | `publication_archived` | publication | N38 to the AM; reason required. |
| `POST /social/publications/{pid}/reopen` | `publication_reopened` (+ `job_social_step_back`) | publication | N39 to Social on the job and the AM; reason required. |
| `POST /social/publications/{pid}/recheck` | `publication_rechecked` (+ `job_social_step_back`) | publication | Back to checking (the job's Producer, Social, COO, ECD); to Social on the job and the AM; reason required. |

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

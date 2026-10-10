# Roles in Slash 301 PM

Status: proposal for owner review. Written for the rebuild in `docs/PLAN.md`. It scopes all 14 roles so `Domain/Policy` tests, per-role "My day" views and Playwright workflows can be generated from it. The machine-readable copy of section 4 is `tests/fixtures/roles/policy-matrix.json` and the two must stay identical.

Sources read: `docs/PLAN.md`, `docs/audit.md`, `docs/adr/0002`, `docs/adr/0003`, `docs/archive/PLAN-v7.4.md` (roles 158-254, questions 877-888), `docs/archive/DEMO.md`, `api/permissions.php`, `api/api.php`, `api/db.php`, `src/constants.js`, `src/data.js`, `src/components/{operations,reviews,modals-brief,capacity,wiki}.js`, `app/Domain/{Role,Policy}.php`, and a read-only profile of `data/live-copy.db`.

**Live data facts that shape this (from `data/live-copy.db`)**

| Fact | Value | Why it matters |
|---|---|---|
| Users by role | COO 1, ECD 1, AM 1, Traffic 2, PM 2, Producer 1, CD 2, Copywriter 2, Designer 2, QA 1, Developer 1, SEO 1, Social 1, Client 20 | Every role has a real user, so every role needs a Policy row even if it is not in the beta |
| Clients per brand | exactly 2 for each of 10 brands | Matches DEMO.md: one Marketing Manager and one Brand Director per brand |
| job_assignments by role | CD, Client, Copywriter, Designer, ECD, PM, QA, Traffic: 18 each; AM, Producer, Developer, SEO, Social: 0 | No live job has an AM. "My jobs" for the AM would be empty after migration without a backfill (see Q10) |
| jobs.created_by | NULL on all 18 jobs | The "creator" fallback for the AM finds nothing on existing jobs |
| Tasks | 18 copy, 18 media, no qa | QA has never had a task; QA is advisory today |

## Terms used in this document

| Term | Meaning |
|---|---|
| allow | Any matching row |
| deny | Never; Policy returns a reason the UI shows |
| assigned | The actor has a `job_assignments` row on the job (any `role_on_job`), or is `assets.assigned_to` / `tasks.assigned_to` on an asset of the job. A condition can narrow it (for example "CD slot only") |
| creator | `briefs.created_by` (or `jobs.created_by` for legacy jobs) is the actor |
| assigned_or_creator | Either of the above. This is the plan's "who counts as the AM" rule, applied to AM, PM and Producer |
| own_brand | The job's campaign `brand_id` equals the Client's `users.brand_id` |
| active stages | briefed, in_progress, in_review, approved_internal, approved_client, ready_to_schedule, scheduled, live |
| workable stages | draft plus the active stages |
| open stages | workable plus waiting and on_hold |
| closed stages | done, archived, cancelled |
| Social stages | ready_to_schedule, scheduled, live. They sit between approved_client and done and all mirror to the legacy status Approved (External). Assets can carry them one by one |
| Makers | Copywriter, Designer, Developer, SEO, Social. They share one rule set for asset work; they differ only in asset types and home-screen filters. Social additionally publishes (section 2.13) |
| Account roles | AM, PM, Producer. They share one rule set on jobs they own (see Q6) |
| Admins | COO and ECD, as in `api/permissions.php:8` and `app/Domain/Policy.php` |
| Client-safe projection | What a Client may receive: title, campaign, client due date, go-live dates, deliverables (label, qty, channel, size, specs), mandatories, references, assets sent to them, their own brand's feedback. Never budget, hours, internal feedback, waiting reasons, team emails, version diffs, drafts |

## 1. Summary

| Role | Tier | Purpose in one line | Home screen in the new app | Beta wave |
|---|---|---|---|---|
| AM | Account | Owns the client relationship and the brief; sends it to Traffic, sends approved work to the client | `/today` (My day) | 1 |
| COO | Admin | Runs the agency; sees everything; user and system admin | `/today` with an agency-wide toggle, then `/jobs` all | 1 |
| ECD | Admin | Creative head; oversees all creative work; final internal approver and CD cover | `/today` (creative variant: review queue first) | 1 (admin), full use in 3 |
| Traffic | Manager | Receives every sent brief, assigns the CD and creatives, schedules and unblocks | `/traffic` (briefs needing a team, then due and overdue assets) | 2 |
| PM | Account | Runs delivery on jobs they own; can write briefs when there is no AM | `/today` (same sections as AM) | 2 |
| Producer | Account | As PM, for production-heavy jobs (shoots, video, print) | `/today` (same sections as AM) | 2 |
| CD | Creative lead | Leads the creative on assigned jobs and approves internally | `/reviews` (internal queue) | 3 |
| QA | Creative | Checks submitted work against the brief and specs before the CD | `/queue` (QA checks) | 3 |
| Client | Client | Reviews work sent to them, gives feedback, signs off (Brand Director) | `/portal` (awaiting my review) | 4 |
| Copywriter | Maker | Writes copy for assigned deliverables and submits it | `/queue` (my assets) | 5 |
| Designer | Maker | Produces design and media for assigned deliverables and submits it | `/queue` (my assets) | 5 |
| Developer | Maker | Builds web deliverables (landing pages, emails, banners) | `/queue` | 6 |
| SEO | Maker | SEO audits, metadata and copy optimisation deliverables | `/queue` | 6 |
| Social | Maker and publisher | After client approval: final check, Ready to Schedule, Scheduled, Live with links, Promoted flags | `/social` (queue of approved items) | 4b |

**Why this order** (the plan's "Later" list is reviews, client portal, creative queue, then Traffic and the rest; this changes it in two places):

| Wave | Roles | Reason |
|---|---|---|
| 1 | AM, COO, ECD | As planned. `app/Http/Middleware/BetaGate.php` already lets these three in. |
| 2 | Traffic, PM, Producer | Every brief the AM sends lands on Traffic, and the AM's sends are blocked until Traffic is assigned. Legacy Traffic sees only the mirrored legacy columns, so it cannot see versions, diffs, deliverable specs or "Brief updated to v1.2.0". PM and Producer need no new screens (they use the AM's grid, board and brief editor), and the plan switches off legacy brief creation after Phase 2, which would leave the 2 PMs and the Producer unable to create jobs at all if they stay on legacy. |
| 3 | CD (plus ECD review features), QA | Since the Phase 0 hotfix (audit H2, H7) legacy approvals do not persist on the server at all, so internal review is the most broken flow. CDs can review in the new app while makers still complete tasks in legacy, because the stage mirror turns their legacy status writes into stages. |
| 4 | Client (both personas) | Client approval in legacy is also not persisted (audit H7). Clients need the client-safe projection, which is easier to guarantee in a new, narrow `/portal` than by patching legacy. |
| 5 | Copywriter, Designer | They can keep working in legacy longest: task completion there still works and is mirrored. Moving them gives them the sent brief version and specs instead of legacy columns. |
| 4b | Social | Right after reviews (wave 3) and the client portal (wave 4) land. Social only touches a brief once the client has approved it, so there is nothing for Social to do until approvals persist on the server (audit H7). It needs the Social stages and `asset_publications`, so it is its own step rather than a copy of the Maker rules. Before 4b, Social stays in legacy, where the Scheduled and Live statuses are rejected anyway. |
| 6 | Developer, SEO | One user each on live and zero job assignments. Same Maker rules; switch on when the first job uses them. |

BetaGate reads `Config::newUiRoles`, so each wave is a config change plus the screens listed.

## 2. Roles in detail

Each section uses the same headings. Field names are the planned column names (`docs/PLAN.md` migrations 0002 to 0005). "Never sees" lists things the server must strip, not only the UI.

### 2.1 AM (Account Manager)

**Responsibilities**
- Takes the client's request and writes the brief: campaign, title, dates, creative direction, deliverables with specs, mandatories and references, budget and hours.
- Sends the brief to Traffic (v1.0.0) and sends versioned updates when the client changes something.
- Watches progress and dates on their jobs; chases the client for inputs (waiting on client).
- After internal approval, checks the work and sends it to the client; logs client feedback or approval received by email or call.
- Closes jobs (done, cancel, archive).

**Daily workflow**
1. Open `/today`. Read Overdue, then Due soon, then Waiting on me (jobs waiting on the AM, unsent drafts, briefs with unsent changes), then Changed by others.
2. For each item waiting on them: reply to the client, then resume the job or update the brief.
3. Create new briefs from client requests: pick or create a campaign, fill the brief (autosave), build deliverables from templates, add mandatories, references, budget, hours, choose Traffic.
4. Send to Traffic; fix anything the send dialog lists as missing.
5. For briefs with unsent changes, review the diff and choose "Send update" with the suggested bump.
6. (Wave 3 on) Open jobs that reached approved_internal, check against the sent brief, send to the client, or send back with a reason.
7. (Wave 4 on) Log client feedback or approval that arrived outside the portal, naming the contact.
8. End of day: grid filtered to "My jobs", sorted by due date; adjust dates (goes to working copy) and waiting reasons.

**Reads**

| Entity | Scope |
|---|---|
| Jobs, campaigns, brands | all (grid and board default to "My jobs") |
| Briefs, sent versions, diffs | all sent; drafts and working copies only where assigned_or_creator |
| Deliverables (brief_assets) and assets | all |
| Tasks, internal feedback, activity | all |
| Budget | assigned_or_creator |
| Hours | all |
| Capacity | all (read only) |
| Users | agency staff and all clients (names, emails) |
| Wiki | all |

**Creates**: jobs with briefs, campaigns, brief deliverables, brief versions (by sending), client feedback and client approval records on behalf of a named contact, waiting and on-hold records, comments (later).

**Edits**

| Entity | Fields | Scope |
|---|---|---|
| Brief (draft) | title, campaign_id, brief_date, due_date, first_go_live, last_go_live, creative_direction, mandatories, references_json, brief_pdf_url, server_link, budget, hours_estimate | assigned_or_creator |
| Brief (sent) | same fields, into the working copy; then Send update | assigned_or_creator |
| Deliverables | type/template, label, qty, channel, size/format, specs, copy_length, due_date, sort_order, add and remove lines | assigned_or_creator, through the brief |
| Job grid | title, campaign_id, description, due_date, brief_date, first_go_live, last_go_live, hours_estimate, budget (all brief-owned, so working copy after send), waiting_on, waiting_reason, sort_order | assigned_or_creator |
| Assignments | Traffic; AM, PM, Producer, Client contact slots; CD and creatives only while draft (as a suggestion) | assigned_or_creator |
| Assets, tasks | none (work belongs to makers, scheduling to Traffic) | |
| Campaigns | create, rename, close | all brands |
| Users | own password only | own |
| Wiki | create and edit | all |

**Stage transitions**

| From | To | Precondition |
|---|---|---|
| draft | briefed | Only by Send; BriefRules pass; Traffic assigned |
| briefed | draft | Recall; no asset started |
| workable | waiting | waiting_on and reason required |
| waiting | previous stage | Resume |
| workable | on_hold | Reason required |
| on_hold | previous stage | Resume |
| approved_internal | in_progress | Reason required (AM not satisfied, or client feedback logged) |
| approved_internal | approved_client | On behalf of a named client contact, with how they approved |
| approved_client | in_progress | Reason required |
| approved_client | done | |
| open | cancelled | Reason required; started assets kept and flagged |
| done, cancelled | archived | |
| done | in_progress | Reopen with reason |
| archived | done or cancelled | Restore |
| cancelled | draft | Reinstate |

**Approvals given**: client approval on behalf of a contact (recorded as such). No internal approval (Q1).

**Never sees**: drafts and working copies of briefs they neither created nor are assigned to; budget on other account people's jobs; `/admin/system`; other users' passwords or sessions.

**Notifications**

| Event | Trigger | Channel now / later | Urgency |
|---|---|---|---|
| N04 team_assigned | Traffic assigns the CD or first creative | in-app / email digest | normal |
| N09 started_asset_conflict | Their update would cancel a started asset | in-app (inline warning) | normal |
| N12 job_due_soon, N13 job_overdue | Daily date check | in-app / email digest | normal, high |
| N16 internal_approved | Job reaches approved_internal | in-app / email | high |
| N20 client_approved | Client signs off | in-app / email | normal |
| N21 client_feedback | Client gives feedback | in-app / email immediate | high |
| N23 job_waiting_on_you | A job is put on waiting with waiting_on = AM | in-app / email | high |
| N26 job_cancelled, N28 job_reopened | On their jobs, by someone else | in-app | normal |
| N29 brief_awaiting_traffic | Their brief has no team after 1 business day | in-app | normal (FYI copy) |

**My day needs**: Overdue (SAST), Due soon (today plus 3 business days), Waiting on me (waiting_on = me, unsent drafts, briefs with unsent changes, approved_internal jobs to send), Waiting on client (jobs where waiting_on = client, with days waiting), Changed by others since last visit, nav counts.

### 2.2 COO

**Responsibilities**
- Agency oversight across all brands; resolves escalations; covers any role.
- Manages users, brands, demo mode and the beta gate; reads `/admin/system`.

**Daily workflow**
1. Open `/today` with "All agency" on: overdue jobs, briefs waiting on Traffic over 1 business day, jobs waiting more than 3 days, client feedback not routed.
2. Open `/jobs` grouped by brand or AM to scan load.
3. Act on escalations (reassign Traffic, cancel, reopen).
4. Weekly: `/admin/system` (migration level, backups, demo banner) and `/admin/users`.
5. Workflow reviews: `/admin/overrides` lists every asset and post status override (who, job, from and to, reason) in a date range (owner decision 2026-10). While demo mode is on, `/admin/system` also offers "Add demo role tasks" (`add_demo_role_tasks`).

**Reads**: everything, all scopes. Budget all.

**Creates**: everything any role can create; users, brands.

**Edits**: every field any role can edit, every assignment; users (role, active, client_signoff, force reset); brands; wiki; demo mode.

**Stage transitions**: every transition in section 4. Approvals given on behalf of a client are recorded as on behalf.

**Approvals given**: internal (as cover for a CD), client on behalf.

**Never sees**: other users' passwords; nothing else is hidden.

**Notifications**: N13 job_overdue (digest), N26 job_cancelled (digest), N29 brief_awaiting_traffic (escalation), N20 client_approved (digest). Email later as a daily digest only; nothing urgent by default.

**My day needs**: the AM sections with an "All agency" toggle, plus Escalations (briefs without a team, feedback not routed, waiting over 3 days) and a system strip (demo mode on, last backup age).

### 2.3 ECD (Executive Creative Director)

**Responsibilities**
- Creative quality across all brands; covers or overrules any CD; approves internally.
- Admin tier (as the plan and `app/Domain/Policy.php`): can manage users and see `/admin/system`.

**Daily workflow**
1. Open `/today` (creative variant): Internal review queue across all jobs (oldest first), jobs with no CD, client feedback needing a creative response.
2. Review or delegate: approve, send back with feedback, or route to a CD.
3. Scan sent briefs of new jobs for creative direction (read only by habit; can edit).

**Reads**: everything, all scopes, budget included.

**Creates**: anything (briefs as cover), internal feedback, approvals, users.

**Edits**: everything; in practice creative_direction on briefs (working copy), assignments of CD, internal feedback.

**Stage transitions**: all. Typical: in_review to approved_internal, in_review to in_progress (send back).

**Approvals given**: internal on any job; client on behalf only in an emergency.

**Never sees**: passwords. Nothing else hidden.

**Notifications**: N14 submitted_for_internal_review when the job has no CD assigned (high), N21 client_feedback where CD is unassigned (high), N29 brief_awaiting_traffic (digest).

**My day needs**: All-agency internal review queue with age, jobs with no CD, overdue creative assets, client feedback awaiting creative action.

### 2.4 Traffic

**Responsibilities**
- First stop for every sent brief: reads it, assigns the CD and creatives (owner decision), confirms dates.
- Schedules assets, balances capacity, unblocks waiting work, routes feedback.
- Marks approved jobs done and archives.

**Daily workflow**
1. Open `/traffic`: "Needs a team" (briefed jobs without CD or creatives, oldest first), "Brief updated" (new versions on jobs they hold, with diff).
2. For each new brief: read v1.0.0, check deliverables and dates, assign CD and creatives (and per-asset assignees), move to in_progress (or let the first maker start it).
3. Check "Due soon / overdue assets"; change asset due dates, reassign, or put the job on waiting.
4. Route client feedback that came back to the right maker.
5. Mark approved_client jobs as done; archive done and cancelled jobs.
6. Crunch time and crises (owner decision 2026-10): the job sheet lists every asset; `GET /jobs/{id}/assets` shows every deliverable, asset and Social post, and Traffic (like the COO and the ECD) can override any asset or post status with a reason (`override_asset_status`). Each override is logged and marked as an override in the activity feed. After the send Traffic also fills the Developer, SEO and Producer slots (`assign_task_roles`).

**Reads**

| Entity | Scope |
|---|---|
| Jobs, campaigns, brands | all |
| Briefs | sent versions only, all jobs (no drafts, no working copies) |
| Deliverables, assets, tasks | all |
| Hours | all |
| Budget | deny |
| Capacity | all |
| Internal feedback, activity | all |
| Wiki | all |

**Creates**: assignments, tasks (later), waiting and on-hold records, feedback routing, internal feedback comments.

**Edits**

| Entity | Fields |
|---|---|
| Assignments | Traffic, CD, Copywriter, Designer, QA, Developer, SEO, Social slots; assets.assigned_to |
| Assets | due_date (internal), sort_order (later) |
| Tasks | create, split, reorder (later) |
| Job grid | waiting_on, waiting_reason, sort_order |
| Brief | none (they ask the AM for an update) |

**Stage transitions**

| From | To | Precondition |
|---|---|---|
| briefed | in_progress | CD or a creative assigned |
| in_progress | in_review | All non-cancelled assets submitted (normally automatic) |
| active | waiting, on_hold | Reason required; not from draft |
| waiting, on_hold | previous stage | |
| approved_client | done | |
| done, cancelled | archived | |

**Approvals given**: none.

**Never sees**: brief drafts and working copies, budget, client emails beyond what is needed to name a contact (agency-internal list only).

**Notifications**

| Event | Trigger | Urgency |
|---|---|---|
| N01 brief_sent | AM sends v1.0.0 | high |
| N02 brief_updated | New version on a job they hold (major high, minor normal, patch low) | varies |
| N03 brief_recalled | AM recalls | high |
| N08 deliverable_cancelled | A brief update cancelled an unstarted asset | normal |
| N11 asset_overdue | Daily | high |
| N17 internal_sent_back | CD sends back | normal |
| N21 client_feedback | Client feedback on their jobs | normal |
| N23 job_waiting_on_you | waiting_on = Traffic user | high |
| N25 job_on_hold, N26 job_cancelled, N28 job_reopened | On their jobs | normal |
| N29 brief_awaiting_traffic | Brief without a team after 1 business day | high |

All in-app now; email later (immediate for N01, N03; digest for the rest).

**My day needs**: Needs a team, Brief updated since last visit, Assets due today and tomorrow by person, Overdue assets, Waiting on me, Ready to close (approved_client), capacity strip for the week.

### 2.5 PM (Project Manager)

**Responsibilities**
- Runs delivery on jobs they own (assigned in the PM slot, or they created the brief).
- Writes and sends briefs when there is no AM on the account (demo and live: 2 PMs, 1 AM).
- Same rule set as the AM on owned jobs; additionally starts work and splits tasks.

**Daily workflow**
1. Open `/today` (AM sections, filtered to jobs where they are PM or creator).
2. Act on Waiting on me and overdue items.
3. Create or update briefs as the AM would.
4. On jobs they own: start work, put on waiting, adjust asset due dates with Traffic.
5. Log client feedback received by email.

**Reads**: as AM (all jobs, sent briefs all, drafts and budget where assigned_or_creator, hours all, capacity all).

**Creates**: as AM, plus tasks on owned jobs (later).

**Edits**: as AM on assigned_or_creator jobs; plus assets due_date and tasks on owned jobs (later).

**Stage transitions**: as AM, plus briefed to in_progress and in_progress to in_review on owned jobs.

**Approvals given**: client approval on behalf (recorded). No internal approval.

**Never sees**: drafts and budget on jobs they do not own; admin pages.

**Notifications**: as AM for owned jobs (N04, N12, N13, N16, N20, N21, N23, N26, N28).

**My day needs**: identical to AM, with "jobs I own" defined as PM slot or creator.

### 2.6 Producer

**Responsibilities**: as PM, for production-heavy work (shoots, video, print, suppliers). Live: 1 user, 0 job assignments.

**Daily workflow**: as PM; the home screen filter defaults to jobs where they hold the Producer slot.

**Reads, creates, edits, transitions, approvals, never sees, notifications, My day**: identical to PM, keyed on the Producer slot instead of the PM slot. Recommendation (Q6): keep the role label, share the Policy rules; add supplier and cost fields later only if the owner asks.

**Asset test reports (owner decision 2026-10)**: the "Asset test report" template (`asset-test-report`) defaults to the job's Producer: when a brief with that deliverable is sent, its assets are assigned to the Producer slot holder (unassigned when the slot is empty). On their own jobs the Producer may tick or note the Social checklist's Test result item (`social_edit_test_result`) and send a post back to checking with a reason (`social_set_checking`); the rest of the checklist and the other Social moves stay with Social, the COO and the ECD.

### 2.7 CD (Creative Director)

**Responsibilities**
- Leads the creative on jobs where they hold the CD slot: interprets the brief, guides makers, reviews and approves internally.
- Actions client feedback creatively and resubmits.
- Can also make work (design or copy) on their jobs.

**Daily workflow**
1. Open `/reviews`: jobs and assets in review on their jobs (oldest first), each with the brief version it was made against.
2. For each: compare to the sent brief and specs; approve (records version checked), or send back with feedback per asset.
3. Check "Client feedback for me" and route or action it.
4. Open "My jobs" for briefs updated since last visit (diff) and brief questions.
5. Do any assets assigned to themselves.

**Reads**

| Entity | Scope |
|---|---|
| Jobs, sent briefs, deliverables, assets | all (read only outside their jobs; `src/constants.js:131` canViewAll true today) |
| Brief drafts, working copies | deny |
| Hours | assigned |
| Budget | deny |
| Internal feedback, activity | all |
| Capacity | all (read) |

**Creates**: internal feedback, approvals, feedback routing, tasks on their jobs (later), comments (later).

**Edits**

| Entity | Fields | Scope |
|---|---|---|
| Assets and tasks | content, file_url, file_type, character_count, working status | CD slot on the job |
| Job grid | waiting_on, waiting_reason | assigned |
| Brief | none; asks the AM for an update (Q9 covers creative_direction) | |
| Assignments | none (Traffic assigns; Q9) | |

**Stage transitions**

| From | To | Precondition |
|---|---|---|
| briefed | in_progress | Assigned |
| in_progress | in_review | All non-cancelled assets submitted |
| in_review | in_progress | Feedback text required |
| in_review | approved_internal | CD slot on the job; records brief version |
| approved_internal | in_progress | Reason required |
| active | waiting and back | Reason required |

**Approvals given**: internal approval on jobs where they hold the CD slot.

**Never sees**: budget, brief drafts, working copies, other clients' contact emails, admin pages.

**Notifications**: N02 brief_updated (on their jobs), N05 assigned_to_job, N11 asset_overdue (their jobs), N14 submitted_for_internal_review (high), N15 qa_result, N21 client_feedback (high), N22 feedback_routed, N23 job_waiting_on_you.

**My day needs**: Review queue with age and version, Client feedback for me, Assets on my jobs due today or overdue, Briefs updated since last visit.

### 2.8 Copywriter (Maker)

**Responsibilities**: writes copy for assets assigned to them, to the sent brief's specs (copy length, channel, mandatories); submits for review; actions feedback.

**Daily workflow**
1. Open `/queue`: my assets grouped Due today, Due soon, Sent back to me, Not started.
2. Open an asset: the sent brief (latest version), the deliverable's specs, any feedback.
3. Write, save, submit for review.
4. Check "Brief updated" notices; reread the diff before continuing.

**Reads**

| Entity | Scope |
|---|---|
| Jobs, sent briefs, deliverables | assigned |
| Assets, tasks | assigned jobs (edit only their own) |
| Internal feedback | assigned |
| Hours | assigned (their asset estimate) |
| Capacity | own row |
| Wiki | all |

**Creates**: asset content, submissions, comments (later).

**Edits**: on assets or tasks where `assigned_to` = them: content, character_count, file_url, file_type, working status (Not Started, In Progress, Waiting). Nothing on jobs or briefs.

**Stage transitions**: implicit only. Starting their first assigned asset moves a briefed job to in_progress; submitting the last required asset moves it to in_review.

**Approvals given**: none.

**Never sees**: unassigned jobs, budget, brief drafts and working copies, client emails, other people's capacity, admin pages.

**Notifications**: N02 brief_updated, N05 assigned_to_job, N06 unassigned_from_job, N07 asset_assigned, N08 deliverable_cancelled, N10 asset_due_soon, N11 asset_overdue, N17 internal_sent_back (high), N22 feedback_routed (high), N03 brief_recalled, N25 job_on_hold, N26 job_cancelled.

**My day needs**: Due today, Due soon, Sent back to me, Brief changed on my jobs, Not started.

### 2.9 Designer (Maker)

Same as Copywriter, with media deliverables (sizes, formats, file links) and the "media" task type. Same reads, edits, transitions, notifications and My day.

### 2.10 QA

**Responsibilities**: checks submitted assets against the sent brief, deliverable specs and mandatories before the CD approves; records pass or fail with notes.

**Daily workflow**
1. Open `/queue` (QA view): submitted assets on jobs where they hold the QA slot.
2. Check each against the spec list; record pass, or fail with notes (which notifies the CD and the maker).
3. Send a job back if it fails a hard requirement.

**Reads**: assigned jobs, sent briefs, deliverables, assets, internal feedback, hours; capacity own row.

**Creates**: QA results, internal feedback.

**Edits**: QA result fields only (later); no asset content.

**Stage transitions**: in_review to in_progress (send back, feedback required), on assigned jobs.

**Approvals given**: QA pass (advisory, Q13). Never internal approval.

**Never sees**: unassigned jobs, budget, drafts, client emails.

**Notifications**: N05 assigned_to_job, N14 submitted_for_internal_review (high), N02 brief_updated.

**My day needs**: Waiting for QA, Failed and resubmitted, Brief changed on my jobs.

### 2.11 Developer, 2.12 SEO (Makers)

Same as Copywriter. Differences are only asset types and home filters:

| Role | Typical deliverables | Extra field later |
|---|---|---|
| Developer | Landing pages, HTML emails, banners | staging_url |
| SEO | Audits, metadata, keyword sets, copy optimisation | report_url |

Not in the beta (Q7). Social is a Maker too but has its own section because it publishes.

**Default tasks (owner decision 2026-10)**: the "UTM link generation" template (`utm-links`) defaults to the job's Developer and "Campaign hashtags" (`campaign-hashtags`) to the job's SEO: when a brief with those deliverables is sent, their assets are assigned to the slot holder (unassigned when the slot is empty). Traffic and the job's owners fill these slots after the send (`assign_task_roles`). Like every assigned-only role, their grid and board filter lists hold only the brands and campaigns of their jobs (no Owner filter), and My day starts with a row of brand buttons (logo or coloured initials, with job counts) that filters every section.

### 2.13 Social (Publisher)

Social is an important role, but only after a brief and its assets are approved by the client. Before that Social has no write access and the brief is not in the Social queue.

**Responsibilities**: do the final check that everything needed to schedule each post is there (copy, image, link, hashtags, test result); set the brief, or individual assets in it, to Ready to Schedule (this notifies the AM), then Scheduled, then Live (adding the live link of each post by hand); tick Promoted per platform when a post was boosted as paid media; later, archive or delete posts.

**Daily workflow**
1. Open `/social`: Approved, to check; Ready to schedule; Scheduled today; Live without a link.
2. Open an approved item: the sent brief version, the approved asset, and the checklist per platform (copy, image, link, hashtags, test result).
3. Tick the checklist. If something is missing, comment and mention the AM or the maker; the item stays where it is. Social does not edit the creative.
4. All items complete: set Ready to Schedule (brief or selected assets). The AM is notified.
5. After scheduling in the platform tool: set the scheduled date per platform and move to Scheduled.
6. When a post goes out: paste the live link per platform and move to Live. Tick Promoted where it was boosted.
7. Watch for changes after Live (see notifications).

**Reads** (recommendation: the queue shows all approved social-channel deliverables, writes need the Social slot on the job)

| Entity | Scope |
|---|---|
| Social queue | Every asset on a social channel (deliverable channel in the platform list, Q21) whose job is approved_client or later |
| Jobs, sent briefs, deliverables | assigned jobs in full; queue items show the client-safe summary plus copy, image, link, hashtags |
| Assets | approved ones in the queue; own assigned assets in full |
| Publication records | all in the queue |
| Internal feedback | assigned |
| Hours | assigned |
| Capacity | own row |
| Wiki | all |

**Creates**: asset_publications rows (one per asset and platform), checklist entries, comments (later).

**Edits**

| Entity | Fields | Scope |
|---|---|---|
| Publication record | checklist (copy, image, link, hashtags, test result), scheduled_at, live_url, promoted, promoted note, status | Social slot on the job, or assets assigned to them |
| Asset or brief status | ready_to_schedule, scheduled, live (later archived) | same |
| Jobs, briefs, budget, assignments | none | |

**Stage transitions**

| From | To | Precondition |
|---|---|---|
| approved_client | ready_to_schedule | Checklist complete for every asset in scope. Notifies the AM |
| ready_to_schedule | scheduled | scheduled_at set per platform |
| scheduled | live | Live link entered per platform |
| any of the three | back one step | Not allowed in the first release; ask the AM to reopen (Q22) |

Brief-level stage moves when every social asset in scope reaches that status, or Social sets the brief directly. Assets can move one by one.

**Approvals given**: none. Social checks completeness; it does not approve creative.

**Never sees**: budget, brief drafts and working copies before client approval, client emails, jobs that are not approved, admin pages.

**Notifications**: N33 client_approved_ready_for_social (high), N30 mention, N02 brief_updated, N39 changes after Live, N28 job_reopened, N26 job_cancelled.

**My day needs** (`/social`): Approved items to check (the queue), Scheduled today, Live without a link, Changed after Live.

### 2.14 Client

**Recommendation on sub-roles**: keep one `Client` role (adding a role value needs a `users` table rebuild because of the CHECK at `api/db.php:70`). Add an additive column `users.client_signoff INTEGER NOT NULL DEFAULT 0`.

| Persona | client_signoff | Can |
|---|---|---|
| Marketing Manager (day-to-day) | 0 | See own-brand client-visible jobs, give feedback, "recommend for sign-off" (notifies sign-off contacts) |
| Brand Director (final approval) | 1 | All of the above, plus approve |

Backfill every existing Client to 1 so nothing changes on day one (today any own-brand Client can approve, `api/permissions.php:123-128`); the owner then turns it off for Marketing Managers on brands that want Director sign-off. The job's single `Client` slot (`api/db.php:146` UNIQUE per role) stays the day-to-day contact who gets notifications.

**Responsibilities**: review work sent to them, give feedback, sign off (Brand Director).

**Daily workflow**
1. Follow the email link (later) or open `/portal`: "Awaiting my review".
2. Open a job: the assets sent, the client-safe brief summary, due and go-live dates.
3. Approve (sign-off contacts) or give feedback; Marketing Managers can "recommend for sign-off".
4. Check "In production" (coarse list of their brand's jobs and due dates) and "Approved" history.

**Reads**

| Entity | Scope |
|---|---|
| Jobs | own_brand, client-safe projection; before send_to_client only a coarse "In production" line |
| Brief | own_brand, client-safe projection of the latest sent version |
| Assets | own_brand, only those sent to them |
| Feedback | own brand's client feedback; never internal feedback |
| Users | own brand's contacts; agency staff names only (no emails), as hotfixed in `api/api.php:363-400` |
| Wiki | pages linked to own-brand jobs or campaigns |

**Creates**: client feedback, client approvals, "recommend for sign-off".

**Edits**: own password; nothing else.

**Stage transitions**: implicit only. Feedback moves approved_internal to in_progress; approval (sign-off contacts) moves approved_internal to approved_client.

**Approvals given**: client approval (client_signoff = 1 only).

**Never sees**: other brands, drafts, working copies, version diffs, budget, hours, internal feedback, waiting reasons, team emails and usernames, assignments, activity log, capacity, any `/jobs` or `/admin` screen.

**Notifications** (email matters most for clients; in-app is a fallback)

| Event | Trigger | Urgency |
|---|---|---|
| N18 sent_to_client | AM sends work | normal |
| N19 client_ready_for_signoff | Marketing Manager recommends (sign-off contacts only) | normal |
| N28 job_reopened | Only when the AM reopens work they had approved | low |

**My day needs** (`/portal`): Awaiting my review, Awaiting Director sign-off (MM view), In production (title, due date), Recently approved.

## 3. Notification catalogue

Rules for every event: the actor never notifies themselves; recipients are resolved at the time of the event from `job_assignments` and `assets.assigned_to`; a Client only ever receives events marked Client; every notification is written in the same transaction as the change (activity log, migration 0006) and rendered from it, so "in-app now" needs no extra table beyond a per-user read marker. Email is "later" in every row (ADR 0003) and listed as the intended mode.

Urgency: **high** = blocks work until the recipient acts (badge plus top of My day); **normal** = appears in My day and the bell; **low** = bell only, never a badge.

| ID | Event | Trigger | Recipients | Why | Urgency | Email later | Phase |
|---|---|---|---|---|---|---|---|
| N01 | brief_sent | send_brief creates v1.0.0 | Traffic on the job; CD and creatives the AM pre-selected (FYI) | Traffic must assign the team | high (Traffic), low (others) | immediate | beta |
| N02 | brief_updated vX.Y.Z | send_brief_update | Every agency assignee on the job; asset assignees | They work from the sent version; the notice shows the change note and diff | major high, minor normal, patch low | major and minor immediate, patch none | beta |
| N03 | brief_recalled | recall_brief | Traffic and every assignee | Stop planning work on it | high | immediate | beta |
| N04 | team_assigned | First CD or creative assigned on a briefed job | AM slot holder or creator | Know it is moving | normal | digest | beta |
| N05 | assigned_to_job | job_assignments row added | The assignee | New work | normal; high if due within 3 business days | immediate | beta |
| N06 | unassigned_from_job | job_assignments row removed | The person removed | Stop work | normal | digest | beta |
| N07 | asset_assigned | assets.assigned_to set or changed | New assignee (and old one, as N06) | New deliverable | normal | digest | beta |
| N08 | deliverable_cancelled | A brief update cancels an unstarted asset | Asset assignee, Traffic | Do not start it | normal | digest | beta |
| N09 | started_asset_conflict | A brief update would reduce a line that has started assets | The sender (inline warning), Traffic | Started assets are never cancelled; someone decides | normal | none | beta |
| N10 | asset_due_soon | Daily 07:00 SAST: asset due within 1 business day, not submitted | Asset assignee | Plan the day | normal | digest | later |
| N11 | asset_overdue | Daily 07:00 SAST: asset past due, not submitted | Asset assignee, Traffic, CD on the job | Recover the date | high | digest | later |
| N12 | job_due_soon | Job due today or within 3 business days, not yet approved_client (or later) | AM (or creator), Traffic | Chase approvals | normal (My day section, no push) | digest | beta |
| N13 | job_overdue | Job past due, not yet approved_client (or later) | AM (or creator), Traffic; COO in digest | Escalate | high | digest | beta |
| N14 | submitted_for_internal_review | Job enters in_review, or an asset is submitted | CD on the job (ECD if none), QA on the job | Review is waiting | high | immediate | reviews |
| N15 | qa_result | qa_signoff recorded as fail | CD on the job, asset assignee | Fix before approval | normal | digest | reviews |
| N16 | internal_approved | in_review to approved_internal | AM (or creator) high; Traffic and makers low | AM sends to the client | high (AM) | immediate (AM) | reviews |
| N17 | internal_sent_back | in_review to in_progress with feedback | Asset assignees named in the feedback (all makers if none named), Traffic | Rework | high | immediate | reviews |
| N18 | sent_to_client | send_to_client | Client contact on the job; sign-off contacts of the brand | Review needed | normal | immediate (main channel for clients) | client_portal |
| N19 | client_ready_for_signoff | recommend_signoff | Brand contacts with client_signoff = 1; AM | Final sign-off | normal | immediate | client_portal |
| N20 | client_approved | approve_client (or on behalf) | AM, Traffic, CD; makers low; COO digest | Close out and schedule | normal | digest | client_portal |
| N21 | client_feedback | give_feedback_client (or on behalf) | AM high; CD high; Traffic normal | Triage and route the changes | high | immediate | client_portal |
| N22 | feedback_routed | route_feedback | The person it is routed to | Action it | high | immediate | reviews |
| N23 | job_waiting_on_you | Job enters waiting with waiting_on = a user | That user | They are the blocker | high | immediate | beta |
| N24 | job_resumed | waiting or on_hold to the previous stage | Every assignee | Work can continue | normal | digest | beta |
| N25 | job_on_hold | workable to on_hold | Every assignee, Traffic | Pause work | normal | digest | beta |
| N26 | job_cancelled | open to cancelled | Every assignee, Traffic; COO digest | Stop work; started assets are listed | normal | immediate | beta |
| N27 | job_done | approved_client to done | AM, Traffic | Record closed | low | none | beta |
| N28 | job_reopened | done or approved_client to in_progress, cancelled to draft, archived restored | Every assignee, Traffic; Client contact only if they had approved | Work is back | high | immediate | beta |
| N29 | brief_awaiting_traffic | Job briefed with no CD or creative after 1 business day | Traffic high; COO high; AM normal | Escalation | high | immediate | beta |
| N30 | mention | @name in a comment | The mentioned user | Direct question | normal | immediate | later |
| N31 | comment_on_my_job | Comment on a job you are assigned to | Assignees (opt out per job) | Awareness | low | digest | later |
| N32 | account_access | User created, password reset forced | The user | They cannot log in yet, so in-app is useless | high | email only | later |
| N33 | client_approved_ready_for_social | approve_client (or on behalf) on a brief with social assets, or an individual social asset approved | Social on the job; Social queue (all Social users) | Final check and scheduling can start | high | immediate | social |
| N34 | ready_to_schedule | Brief or assets set to Ready to Schedule | AM (or creator); PM or Producer owner | Know it is ready and can tell the client | normal | digest | social |
| N35 | scheduled | Brief or assets set to Scheduled | AM | Know the dates | normal | digest | social |
| N36 | live | Brief or assets set to Live, with the live links | AM; Client contacts later (links only) | Share the links, close out | normal | immediate (AM) | social |
| N37 | promoted_flag_changed | Promoted ticked or unticked on a platform | AM | Paid media records and reporting | low | digest | social |
| N38 | post_archived_or_deleted | Post publication record archived or deleted | AM | A live post is gone or hidden | normal | digest | social |
| N39 | changed_after_live | Brief updated, live link edited or asset reopened after Live | Social on the job; AM | A live post may need editing | high | immediate | social |

## 4. Permission matrix

Machine-readable copy: `tests/fixtures/roles/policy-matrix.json` (generated from the same table; the JSON uses the full value names). Each row is one `Policy` function. A cell is necessary but not sufficient: the stage preconditions in the conditions table and the read rules (`view_*`) must also pass, and every actor and `*_by` value comes from the session.

Legend: **Y** allow, **-** deny, **A** assigned, **C** creator, **AC** assigned_or_creator, **B** own_brand. A `*` after the action means it has conditions (listed after the tables). Columns: COO, ECD, AM, Trf = Traffic, PM, Prd = Producer, CD, Cpy = Copywriter, Dsg = Designer, QA, Dev = Developer, SEO, Soc = Social, Cli = Client.

**Main differences from legacy, and why** (row-level detail in "Where legacy differs" below)

| Area | Legacy (`api/permissions.php`, `src/constants.js`) | Recommendation | Why |
|---|---|---|---|
| Manager tier | AM, PM, Traffic, Producer can edit any job, asset and task (`api/permissions.php:85-107`) | Account roles edit only jobs they own; Traffic edits only assignments, schedule and waiting | "Managed" was never defined (PLAN-v7.4 Q6); field-level rules close audit M8 |
| Traffic | Manager on the server, `admin` in the UI (`src/constants.js:87-88`) | Manager: owns resourcing, no brief edits, no budget | Owner decision: AM writes, Traffic assigns |
| Status | Free status editing except the two Approved values (audit H2 hotfix) | Named transitions only, each with a role rule and a precondition | ADR 0002 |
| Internal approval | Any CD or admin (`api/permissions.php:118-121`) | CD slot on the job, ECD, COO | G8 |
| Client feedback | Any manager, no on-behalf record (`api/permissions.php:130-136`) | Client of the brand, or AM, PM, Producer, admins on behalf of a named contact | G10 |
| Client approval | Any own-brand Client or admin | Own-brand Client with `client_signoff = 1`, or on behalf | Q3 |
| Users | COO only | COO and ECD | Approved plan; Q17 |
| Visibility | Creatives list every job; detail refused (audit M7) | CD reads all; makers and QA assigned only | G7 |

#### Brief

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `create_brief` | Y | Y | Y | - | Y | Y | - | - | - | - | - | - | - | - |
| `view_brief_draft` | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `view_brief_sent` * | Y | Y | Y | Y | Y | Y | Y | A | A | A | A | A | A | B |
| `edit_brief_draft` | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_brief_sent` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `send_brief` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `send_brief_update` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `recall_brief` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |

#### Assignments

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `assign_traffic` | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `assign_creatives` * | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `assign_account_roles` | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `assign_task_roles` * | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |

#### Assets and tasks

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `manage_tasks` | Y | Y | - | Y | AC | AC | A | - | - | - | - | - | - | - |
| `edit_asset_schedule` | Y | Y | - | Y | AC | AC | - | - | - | - | - | - | - | - |
| `edit_asset_work` * | Y | Y | - | - | - | - | A | A | A | - | A | A | A | - |
| `view_all_assets` * | Y | Y | - | Y | - | - | - | - | - | - | - | - | - | - |
| `override_asset_status` * | Y | Y | - | Y | - | - | - | - | - | - | - | - | - | - |

#### Job fields

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `edit_job_field:title` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:campaign_id` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:description` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:due_date` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:brief_date` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:first_go_live` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:last_go_live` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:hours_estimate` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:budget` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `edit_job_field:waiting_on` * | Y | Y | AC | Y | AC | AC | A | - | - | - | - | - | - | - |
| `edit_job_field:waiting_reason` * | Y | Y | AC | Y | AC | AC | A | - | - | - | - | - | - | - |
| `edit_job_field:sort_order` | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |

#### Transitions

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `transition:draft->briefed` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `transition:briefed->draft` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `transition:briefed->in_progress` * | Y | Y | - | Y | AC | AC | A | A | A | - | A | A | A | - |
| `transition:in_progress->in_review` * | Y | Y | - | Y | AC | AC | A | A | A | - | A | A | A | - |
| `transition:in_review->in_progress` * | Y | Y | - | - | - | - | A | - | - | A | - | - | - | - |
| `transition:in_review->approved_internal` * | Y | Y | - | - | - | - | A | - | - | - | - | - | - | - |
| `transition:approved_internal->in_progress` * | Y | Y | AC | - | AC | AC | A | - | - | - | - | - | - | B |
| `transition:approved_internal->approved_client` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | B |
| `transition:approved_client->in_progress` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `transition:approved_client->done` | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `transition:approved_client->ready_to_schedule` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `transition:ready_to_schedule->scheduled` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `transition:scheduled->live` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `transition:live->done` * | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `transition:workable->waiting` * | Y | Y | AC | Y | AC | AC | A | - | - | - | - | - | - | - |
| `transition:waiting->resume` * | Y | Y | AC | Y | AC | AC | A | - | - | - | - | - | - | - |
| `transition:workable->on_hold` * | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `transition:on_hold->resume` | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `transition:open->cancelled` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `transition:done->archived` | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `transition:cancelled->archived` | Y | Y | AC | Y | AC | AC | - | - | - | - | - | - | - | - |
| `transition:done->in_progress` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `transition:archived->restore` | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `transition:cancelled->draft` | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |

#### Reviews

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `submit_for_review` * | Y | Y | - | - | - | - | A | A | A | - | A | A | A | - |
| `qa_signoff` | Y | Y | - | - | - | - | - | - | - | A | - | - | - | - |
| `approve_internal` * | Y | Y | - | - | - | - | A | - | - | - | - | - | - | - |
| `give_feedback_internal` | Y | Y | AC | Y | AC | AC | A | - | - | A | - | - | - | - |
| `route_feedback` | Y | Y | AC | Y | AC | AC | A | - | - | - | - | - | - | - |
| `send_to_client` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `approve_client` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | B |
| `give_feedback_client` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | B |
| `recommend_signoff` * | - | - | - | - | - | - | - | - | - | - | - | - | - | B |
| `comment` | Y | Y | Y | Y | Y | Y | Y | A | A | A | A | A | A | - |

#### Organisation and visibility

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `manage_campaign` | Y | Y | Y | - | Y | Y | - | - | - | - | - | - | - | - |
| `manage_brand` | Y | Y | - | - | - | - | - | - | - | - | - | - | - | - |
| `manage_users` | Y | Y | - | - | - | - | - | - | - | - | - | - | - | - |
| `admin_system` | Y | Y | - | - | - | - | - | - | - | - | - | - | - | - |
| `toggle_demo_mode` | Y | - | - | - | - | - | - | - | - | - | - | - | - | - |
| `manage_brand_logo` * | Y | Y | Y | - | Y | Y | - | - | - | - | - | - | - | - |
| `view_overrides_report` | Y | - | - | - | - | - | - | - | - | - | - | - | - | - |
| `add_demo_role_tasks` * | Y | - | - | - | - | - | - | - | - | - | - | - | - | - |
| `view_all_jobs` * | Y | Y | Y | Y | Y | Y | Y | A | A | A | A | A | A | B |
| `view_budget` | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | - | - |
| `view_hours` | Y | Y | Y | Y | Y | Y | A | A | A | A | A | A | A | - |
| `view_internal_feedback` | Y | Y | Y | Y | Y | Y | Y | A | A | A | A | A | A | - |
| `view_staff_emails` * | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | - |
| `view_capacity` | Y | Y | Y | Y | Y | Y | Y | A | A | A | A | A | A | - |
| `view_wiki` * | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | B |
| `edit_wiki` | Y | Y | Y | Y | Y | Y | - | - | - | - | - | - | - | - |

#### Social publishing

Applies from stage approved_client. Asset-level moves use the same actions on a single asset, so there are no separate asset rows. Social "A" here means the Social slot on the job or an asset assigned to them.

| Action | COO | ECD | AM | Trf | PM | Prd | CD | Cpy | Dsg | QA | Dev | SEO | Soc | Cli |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `social_view_queue` * | Y | Y | AC | - | AC | AC | - | - | - | - | - | - | Y | - |
| `social_edit_checklist` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_set_ready_to_schedule` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_set_scheduled` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_set_live` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_edit_live_link` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_set_promoted` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_archive_post` * | Y | Y | - | - | - | - | - | - | - | - | - | - | A | - |
| `social_set_checking` * | Y | Y | - | - | - | AC | - | - | - | - | - | - | A | - |
| `social_edit_test_result` * | Y | Y | - | - | - | AC | - | - | - | - | - | - | A | - |

#### Conditions (marked * above)

| Action | Role | Condition |
|---|---|---|
| `view_brief_sent` | Client | Client-safe projection only (title, deliverables, dates, mandatories, references). Never budget, hours, team, internal notes, version diffs. |
| `edit_brief_sent` | all allowed roles | Stage not in done, archived, cancelled. |
| `send_brief` | all allowed roles | Stage draft; BriefRules pass; a Traffic user is assigned. |
| `send_brief_update` | all allowed roles | has_unsent_changes; stage not in done, archived, cancelled; started assets are never cancelled. |
| `recall_brief` | all allowed roles | Stage briefed and no asset has started. |
| `assign_creatives` | AM | Only while stage is draft, as a suggestion Traffic can change. |
| `assign_creatives` | PM | Only while stage is draft. |
| `assign_creatives` | Producer | Only while stage is draft. |
| `edit_asset_work` | Copywriter | assets.assigned_to or tasks.assigned_to is the actor. |
| `edit_asset_work` | Designer | assets.assigned_to or tasks.assigned_to is the actor. |
| `edit_asset_work` | Developer | assets.assigned_to or tasks.assigned_to is the actor. |
| `edit_asset_work` | SEO | assets.assigned_to or tasks.assigned_to is the actor. |
| `edit_asset_work` | Social | assets.assigned_to or tasks.assigned_to is the actor. |
| `edit_asset_work` | CD | Holds the CD slot on the job. |
| `edit_job_field:title` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:campaign_id` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:description` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:due_date` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:brief_date` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:first_go_live` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:last_go_live` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:hours_estimate` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:budget` | all allowed roles | Brief-owned field: after first send the edit goes to the working copy and needs Send update. |
| `edit_job_field:waiting_on` | all allowed roles | Stage is waiting. |
| `edit_job_field:waiting_reason` | all allowed roles | Stage is waiting. |
| `transition:draft->briefed` | all allowed roles | Same preconditions as send_brief. |
| `transition:briefed->draft` | all allowed roles | No asset has started. |
| `transition:briefed->in_progress` | all allowed roles | At least one CD or creative is assigned. |
| `transition:briefed->in_progress` | Copywriter | Implicit only: fires when they start an assigned asset. |
| `transition:briefed->in_progress` | Designer | Implicit only. |
| `transition:briefed->in_progress` | Developer | Implicit only. |
| `transition:briefed->in_progress` | SEO | Implicit only. |
| `transition:briefed->in_progress` | Social | Implicit only. |
| `transition:in_progress->in_review` | all allowed roles | Every non-cancelled asset is submitted. |
| `transition:in_progress->in_review` | Copywriter | Implicit only: fires when the last required asset is submitted. |
| `transition:in_progress->in_review` | Designer | Implicit only. |
| `transition:in_progress->in_review` | Developer | Implicit only. |
| `transition:in_progress->in_review` | SEO | Implicit only. |
| `transition:in_progress->in_review` | Social | Implicit only. |
| `transition:in_review->in_progress` | all allowed roles | Feedback text is required. |
| `transition:in_review->approved_internal` | CD | Holds the CD slot on the job. |
| `transition:approved_internal->in_progress` | Client | Implicit only, through give_feedback_client after send_to_client. |
| `transition:approved_internal->in_progress` | all allowed roles | Feedback or reason text is required. |
| `transition:approved_internal->approved_client` | Client | users.client_signoff = 1 and the job was sent to the client. |
| `transition:approved_internal->approved_client` | AM | On behalf: must name the client contact and how they approved. |
| `transition:approved_internal->approved_client` | PM | On behalf, as AM. |
| `transition:approved_internal->approved_client` | Producer | On behalf, as AM. |
| `transition:approved_internal->approved_client` | COO | On behalf, as AM. |
| `transition:approved_internal->approved_client` | ECD | On behalf, as AM. |
| `transition:approved_client->in_progress` | all allowed roles | Reason required; client approval fields are cleared. |
| `transition:approved_client->ready_to_schedule` | all allowed roles | Checklist complete for every social asset in scope; notifies the AM. |
| `transition:ready_to_schedule->scheduled` | all allowed roles | scheduled_at set per platform in scope. |
| `transition:scheduled->live` | all allowed roles | Live link entered per platform in scope. The AM cannot do this (Q23). |
| `transition:live->done` | all allowed roles | Stage live. Jobs without social assets still use approved_client to done. |
| `social_view_queue` | Social | Lists every approved social-channel deliverable; never budget. Writes still need the Social slot. |
| `social_view_queue` | AM, PM, Producer | Read only, own jobs. |
| `social_edit_checklist` | all allowed roles | Stage approved_client or later; Social needs the Social slot or an assigned asset. |
| `social_set_ready_to_schedule` | all allowed roles | As the transition: checklist complete. |
| `social_set_scheduled` | all allowed roles | Stage ready_to_schedule; scheduled_at required. |
| `social_set_live` | all allowed roles | Stage scheduled; live link required. |
| `social_edit_live_link` | all allowed roles | Stage scheduled or later; edits after Live are logged and notify the AM. |
| `social_set_promoted` | all allowed roles | Stage scheduled or later; checkbox plus optional note. |
| `social_archive_post` | all allowed roles | Archive with a reason; hard delete is not offered to Social. |
| `social_set_checking` | all allowed roles | Post Ready to Schedule or Scheduled; reason required; notifies Social and the AM; the job Social stage moves back with it. |
| `social_set_checking` | Producer | Own jobs (Producer slot, an assigned asset, or brief creator): asset test reports. |
| `social_edit_test_result` | all allowed roles | Post still being checked; only the Test result item (whoever may edit the whole checklist may edit it too). |
| `social_edit_test_result` | Producer | Own jobs; the other checklist items stay as stored. |
| `assign_task_roles` | all allowed roles | Brief sent, job not closed: the Developer, SEO and Producer slots (their template tasks are assigned to the slot holder when a brief is sent). Before the send `assign_creatives` / `assign_account_roles` apply. |
| `view_all_assets` | all allowed roles | Brief sent (or a legacy job past draft): the job sheet asset list and GET /jobs/{id}/assets (deliverables, assets, status, assignee, due). |
| `override_asset_status` | all allowed roles | Brief sent (or a legacy job past draft). Any legacy asset status, or any Social post status, with a required reason. Writes `asset_status_overridden` (from, to, reason; recipients: asset assignee, AM or brief creator, CD) in the same transaction; shown as an override in the activity feed and on the COO's Overrides report. |
| `manage_brand_logo` | all allowed roles | https links only (no uploads), set on the Campaigns page; the view re-checks the stored link before it renders an image. |
| `add_demo_role_tasks` | COO | Demo mode on only; up to 3 open sent jobs; idempotent. |
| `transition:workable->waiting` | all allowed roles | waiting_on and waiting_reason required. |
| `transition:workable->waiting` | Traffic | Not from draft (cannot see drafts). |
| `transition:workable->waiting` | CD | Not from draft. |
| `transition:waiting->resume` | all allowed roles | Returns to the stored resume stage. |
| `transition:workable->on_hold` | all allowed roles | Reason required. |
| `transition:workable->on_hold` | Traffic | Not from draft. |
| `transition:open->cancelled` | all allowed roles | Reason required; started assets are kept and flagged. |
| `transition:done->in_progress` | all allowed roles | Reason required. |
| `submit_for_review` | all allowed roles | Asset is assigned to the actor (CD: CD slot on the job). |
| `approve_internal` | CD | Holds the CD slot on the job; not their own submitted asset unless no other CD or ECD exists. |
| `send_to_client` | all allowed roles | Stage approved_internal; a Client contact is assigned. |
| `approve_client` | Client | users.client_signoff = 1. |
| `approve_client` | AM | On behalf, with contact and evidence note. |
| `approve_client` | PM | On behalf. |
| `approve_client` | Producer | On behalf. |
| `approve_client` | COO | On behalf. |
| `approve_client` | ECD | On behalf. |
| `give_feedback_client` | AM | On behalf: must name the client contact. |
| `give_feedback_client` | PM | On behalf. |
| `give_feedback_client` | Producer | On behalf. |
| `give_feedback_client` | COO | On behalf. |
| `give_feedback_client` | ECD | On behalf. |
| `give_feedback_client` | Client | Job was sent to the client. |
| `recommend_signoff` | Client | users.client_signoff = 0 and the job was sent to the client. |
| `view_all_jobs` | Client | Client-safe projection; drafts and internal-only stages show only as a coarse "In production" line. |
| `view_staff_emails` | Client | Own email only. |
| `view_wiki` | Client | Pages linked to own-brand jobs or campaigns. |

#### Where legacy differs

| Action | Legacy behaviour (file:line) |
|---|---|
| `create_brief` | api/permissions.php:81-83 allows every manager including Traffic; src/constants.js:93 Traffic canCreateJobs true, :121 ECD false, :164 Producer false. |
| `view_brief_draft` | Legacy has no drafts: a brief is a create-only modal. |
| `assign_creatives` | api/permissions.php:85-91 lets any manager and any assigned creative rewrite every assignment through update_job (audit M8). |
| `edit_asset_work` | api/permissions.php:101-107 lets every manager (AM, PM, Traffic, Producer) edit any asset. |
| `edit_job_field:title` | api/api.php:831-840: any assigned creative can edit the title (audit M8). |
| `edit_job_field:hours_estimate` | api/api.php:831-840 update_job whitelist lets any manager and any assigned creative edit it. |
| `transition:in_review->in_progress` | api/permissions.php:130-136 also lets every manager send back. |
| `transition:in_review->approved_internal` | api/permissions.php:118-121 lets any CD approve any job; src/data.js:752-763 auto-approves when a CD or ECD completes the last task. |
| `transition:approved_internal->approved_client` | api/permissions.php:123-128: any own-brand Client or admin; no sign-off distinction; managers refused. |
| `transition:approved_client->done` | No legacy path to Done (PLAN-v7.4 Q2). |
| `social_*` and the three Social transitions | No legacy equivalent: the database rejects the Scheduled and Live statuses (`src/constants.js:21`); all three mirror to Approved (External). |
| `transition:open->cancelled` | Legacy has no cancel action (PLAN-v7.4 gap 3). |
| `qa_signoff` | Legacy QA has no action at all. |
| `approve_internal` | api/permissions.php:118-121: any CD, not the assigned CD. |
| `route_feedback` | src/components/reviews.js:25 FEEDBACK_ASSIGNABLE_ROLES omits CD, QA, AM, Developer, SEO, Social. |
| `send_to_client` | No legacy action; the client queue shows any Approved (Internal) or In Review job (src/components/operations.js:178). |
| `approve_client` | api/permissions.php:123-128: Client of the brand or admin only. |
| `give_feedback_client` | api/permissions.php:130-136 lets every manager post client feedback with no on-behalf record; is_internal comes from the body (api/api.php:1201). |
| `recommend_signoff` | No legacy equivalent. |
| `comment` | No comments in legacy. |
| `manage_campaign` | No add_campaign endpoint exists; legacy creates campaigns only in localStorage. |
| `manage_users` | api/permissions.php:109-112 COO only; src/constants.js:124 ECD canEditPeople false. |
| `toggle_demo_mode` | api/api.php:343 COO only (kept). |
| `view_all_jobs` | api/api.php:438-520 get_jobs returns every job to creatives (audit M7); get_job refuses unassigned (api/permissions.php:138-148). |
| `view_hours` | api/api.php:42-45 hides hours_estimate from clients only. |
| `view_staff_emails` | Hotfixed for clients in api/api.php:363-400. |
| `edit_wiki` | src/components/wiki.js shows the editor to everyone; api/permissions.php:114-116 refuses non-managers. |

## 5. Conflicts, gaps and open questions

### 5.1 Conflicts and gaps in the current code and docs

| # | Where | What is wrong | Effect on roles | Recommended fix (phase) |
|---|---|---|---|---|
| G1 | `api/api.php:445`, `:530` (`SELECT j.*`) with `api/api.php:42-45` (`CLIENT_HIDDEN_JOB_FIELDS`) | Migration 0002 adds `waiting_on`, `waiting_reason`, `am_user_id`, `updated_by`, `row_version` to `jobs`. The legacy `j.*` reads return every new column, and the client strip list does not name them | **Deploy-order hazard.** The moment 0002 runs, legacy `get_jobs` and `get_job` send internal waiting reasons and AM ids to Clients | Add the new columns to `CLIENT_HIDDEN_JOB_FIELDS` in a legacy hotfix deployed **before** 0002 (Phase 2) |
| G2 | `data/live-copy.db` | No job has an AM assignment and every `jobs.created_by` is NULL | After migration the AM's "My jobs", My day and every `assigned_or_creator` rule match nothing; the AM cannot edit or send any existing job | Backfill `am_user_id` and the AM slot in 0002 or a data step (Q10) |
| G3 | `api/permissions.php:9` vs `src/constants.js:87-88` | Server puts Traffic in the manager tier; the UI table gives Traffic `level: 'admin'` | Two different Traffic roles depending on which file you read | Policy follows this document (Traffic is manager-tier, no brief edits, no budget) (Phase 2) |
| G4 | `src/constants.js:115-121` vs `api/permissions.php:8`, `app/Domain/Policy.php` | ECD is `level: 'manager'`, `canCreateJobs: false` in the UI but admin on the server and in the new Policy | ECD sees fewer buttons than the server allows | ECD is admin (Q5) (Phase 2) |
| G5 | `api/permissions.php:109-112`, `src/constants.js:124` vs `app/Domain/Policy.php` `canManageUsers` | Legacy: only COO adds users. New Policy: COO or ECD | ECD gets user admin only in the new app | Follow the approved plan (COO and ECD) and say so in the PR (Q17) |
| G6 | `src/constants.js:233-246` | No entries for Copywriter, Designer, QA (audit L2); they fall to `default` | Maker rules differ between the UI and the server | Makers share one Policy rule set (Phase 2) |
| G7 | `src/constants.js:129-131` vs `api/permissions.php:138-148` | CD and the list endpoint show every job (`canViewAll: true`, audit M7), but `get_job` refuses unassigned creatives | CDs and makers see a list of jobs they cannot open | CD: view all (read only); makers: assigned only, in `JobQuery` (Phase 3) |
| G8 | `api/permissions.php:118-121` vs `src/components/reviews.js:106` | Server lets any CD approve any job; the UI only shows a CD their own | A CD can approve another CD's job by calling the API | `approve_internal` requires the CD slot (Phase reviews) |
| G9 | `src/data.js:748-763` | When a CD or ECD completes the last task the reducer sets Approved (Internal) on their own work | Self-approval with no review | No auto-approval; submit and approve are separate actions (Phase reviews) |
| G10 | `api/permissions.php:130-136`, `api/api.php:1201` | Any manager can reject with feedback, and `is_internal` comes from the body, so an AM or Traffic can post feedback as if from the client with no on-behalf record | Fake client feedback; no way to tell who really said it | Separate `give_feedback_internal` and `give_feedback_client`; on-behalf records name the contact (Phase client_portal) |
| G11 | `api/permissions.php:123-128` vs `src/components/operations.js:183` | Server: any own-brand Client can approve. UI: only the Client in the job's Client slot sees the job | A Brand Director who is not the job's contact never sees the job in the UI, yet the server would accept their approval | Client sees own-brand client-visible jobs; approval needs `client_signoff` (Q3) |
| G12 | `src/components/operations.js:441` | Internal review access includes PM, Producer, Traffic (who cannot approve on the server) and excludes AM and QA | Buttons that return 403; AM and QA locked out | Review queue per section 4 (Phase reviews) |
| G13 | `src/components/operations.js:161` | Client Review access is Client, Traffic, COO; AM is missing | The client-facing role cannot see the client queue | AM, PM, Producer, COO, ECD see the client queue (Phase client_portal) |
| G14 | `src/components/operations.js:3` | Operations dashboard excludes AM | | Superseded by `/today` |
| G15 | `src/components/modals-brief.js:121`, `:286` | The brief requires PM, Copywriter, Designer and CD, and its key roles omit AM | Contradicts the owner decision (Traffic required; CD and creatives optional, chosen by Traffic) | New BriefRules: Traffic required, nothing else (Phase 2) |
| G16 | `api/db.php:146` (`UNIQUE(job_id, role_on_job)`) | One person per role per job | A job with 9 social statics cannot have two designers in the slot | Keep one lead per slot; extra makers go on `assets.assigned_to`; "assigned" checks both (section 4) |
| G17 | `api/db.php:70` (users.role CHECK) | A new role value needs a table rebuild | Client sub-roles cannot be new roles | `users.client_signoff` column (Q3) |
| G18 | `api/api.php` endpoint list (no `add_campaign`) | Campaigns are only created in the browser's localStorage | AM, PM cannot create campaigns that persist | `manage_campaign` in the new app (Phase 2) |
| G19 | `api/permissions.php:101-107` | Any manager can edit any asset | AM or Traffic can overwrite a designer's file link or copy | `edit_asset_work` is assigned makers, the CD slot and admins only (Phase reviews) |
| G20 | `src/components/wiki.js` (no permission check) vs `api/permissions.php:114-116` | Everyone gets the wiki editor; the server refuses non-managers | Makers type, then lose the edit | Hide the editor by `edit_wiki` (Later, wiki rebuild) |
| G21 | `src/components/reviews.js:25` | Feedback can be routed only to ECD, Copywriter, Designer, Traffic, PM, Producer | Cannot route to CD, QA, AM, Developer, SEO, Social | `route_feedback` to any agency assignee (Phase reviews) |
| G22 | `src/components/capacity.js:127-128` | "Send by Email" is a mailto link; nothing is recorded | The only notification in the product leaves no trace | Notifications from the activity log (section 3) |
| G23 | `docs/PLAN.md` "Moves an AM can make" and ADR 0002 | Waiting and on hold have no stored "resume to" stage | Resume cannot know where to return (in_progress or in_review) | Add `resume_stage` to migration 0002 (it is not written yet) |
| G24 | `docs/PLAN.md` stages | No stage for "with the client". Sent and not-yet-sent approved_internal jobs look the same | The client cannot be shown "awaiting review" reliably | Add `sent_to_client_at` and the brief version sent (column on jobs or a reviews table), not a new stage, so the legacy mirror stays 1:1 (Phase client_portal) |
| G25 | `docs/archive/PLAN-v7.4.md` (roles table) | Lists 16 roles in 6 groups and places QA under Operations; code has 14 | Docs and code disagree | This document replaces it |
| G26 | `api/api.php:42-45` | Hours are hidden from Clients only; makers and every other role receive hours, and budget will arrive on briefs | Budget leak once briefs exist if the same pattern is copied | Store queries project per `view_budget` and `view_hours` (Phase 2) |

### 5.2 Open questions for the owner

Each has the default this document (and `policy-matrix.json`) already uses. Changing a default means changing both.

| # | Question | Recommended default | Why |
|---|---|---|---|
| Q1 | Can the AM approve internally? | No. Only the CD on the job, the ECD or the COO | Keeps creative sign-off separate from the client relationship; AM can still send back (approved_internal to in_progress) before the client sees it |
| Q2 | Can the AM (or PM, Producer) record a client approval or feedback given by email or phone? | Yes, "on behalf", naming the contact and how they approved; shown as such in the activity log | Clients approve by email in real life; legacy already lets managers post client feedback, but with no record (G10) |
| Q3 | Client sub-roles? | One Client role plus `users.client_signoff` (0 = Marketing Manager, 1 = Brand Director). Backfill 1 for every existing client; owner unticks MMs per brand | No table rebuild (G17); nothing changes on day one; brands that need Director sign-off get it with one click |
| Q4 | Who can cancel a job? | AM, PM or Producer who owns it (assigned_or_creator), COO, ECD; reason required. Traffic cannot | Cancelling is a commercial decision; Traffic can put on hold instead |
| Q5 | Does the ECD create briefs and jobs? | Allowed (admin cover), not on the ECD's home screen | Admin tier on the server already; avoids a dead end when the AM is away |
| Q6 | Does Producer differ from PM? | Same Policy rules; separate label and home filter (Producer slot) | Live has 1 Producer with 0 assignments; nothing yet needs different rules |
| Q7 | Are Developer, SEO, Social needed in the beta? | Developer and SEO: no, same Maker rules, wave 6. Social: no, wave 4b after reviews and the client portal (see Q20 to Q24) | 1 user each, 0 assignments on live |
| Q8 | Who sees budget? | COO, ECD, and AM, PM, Producer on jobs they own. Traffic, CD and makers see hours only. Clients see neither | Money is account data; hours are needed for planning |
| Q9 | Can the CD assign or swap creatives, or edit creative_direction? | No. Traffic assigns (owner decision); the CD asks Traffic, and asks the AM for brief changes | Single owner for resourcing and for the brief; each change is versioned |
| Q10 | How do existing live jobs get an AM? | Backfill the one live AM user into the AM slot and `am_user_id` on every open job; owner confirms before 0002 runs | Otherwise the AM's beta starts with zero jobs (G2) |
| Q11 | Can Clients see jobs before work is sent to them? | Only a coarse "In production" line (title, due date) for their brand; no assets, no stage detail | Useful status without exposing work in progress |
| Q12 | Can Traffic create briefs? | No. Only AM, PM, Producer, COO, ECD | One entry point for client work. Legacy allows it (`api/permissions.php:81-83`) |
| Q13 | Is QA a blocking gate before the CD? | Advisory: QA pass or fail is recorded and shown; the CD can approve without it. Owner can make it blocking per brand later | Live has never had a QA task |
| Q14 | Multiple makers of one role on a job? | One lead per job slot; others on `assets.assigned_to` | Avoids a `job_assignments` rebuild (G16) |
| Q15 | Can Traffic see drafts to plan ahead? | No; drafts are private to the owner until sent | Matches ADR 0003 (drafts not visible to creatives); revisit if Traffic asks for a pipeline view |
| Q16 | Who can reopen, restore from archive, or reinstate a cancelled job? | The owning AM, PM or Producer, COO and ECD, with a reason | Reversible actions belong with whoever closed them |
| Q17 | Does the ECD manage users? | Yes, as the approved plan says; legacy is COO only | Differs from legacy (G5); worth a one-line confirmation |
| Q18 | Does the brief-owned field edit in the grid (for example due date) go live at once after send? | No. It goes to the working copy and shows "Unsent changes, Send update" | Owner decision: edits after send are versioned and the team is notified |
| Q19 | Are client-visible notifications email-first? | Yes, when email lands; in-app only until then | Clients rarely sit in the app |
| Q20 | Which platforms does the Social list offer? | Facebook, Instagram, LinkedIn, X, TikTok, YouTube, Google Business. Owner can add more (a config list, not a CHECK) | One publication record per asset and platform |
| Q21 | Is Social assigned per job through job_assignments (role Social)? | Yes. Plus a "social queue" of all approved social-channel deliverables that any Social user can read; writes need the Social slot | The slot gives ownership and notifications; the queue stops approved posts being missed when nobody is assigned |
| Q22 | Can Social move a post back, and what happens after Live? | No backward moves for Social. Anyone allowed to reopen (AM, PM, Producer, COO, ECD) uses approved_client to in_progress with a reason; Social and the AM are told (N39) | Keeps the record honest; a live post cannot be silently undone |
| Q23 | Can the AM also mark Live? | No. Social, COO and ECD only. The AM sees the status and the links | The one who publishes confirms it; avoids a Live status with no link |
| Q24 | Is Promoted a checkbox only or does it carry spend? | Checkbox per platform plus an optional note. No spend amount | Spend belongs with budget, which Social never sees |

## 6. Test workflows

For Playwright against `php -S 127.0.0.1:8301 tools/dev-router.php` on a database built from the migrations. "Refused" always means three things are checked: the response is a 403 (or 404 where existence must not leak) with the Policy reason in a toast, the UI does not offer the control, and the row (stage, fields, `row_version`) is unchanged.

**Fixture users** (DEMO.md names; `client_signoff` as proposed in Q3)

| Handle | Role | Notes |
|---|---|---|
| coo_priya | COO | |
| ecd_dominic | ECD | |
| am_amy, am_ben | AM | two AMs, for ownership tests |
| traffic_morgan, traffic_ryan | Traffic | |
| pm_sarah, pm_lena | PM | |
| producer_taylor | Producer | |
| cd_isabelle, cd_james | CD | |
| copy_alex, copy_chloe | Copywriter | |
| design_kim, design_tariq | Designer | |
| qa_jordan | QA | |
| dev_dana, seo_sam, social_sol | Developer, SEO, Social | |
| client_sophie | Client, The Meridian Collection | Marketing Manager, client_signoff 0 |
| client_charles | Client, The Meridian Collection | Brand Director, client_signoff 1 |
| client_luca | Client, Harbour & Helm Hotels | Marketing Manager, client_signoff 0 |

Campaign "Grand Opening London" (Meridian) exists. "MERC job" below means a Meridian job created by am_amy unless stated.

### AM

| ID | Given | When | Then |
|---|---|---|---|
| AM-1 | am_amy is logged in | She creates a brief in "Grand Opening London", adds the deliverable "3x Social Static 1080x1350, IG", mandatories, budget 25000, hours 30, assigns traffic_morgan, and confirms "Send to Traffic" | Stage is briefed; brief v1.0.0 exists; 3 assets exist named by the legacy scheme; traffic_morgan's bell shows N01 (high); the activity log row's actor is am_amy; the legacy `/legacy/` app shows the job as To Do |
| AM-2 | A draft MERC job with no Traffic assigned | am_amy clicks "Send to Traffic" | The dialog lists "Traffic is required"; Send is disabled; stage stays draft; no version row |
| AM-3 | MERC job sent at v1.0.0, copy_alex assigned to an asset | am_amy changes the qty from 3 to 5 and the due date, then opens "Send update" | Before sending, copy_alex still sees v1.0.0 and no change; the dialog suggests minor; after sending, version is v1.1.0, 2 new assets exist, copy_alex gets N02 (normal) with the diff |
| AM-4 | MERC job briefed; design_kim has started one asset | am_amy clicks Recall | Refused: "an asset has started"; stage stays briefed |
| AM-5 (negative) | MERC job in_review | am_amy drags the card to Approved (Internal), and separately POSTs the internal approve route | Both refused; the card snaps back with an error toast; stage stays in_review |
| AM-6 (negative) | am_ben has a draft brief and a sent job with budget 9000 | am_amy opens am_ben's draft URL, and views am_ben's sent job in the grid | Draft returns 404 and is not listed; the sent job is readable but its budget cell is empty and the HTML contains no 9000 |
| AM-7 | MERC job in_progress | am_amy sets waiting, waiting_on "client", reason "Awaiting hero image" | Stage waiting; the job is in her "Waiting on client" section with days waiting; Resume returns it to in_progress (the stored resume stage), assignees get N24 |

### COO

| ID | Given | When | Then |
|---|---|---|---|
| COO-1 | Overdue jobs owned by am_amy and pm_sarah | coo_priya opens `/today` and turns on "All agency" | Both overdue jobs are listed with their owner |
| COO-2 | coo_priya is on `/admin/users` | She creates a Client for Meridian with client_signoff 1 | The user exists with a forced password reset; the first login goes to `/account/password` |
| COO-3 | MERC job in_progress with copy_alex assigned | coo_priya cancels with reason "Client withdrew" | Stage cancelled; copy_alex and traffic_morgan get N26; started assets are kept and flagged |
| COO-4 | Demo mode is on | coo_priya toggles demo mode off on `/admin/system` | Demo mode off; the banner disappears |

### ECD

| ID | Given | When | Then |
|---|---|---|---|
| ECD-1 | MERC job in_review with no CD assigned | ecd_dominic opens `/today` | The job is in his review queue; N14 was sent to him because no CD is assigned |
| ECD-2 | Same job | He approves | Stage approved_internal; the approval records brief version v1.0.0 and actor ecd_dominic; am_amy gets N16 (high) |
| ECD-3 | Another job in_review | He sends it back without feedback text, then with "Logo too small" | First attempt refused (feedback required); second moves it to in_progress and the makers get N17 |
| ECD-4 (negative) | Demo mode is on | ecd_dominic opens `/admin/system` and tries to toggle demo mode | The page opens (admin_system allowed); the toggle is not shown and the POST is refused (COO only) |

### Traffic

| ID | Given | When | Then |
|---|---|---|---|
| TRF-1 | am_amy sent a MERC brief to traffic_morgan | traffic_morgan opens `/traffic` | The job is in "Needs a team"; he assigns cd_isabelle, copy_alex and design_kim and per-asset assignees; am_amy gets N04; copy_alex gets N05 |
| TRF-2 | Same job, then am_amy sends v1.1.0 | traffic_morgan opens the job | "Brief updated to v1.1.0" with change note and diff is shown; the version list has 1.0.0 and 1.1.0 |
| TRF-3 (negative) | Same job | traffic_morgan clicks the due date cell, and PATCHes the brief | No editor opens; the PATCH is refused; the brief is unchanged |
| TRF-4 (negative) | am_amy has a draft | traffic_morgan opens `/jobs` and the draft's brief URL | The draft is not listed; the URL returns 404 |
| TRF-5 (negative) | A job with budget 25000 | traffic_morgan opens the grid and the job sheet | No budget column; the response HTML contains no 25000 |
| TRF-6 | A job approved_client | traffic_morgan marks it done, then archives it | Stage done, then archived; am_amy gets N27 |

### PM

| ID | Given | When | Then |
|---|---|---|---|
| PM-1 | pm_sarah, no AM on the brand | She creates and sends a brief with traffic_ryan | Sent at v1.0.0; she is the creator; the AM slot is empty (she does not hold the AM role) |
| PM-2 | Her sent job | She edits the due date in the grid | The cell shows the new date with "Unsent changes"; makers still see the old date until she sends the update |
| PM-3 (negative) | pm_lena's sent job | pm_sarah edits its title | Refused; title unchanged |
| PM-4 | Her job approved_internal and sent to the client | She records approval on behalf of client_charles, "approved by email" | Stage approved_client; the activity log shows actor pm_sarah on behalf of client_charles |

### Producer

| ID | Given | When | Then |
|---|---|---|---|
| PRD-1 | producer_taylor holds the Producer slot on a job | He opens `/today` | The job is in his sections |
| PRD-2 | That job briefed with a CD assigned | He moves it to in_progress | Allowed |
| PRD-3 (negative) | That job in_review | He tries to approve internally | Refused |

### CD

| ID | Given | When | Then |
|---|---|---|---|
| CD-1 | Two jobs in_review: one with cd_isabelle, one with cd_james | cd_isabelle opens `/reviews` | Only her job is listed, with "made against v1.1.0" |
| CD-2 | Her job | She approves | Stage approved_internal; version checked v1.1.0 recorded; am_amy gets N16 |
| CD-3 | Another of her jobs in_review | She sends back with empty feedback | Refused; stage unchanged |
| CD-4 (negative) | cd_james's job in_review | cd_isabelle POSTs approve for it | Refused (CD slot required) |
| CD-5 (negative) | am_amy has unsent changes on cd_isabelle's job | cd_isabelle opens the brief | She sees the last sent version only; no working-copy values in the HTML |
| CD-6 (negative) | Her job | She tries to replace copy_alex with copy_chloe | Refused (Traffic assigns, Q9) |

### Copywriter

| ID | Given | When | Then |
|---|---|---|---|
| CPY-1 | copy_alex has the copy asset; design_kim has submitted hers | copy_alex writes copy and submits | His asset is submitted; the job moves to in_review; cd_isabelle gets N14 |
| CPY-2 | am_amy has unsent changes on his job | copy_alex opens the brief, then am_amy sends v1.1.0 | First he sees v1.0.0; after the send he gets N02 and sees v1.1.0 with the diff |
| CPY-3 (negative) | A job he is not assigned to | He opens `/queue` and the job URL | Not listed; the URL returns 404 |
| CPY-4 (negative) | design_kim's asset on the same job | He edits its file link | Refused |
| CPY-5 (negative) | His job has budget 25000 | He opens the brief | No budget field; the HTML contains no 25000 |

### Designer

| ID | Given | When | Then |
|---|---|---|---|
| DSG-1 | A briefed job; design_kim assigned to an asset | She starts the asset | The job moves to in_progress (implicit transition); actor is design_kim |
| DSG-2 | Her asset sent back with "Logo too small" | She opens `/queue` | It is in "Sent back to me" with the feedback; she resubmits |
| DSG-3 (negative) | Her job in_progress | She POSTs `/jobs/{id}/move` to approved_internal | Refused |
| DSG-4 | Her unstarted asset; am_amy sends an update lowering the qty | She opens `/queue` | The asset shows as cancelled; she got N08 |

### QA

| ID | Given | When | Then |
|---|---|---|---|
| QA-1 | qa_jordan on a job with a submitted asset | He records a fail, "Wrong size" | cd_isabelle and the asset assignee get N15 |
| QA-2 | Job in_review | He sends it back with notes | Stage in_progress; makers get N17 |
| QA-3 (negative) | Same job | He tries to approve internally | Refused |

### Developer

| ID | Given | When | Then |
|---|---|---|---|
| DEV-1 | Wave 6 is not enabled | dev_dana logs in | BetaGate sends her to `/legacy/` |
| DEV-2 | Wave 6 enabled; she is assigned a landing page asset | She submits it | Submitted; same Maker rules as Copywriter |
| DEV-3 (negative) | A job she is not on | She opens it | 404 |

### SEO

| ID | Given | When | Then |
|---|---|---|---|
| SEO-1 | Wave 6 not enabled | seo_sam logs in | Redirected to `/legacy/` |
| SEO-2 | Wave 6 enabled; assigned a metadata asset | He edits and submits it | Allowed |
| SEO-3 (negative) | His job | He edits the job's go-live date | Refused |

### Social

| ID | Given | When | Then |
|---|---|---|---|
| SOC-1 | Wave 4b not enabled | social_sol logs in | Redirected to `/legacy/` |
| SOC-2 | Wave 4b enabled; a job with 3 social assets, Social slot social_sol, stage approved_client | She opens `/social`, ticks copy, image, link, hashtags and test result for all three, and clicks Ready to Schedule | Stage ready_to_schedule (legacy status Approved (External)); am_amy gets N34; activity log actor social_sol |
| SOC-3 | That job in ready_to_schedule | She sets a scheduled date for Instagram and Facebook and moves to Scheduled, later pastes both live links and moves to Live, and ticks Promoted on Instagram | Stages scheduled then live; live_url stored per platform; promoted true on Instagram only; am_amy gets N35, N36 and N37; the job's Done is then up to am_amy |
| SOC-4 | Same job, asset 2 only is approved by the client | She sets asset 2 to Ready to Schedule | Allowed for asset 2; assets 1 and 3 and the brief stage are unchanged; the brief moves only when all social assets reach that status |
| SOC-5 (negative) | A job in approved_internal (client has not approved) | social_sol POSTs ready_to_schedule and opens the job URL | Both refused; the job is not in her queue; nothing changes |
| SOC-6 (negative) | A job in scheduled with live link empty | am_amy POSTs Live; social_sol tries Live without a link | AM refused (403, Policy reason shown); Social refused until a live link is entered |
| SOC-7 (negative) | A job with budget 25000 set | social_sol loads `/social`, the job page and every fragment | Budget does not appear in any HTML or SSE response; a checklist with an item unticked also blocks Ready to Schedule |

### Client

| ID | Given | When | Then |
|---|---|---|---|
| CLI-1 | Jobs exist for Meridian and Harbour & Helm | client_sophie opens `/portal` | Only Meridian jobs; sent jobs in "Awaiting my review"; others as "In production" lines without assets |
| CLI-2 | A Meridian job sent to the client | client_sophie (signoff 0) opens it | No Approve button and the approve POST is refused; she gives feedback, so the stage moves to in_progress and am_amy gets N21; on another sent job she clicks "Recommend for sign-off" and client_charles gets N19 |
| CLI-3 | That second job | client_charles approves | Stage approved_client; actor client_charles; am_amy gets N20 |
| CLI-4 (negative) | A Meridian job id | client_luca opens its portal URL and POSTs feedback | 404 for both; nothing changes |
| CLI-5 (negative) | A Meridian job with budget, hours, internal feedback, waiting reason and staff emails set | client_sophie loads every portal page and fragment | None of those values appear in any response (scan the HTML and the SSE events for each stored value) |
| CLI-6 (negative) | client_sophie is logged in | She opens `/jobs`, `/today`, `/admin/system` | Redirected to `/portal` (or 403); before wave 4 BetaGate sends her to `/legacy/` |

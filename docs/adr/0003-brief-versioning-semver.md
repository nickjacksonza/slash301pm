# ADR 0003: Brief versions use major.minor.patch

Status: accepted (Phase 0)

## Context
In the legacy app there is no Brief entity. A brief is a create-only modal whose fields are spread across `jobs` columns, and several of its fields are never saved (`docs/audit.md`, H8). The brief is the centre of every step: the AM writes it, Traffic assigns the team from it, and the creatives work from it. Briefs change after they are sent, and the team has to know what changed and which version a review looked at.

## Decision
- A `briefs` table (1:1 with a job) holds the working brief. `brief_versions` holds immutable snapshots (version text such as "3.2.3", the three parts, bump level, change note, snapshot JSON, diff JSON, author, time).
- Version numbers have three parts, major.minor.patch.
  - A draft is 0.x and is not visible to creatives.
  - The first send to Traffic creates **1.0.0**.
  - Each later "Send update" asks for a bump level and suggests one from the diff:
    - **major**: change of scope, or a re-brief.
    - **minor**: deliverables, dates, budget or team changed.
    - **patch**: wording, references or fixes.
  - A bump resets the parts to its right to 0.
- After the first send, edits go to a working copy (`has_unsent_changes`) until the AM chooses "Send update (vX.Y.Z)".
- **Creatives always read the latest sent version**, never the working copy. Reviews (later phase) record the version they checked.
- Sending creates a snapshot, logs the activity in the same transaction, and notifies the team: "Brief updated to v1.2.0" with the change summary and diff.
- Deliverables (`brief_assets`) expand into `assets` rows on send. A higher quantity adds rows; a lower quantity or removed line cancels rows that have not started; started assets are never cancelled and the AM sees a warning.
- `BriefVersion` (parse, compare, bump, suggest from diff) lives in `Domain` and is pure.

## Consequences
- Version history, diff view and print view come from the snapshots with no extra tracking.
- Snapshots duplicate data on purpose; they are never edited, so a bug in a later migration cannot rewrite history.
- A suggested bump is only a suggestion. The AM can pick any level, so the number is a signal and not a guarantee.
- Jobs already past the brief stage are backfilled at 1.0.0 (migration 0003).
- Notifications are in-app in the beta; email comes later.

## Alternatives considered
- A single integer version. Rejected: it cannot tell the team whether a change is a re-brief or a typo fix.
- Editing the brief in place with only an activity log. Rejected: creatives could not be pinned to a stable sent version, and reviews could not say what they checked.
- Versioning the whole job. Rejected: the job also holds status and assignments that change daily and are not part of the brief.

---
name: steward
description: Repository conventions for Slash 301 PM pull requests - branches, commits, PR descriptions, review and CI handling, and what Claude must never do. Load before creating a branch, committing, opening or updating a PR, or acting on PR review and CI events.
---

# Steward rules for this repo

## Owner's standing rules (also enforced by .claude/hooks/guard-bash.sh)
- Never push to `main`, force-push, `git reset --hard`, or amend pushed commits. Work on a feature branch and open a PR.
- Never touch the live server. The owner deploys by hand over SFTP. Give them a checklist (load the `sftp-deploy` skill).
- `data/.demo_mode` and the seeded passwords in `api/seed.php` stay as they are until the owner flips the beta gate.
- Do not touch kairosflow or any client folders.
- Confirm with the owner before any outward-facing or destructive action (merging, deleting branches, posting outside this repo).

## Branches and commits
- One branch and one PR per plan phase or self-contained fix. Branch names: `phase-N/<short-topic>` or `fix/<short-topic>`, unless the session assigns a branch.
- Each logical change is its own commit with a clear imperative subject. Do not mix refactors with behaviour changes.
- After a PR is squash-merged, start follow-up work from the latest `main`; never stack new commits on merged history. Because force-push is not allowed, use a new branch name rather than resetting a pushed one.

## PR description
Sections, in order:
1. **Summary**: what changed and why, in plain English.
2. **Testing**: exact commands run (`php tests/run.php`, `php tools/lint-sql.php`, `php tools/predeploy.php <from> HEAD`, Playwright) and results.
3. **Deploy (SFTP)**: the checklist from the `sftp-deploy` skill, in deploy order, including deletes and server-side steps.
4. **Owner actions**: anything only the owner can do (beta gate, live DB download, network allow-list).
5. **Notes / risks**.
No deploy workflows exist in `.github/`, so merging to `main` does not change the live site.

## Before every push
- `php -l` on changed PHP files, the unit tests, SQL lint on new migrations, and `php tools/predeploy.php` for the range.
- Run `/code-review` on the diff and, for anything touching auth, permissions, sessions, uploads or escaping, `/security-review`. Use the `architect` agent for the adversarial pass on Domain, migrations and auth.

## Review and CI events
- There is no CI in this repo today. Review comments: implement small, local asks; reply with a proposal for larger ones and let the owner decide.
- Merging is the owner's call. Do not merge unless the owner says so in this session.

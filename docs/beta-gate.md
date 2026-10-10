# Beta gate checklist

The new app goes into daily use (the beta) only when every item below is met. Items marked **owner** are yours to do by hand; Claude never does them. `/admin/system` computes the app-side items on every visit and shows a red banner listing whatever is still unmet (a green line when all pass).

| # | Item | Who | How to meet it | Shown on /admin/system |
|---|---|---|---|---|
| 1 | Demo mode off | **owner** | Delete `data/.demo_mode` on the server by SFTP (`public_html/projects/slash301pm/data/.demo_mode`). Never upload it again; `tools/predeploy.php` refuses to. While the file exists every page of the new app shows a "Demo mode is on" banner. | yes (`demo_off`) |
| 2 | Seeded passwords replaced | **owner** | Every active user can still sign in with the seed password from `api/seed.php` (audit C3). Sign in as COO or ECD, open `/admin/users` and press **Reset password** for each user; give each person their new password privately (they can change it at **Change password**). Deactivate accounts nobody uses. The banner names the users still on the seed password. The check is cached per password hash in `data/seed-password-check.json` and checks at most 6 users, or about 1.5 seconds of work, per page load (each check takes a quarter second or more), so reload `/admin/system` until it says no users are left unchecked. | yes (`seed_passwords`) |
| 3 | Account Manager user(s) created | **owner** | At `/admin/users` create the AM account(s) (role AM), with your own 12+ character password. | yes (`am_user`) |
| 4 | Migrations applied, none failed | owner opens the page | After the upload, open `/slash301pm/healthz` once: it runs pending migrations (0011 adds `rate_limits`) and answers `"ok": true`. | yes (`migrations`) |
| 5 | Backup present after migration | automatic | The migrator writes `data/backups/pre-NNNN-<time>.db` before applying anything. `/admin/system` lists the backups. | yes (`backup`) |
| 6 | datastar.js matches its pin | automatic | Upload `public/js/datastar.js` and `public/js/datastar.js.sha256` together. | yes (`datastar_pin`) |
| 7 | `/admin/system` green | **owner** looks | No banner items left; schema level 11 of 11. | the banner itself |
| 8 | `/healthz` ok | **owner** looks | `/slash301pm/healthz` returns HTTP 200 with `"ok": true` and `"demo_mode": false`. | no (it is the health check) |
| 9 | Pre-deploy check passes | Claude runs, owner confirms | `php tools/predeploy.php <live commit> <release commit>` ends with "Pre-deploy check passed" (warnings allowed, no FAIL lines). | no |
| 10 | Legacy smoke test passes | Claude runs, owner confirms on live | Locally: `php tests/run.php integration --filter=legacy` (legacy API: login, batch authorization, approval guard, `*_by` from the session, Client isolation, wiki blanking guard, job numbers). There is no browser smoke test of `/legacy/` in the repo yet (its React and DOMPurify load from CDNs this sandbox cannot reach). On live: sign in at `/slash301pm/legacy/`, open the job list and one job, and check that assets and the brief show. | no |

## Before you flip the gate
- Cloudflare: keep Rocket Loader as it is (our scripts carry `data-cfasync="false"`), and keep Email Obfuscation and automatic Web Analytics injection **off** for `/slash301pm/`. Both inject inline scripts that the new Content Security Policy blocks (docs/adr/0007-security-headers.md); the app still works but the browser console shows CSP errors.
- Check the web root for files git never tracked: `unzipper.php`, any `*.zip`, `api/seed.php`, old probe scripts. Delete them.
- Check that `data/logs/` exists on the server after the first request (the app creates it, with its own deny `.htaccess`). Error details go there, never to the browser; a user only sees a reference like `quote reference 3f9c0a1b2c3d4e5f`, which you can search for in `data/logs/app-<date>.log`. Logs older than 30 days are deleted automatically.

## After the gate
- The old seed password no longer works for anyone; `/admin/system` stays green.
- Rate limits are active: 120 changes per minute per person (or per IP when signed out); sign-in keeps its own limit of 10 failures per 15 minutes per IP and per username.
- Open audit items that remain after the gate: M10 (old database in git history, owner decision on a history rewrite), M11 (wiki HTML sanitising, with the wiki rebuild), L9 (re-check the hard-coded Cloudflare IP ranges in `app/Http/ClientIp.php` against https://www.cloudflare.com/ips/; the sandbox cannot reach that page).

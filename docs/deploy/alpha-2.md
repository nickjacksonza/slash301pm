# Alpha 2 deploy (SFTP, overwrite)

Built from the head of `claude/rebuild` (PR into `main`). 486 tests pass, `php tools/predeploy.php 23d123d HEAD` passes, and a smoke test of the package against a copy of the old database ran all migrations (live is at 11, so 0012 to 0014 will run), wrote a backup and served the app.

Server folder: `public_html/projects/slash301pm/`. Package: `slash301pm-alpha2.tar.gz` (434 files, about 3 MB). Upload its **contents** into the server folder.

## What is new since alpha 1
- Brand logo filters and Traffic asset overrides on My day and Jobs.
- Default role tasks (UTM links, hashtags, test reports).
- Social role screens: ready to schedule, scheduled, live, paid promotion.
- Basic permissions for all roles.
- Approvals: creative hand-in page, post-style review page, CD and ECD approval, ready for client.
- Design pass: new pages follow the legacy look (pill controls, uppercase badges, warm shadows).
- Migrations 0012 (asset publications), 0013 (brand logos), 0014 (reviews).

## Before you start
1. Download a full copy of the server folder (rollback). The key file is `data/slash301pm.db`.
2. Turn off any mirror or "delete remote files not present locally" option. The package has no database.
3. Do not upload a local database, and do not delete anything inside the server's `data/` folder.

## Upload order
1. Sub-folder `.htaccess` files first: `app/`, `vendor/`, `migrations/`, `api/`, `data/`, `data/sessions/`, `data/logs/`.
2. Backend: `migrations/`, `vendor/`, `api/`, `app/`, then `index.php`.
3. Frontend: `public/`, then `legacy/`.
4. Root `.htaccess` last.

Exact list: `php tools/predeploy.php 23d123d <commit>`.

## Delete on the server
Nothing new compared with alpha 1. Check once that `unzipper.php`, any `zipper-*.zip` and `api/seed.php` are gone.

## After the upload
1. Purge the Cloudflare cache for `projects.slash301.com/slash301pm/*`.
2. Open `/slash301pm/healthz` once. Expect `"ok": true` and `"current": 14, "latest": 14`. Demo mode stays on (the package does not include or change the demo-mode flag file).
3. Sign in and check: My day, Jobs, Board, a brief, `/jobs/{id}/work` and `/jobs/{id}/review` on a job with assets, and `/legacy/`.
4. As COO or ECD open `/admin/system`: migration level 14, a new backup listed, review round limit (3).

## If something goes wrong
- Maintenance page or `failed: true` in healthz: a migration was rolled back and `/legacy/` still works. Send `data/migrate-failed.json`.
- Blank page or 500: send the newest file in `data/logs/`.
- Full rollback: restore the folder copy from step 1, or only the database from `data/backups/pre-0012-*.db`.

## Known limits
- Reassigning a review to a client only records it (no client login yet).
- No live job has an AM assigned, so AM notifications start once an AM claims a job.
- Login page and a few one-off controls still use the old sizes.
- Beta gate (demo mode off, seeded passwords reset, AM users) is still yours to do.

# Alpha 1 deploy (SFTP, full overwrite)

Built from commit `23d123d` ("Phase 5: hardening and beta gate"). All 401 tests pass on a clean export of that commit, and a smoke test of the package against a copy of the old database ran all 11 migrations, wrote a backup and served the app.

Server folder: `public_html/projects/slash301pm/` (live at `https://projects.slash301.com/slash301pm/`).
Package folder to upload: `upload/slash301pm/` (350 files, about 2.5 MB). Upload its **contents** into the server folder.

## Before you start (5 minutes)

1. **Download a full copy of the server folder** to your computer (this is your rollback). The most important file is `data/slash301pm.db`.
2. In your SFTP client, **turn off any "mirror", "synchronise" or "delete remote files not present locally" option.** The package has no database, so a mirror would delete `data/slash301pm.db` and everyone's sessions.
3. Never upload a local database over the server one, and do not delete anything inside the server's `data/` folder.

## Upload order

Uploading in this order keeps the site from breaking halfway, and keeps internal folders protected before code lands in them.

1. **Sub-folder `.htaccess` files first:**
   `app/.htaccess`, `vendor/.htaccess`, `migrations/.htaccess`, `api/.htaccess`, `data/.htaccess`, `data/sessions/.htaccess`, `data/logs/.htaccess` (create the `app/`, `vendor/`, `migrations/` and `data/logs/` folders on the server if they do not exist yet).
2. **Backend:**
   1. `migrations/` (all `.sql` files)
   2. `vendor/`
   3. `api/` (`api.php`, `auth.php`, `db.php`, `permissions.php`)
   4. `app/`
   5. `index.php` (last of the backend; it loads `app/`)
3. **Frontend:** `public/`, then `legacy/`.
4. **Root `.htaccess` last.** From this moment `/slash301pm/` is the new app and `/slash301pm/legacy/` is the old one.

## Delete on the server (after the upload)

Old app files now replaced by `legacy/`:
- `index.html`, `styles.css`, `scripts.js`
- `src/` (whole folder)
- `backup/` (whole folder)

Leftovers that must not stay on a public server:
- `unzipper.php` and any `zipper-*.zip` or other archive in the folder
- `api/seed.php` (the package does not include it; delete the server copy)
- any other probe or test script you do not recognise

Leave alone: `data/slash301pm.db`, `data/sessions/`, `data/.demo_mode` (demo mode stays on for the alpha; you switch it off at the beta gate).

## After the upload

1. Purge the Cloudflare cache for `projects.slash301.com/slash301pm/*`.
2. Open `https://projects.slash301.com/slash301pm/healthz` once. It runs the 11 database migrations (first a backup into `data/backups/`) and should show `"ok": true` and `"current": 11, "latest": 11`.
3. Open `https://projects.slash301.com/slash301pm/` and sign in (demo picker while demo mode is on). Check:
   - My day, Briefs, Jobs, Board and Campaigns open without errors.
   - Create a test brief, add a deliverable, assign Traffic, send it (v1.0.0).
   - `https://projects.slash301.com/slash301pm/legacy/` still loads the old app and shows the same jobs.
4. Signed in as COO or ECD, open `/slash301pm/admin/system`: migration level 11, a backup listed, and the beta-gate banner (it will list demo mode and the seed passwords; that is expected for the alpha).
5. Cloudflare settings for `/slash301pm/`: keep Email Obfuscation and automatic analytics injection off (they inject inline scripts the new security policy blocks).

## If something goes wrong

- Page shows "maintenance" or `/healthz` reports `failed: true`: a migration failed and was rolled back; the old app at `/legacy/` keeps working. Send me the text of `data/migrate-failed.json`.
- Blank page or 500 error: send me the newest file in `data/logs/` (download it; it is not readable from the web).
- Full rollback: delete the uploaded files and restore the copy you downloaded in step 1, including `data/slash301pm.db`. (Or restore only the database from `data/backups/pre-0001-*.db`.)

## Known limits of this alpha

- New app roles: AM, COO and ECD. Everyone else is sent to `/legacy/` (role permissions for the other roles are being finished).
- Social publishing screens are not in this alpha.
- The Cloudflare IP list in `api/auth.php` and the new login rate limit has not been re-checked against cloudflare.com/ips.

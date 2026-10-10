# Slash 301 PM

Live at `projects.slash301.com/slash301pm/`.

**SFTP path:** `public_html/projects/slash301pm/`

**The only build step** is `bash tools/build-css.sh` (the Tailwind CLI). Its output `public/css/app.css` is committed. The server runs no build, no Composer and no npm: everything that is uploaded is already built.

## Deploy Order

The order matters: the root `.htaccess` routes to `index.php`, `app/`, `vendor/` and `legacy/`, so uploading it early breaks the site; and `app/` uploaded before its `.htaccess` is exposed.

1. Sub-folder `.htaccess` files (`app/`, `vendor/`, `migrations/`, `api/`, `data/`, `tests/`, `tools/`, any other non-root one)
2. Backend (`api/`, `app/`, `vendor/`, `migrations/`, allowed `data/` files, `index.php`)
3. Frontend (`public/`, `legacy/`, other files)
4. The ROOT `.htaccess`, last
5. Deletes (old root `index.html`, `src/`, `styles.css`, `scripts.js`, `backup/`), after all uploads

After upload, open `/slash301pm/healthz` once; it runs pending migrations and writes a backup in `data/backups/`.

Run `php tools/predeploy.php <from> <to>` for the exact list. Increment `?v=N` on CSS/JS includes in `legacy/index.html` on each deploy for Cloudflare cache busting.

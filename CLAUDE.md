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

## Network access

The cloud environment for this repo allows outbound HTTPS to the domains below. Anything not listed is denied by policy. The list was checked against the proxy in an earlier session; if a host fails, check the environment's Allowed domains before assuming a bug.

Reachable, per the environment settings:

| Category | Domains |
| --- | --- |
| Code hosting | github.com, api.github.com, codeload.github.com, raw.githubusercontent.com, release-assets.githubusercontent.com, *.github.com, *.githubusercontent.com |
| npm / JS | npmjs.com, npmjs.org, www.npmjs.com, www.npmjs.org, registry.npmjs.org, registry.yarnpkg.com, yarnpkg.com, unpkg.com |
| Python | pypi.org, files.pythonhosted.org, pythonhosted.org |
| Rust | crates.io, index.crates.io, static.crates.io, docs.rs |
| Go | go.dev, golang.org, pkg.go.dev, proxy.golang.org, sum.golang.org |
| Google | *.google.com, googleapis.com, gstatic.com, support.google.com |
| Anthropic / Claude | api.anthropic.com, claude.ai, bridge.claudeusercontent.com |
| CDNs | cdn.jsdelivr.net, cdnjs.cloudflare.com |
| Reference / docs | developer.mozilla.org, w3.org, stackoverflow.com, postgresql.org, *.postgresql.org |
| Linux packages | archive.ubuntu.com, security.ubuntu.com |
| Other | *.slash301.com |
| Added: other registries | rubygems.org, index.rubygems.org, repo.maven.apache.org, plugins.gradle.org, services.gradle.org, repo.packagist.org, api.nuget.org, repo.anaconda.com, conda.anaconda.org |
| Added: toolchains and downloads | nodejs.org, dl-cdn.alpinelinux.org, releases.hashicorp.com, developer.hashicorp.com, dl.google.com |
| Added: ML and docs | huggingface.co, docs.python.org, learn.microsoft.com, kubernetes.io |

Not allowed: container registries (for example registry-1.docker.io). Pulling images will fail.

Notes:
- claude.ai and www.npmjs.com are reachable, but they return a Cloudflare bot challenge to non-browser clients, so `curl` and `WebFetch` may get a 403 there.
- crates.io returns 403 from the origin for the site root.

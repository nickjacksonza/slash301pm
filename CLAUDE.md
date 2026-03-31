# Slash 301 PM

Live at `projects.slash301.com/slash301pm/`.

**SFTP path:** `public_html/projects/slash301pm/`

**No build step.** Open `index.html` directly in a browser. No npm, no bundler, no package manager.

## Deploy Order

1. `.htaccess` files first
2. Backend (`api/`, `data/`)
3. Frontend (`index.html`, `styles.css`, `scripts.js`)

Increment `?v=N` on CSS/JS includes in `index.html` on each deploy for Cloudflare cache busting.

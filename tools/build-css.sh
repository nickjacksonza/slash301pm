#!/usr/bin/env bash
# Build public/css/app.css from styles/app.css with the Tailwind v4 standalone CLI (via npx, no package.json).
# Idempotent: same inputs give the same output. Override the version with TAILWIND_VERSION=x.y.z.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p public/css
V="${TAILWIND_VERSION:-4.3.3}"
# The CLI resolves `@import "tailwindcss"` from node_modules next to the project, and npx alone does not provide it.
# node_modules/ is git-ignored; --no-save writes no package.json or lockfile.
if [ ! -f node_modules/tailwindcss/package.json ] || ! grep -q "\"version\": \"$V\"" node_modules/tailwindcss/package.json; then
  npm install --no-save --no-package-lock --no-audit --no-fund "tailwindcss@$V" >/dev/null
fi
npx -y "@tailwindcss/cli@$V" -i styles/app.css -o public/css/app.css --minify
printf 'public/css/app.css: %s bytes\n' "$(wc -c < public/css/app.css | tr -d ' ')"

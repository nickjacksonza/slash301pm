#!/bin/bash
# SessionStart: make a fresh cloud container ready for this repo's checks and tests.
# Idempotent and non-interactive. Runs only in Claude Code cloud sessions.
set -uo pipefail
[ "${CLAUDE_CODE_REMOTE:-}" != "true" ] && exit 0

root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
TAILWIND_VERSION="4.3.3"   # keep in step with tools/build-css.sh
status=()
have() { command -v "$1" >/dev/null 2>&1; }

if have php; then
  v=$(php -r 'echo PHP_VERSION;'); status+=("php $v")
  php -m | grep -qi pdo_sqlite || status+=("WARNING: pdo_sqlite missing")
  php -m | grep -qi '^sqlite3$' || status+=("WARNING: sqlite3 ext missing")
else
  status+=("WARNING: php missing")
fi
have composer && status+=("composer ok") || status+=("composer missing")
have go && status+=("go $(go env GOVERSION 2>/dev/null)") || status+=("go missing")
have node && status+=("node $(node -v)") || status+=("WARNING: node missing")

# Tailwind v4 CLI through npx (npm registry is reachable from cloud sessions).
# Warm the npx cache once so later builds are fast and offline-tolerant.
if have npx; then
  if npx -y "@tailwindcss/cli@${TAILWIND_VERSION}" --help >/dev/null 2>&1; then
    status+=("tailwind ${TAILWIND_VERSION} ready (npx)")
  else
    status+=("WARNING: tailwind ${TAILWIND_VERSION} could not be fetched")
  fi
fi

# Playwright: use the preinstalled Chromium, never download browsers.
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
  {
    echo 'export PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers'
    echo 'export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1'
    echo "export TAILWIND_VERSION=${TAILWIND_VERSION}"
  } >> "$CLAUDE_ENV_FILE"
fi
if [ -f "$root/tests/e2e/package.json" ] && have npm; then
  (cd "$root/tests/e2e" && PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm install --no-audit --no-fund >/dev/null 2>&1) \
    && status+=("e2e deps installed") || status+=("WARNING: e2e npm install failed")
fi

printf 'Session ready: %s\n' "$(printf '%s; ' "${status[@]}")"
echo "Fast checks: php -l <file>; php tests/run.php --unit (once tests/ exists); php tools/predeploy.php"
exit 0

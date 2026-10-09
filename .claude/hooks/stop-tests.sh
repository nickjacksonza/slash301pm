#!/bin/bash
# Stop hook: if PHP files changed and the test runner exists, run the fast unit tests.
# A failure blocks stopping once, so the failure gets fixed or reported.
set -uo pipefail
input=$(cat)
[ "$(printf '%s' "$input" | jq -r '.stop_hook_active // false')" = "true" ] && exit 0
root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
[ -f "$root/tests/run.php" ] || exit 0
changed=$( { git -C "$root" diff --name-only HEAD 2>/dev/null; git -C "$root" ls-files --others --exclude-standard 2>/dev/null; } | grep -E '\.php$' | head -1)
[ -z "$changed" ] && exit 0
out=$(cd "$root" && timeout 120 php tests/run.php --unit 2>&1)
if [ $? -ne 0 ]; then
  echo "Unit tests fail (php tests/run.php --unit). Fix them or tell the owner why not:" >&2
  printf '%s\n' "$out" | tail -40 >&2
  exit 2
fi
exit 0

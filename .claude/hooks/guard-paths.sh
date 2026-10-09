#!/bin/bash
# PreToolUse guard for Edit/Write/MultiEdit/NotebookEdit: protected files stay untouched.
set -uo pipefail
f=$(jq -r '.tool_input.file_path // .tool_input.notebook_path // ""')
[ -z "$f" ] && exit 0
root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
rel="${f#$root/}"
block() { echo "Blocked by .claude/hooks/guard-paths.sh: $1 Ask the owner first if this is really needed." >&2; exit 2; }
case "$rel" in
  data/.demo_mode) block "data/.demo_mode stays as is (owner decision)." ;;
  api/seed.php) block "api/seed.php holds the seeded passwords, which stay as they are (owner decision)." ;;
esac
echo "$rel" | grep -qi kairosflow && block "kairosflow is out of scope."
if [ -f "$root/.claude/protected-paths.txt" ]; then
  while IFS= read -r p; do
    [[ -z "$p" || "$p" == \#* ]] && continue
    echo "$rel" | grep -Eq "^${p}" && block "$p is a protected path (.claude/protected-paths.txt)."
  done < "$root/.claude/protected-paths.txt"
fi
exit 0

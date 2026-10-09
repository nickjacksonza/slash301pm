#!/bin/bash
# PostToolUse after Edit/Write: fast checks on the file just changed.
# Exit 2 sends the problem back to Claude so it gets fixed straight away.
set -uo pipefail
f=$(jq -r '.tool_input.file_path // ""')
[ -z "$f" ] || [ ! -f "$f" ] && exit 0
root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
rel="${f#$root/}"
problems=""

if [[ "$f" == *.php ]]; then
  out=$(php -l "$f" 2>&1) || problems+="php -l failed for $rel:\n$out\n"
fi

if [[ "$rel" == migrations/*.sql && -f "$root/tools/lint-sql.php" ]]; then
  out=$(php "$root/tools/lint-sql.php" "$f" 2>&1) || problems+="SQL lint failed for $rel (server runs SQLite 3.34):\n$out\n"
fi

# Tailwind only sees full literal class names. Flag class names built by concatenation.
if [[ "$rel" == app/View/* && "$f" == *.php ]]; then
  hits=$(grep -nE "(['\"])(bg|text|border|ring|fill|stroke|from|via|to|shadow|outline|divide|placeholder|accent|caret|decoration)-\1?[[:space:]]*\.|(bg|text|border|ring)-(\{\\\$|<\?=)" "$f" | head -5)
  [ -n "$hits" ] && problems+="Tailwind class built by concatenation in $rel (write full literal class strings, e.g. via a map):\n$hits\n"
fi

if [ -n "$problems" ]; then
  printf "%b" "$problems" >&2
  exit 2
fi
exit 0

#!/bin/bash
# PreToolUse guard for Bash. Enforces the owner's standing rules:
# no push to main, no force-push, no reset --hard, no amending pushed commits,
# no talking to servers (deploys are manual SFTP by the owner), and no changes to
# demo mode, the seeded passwords, kairosflow or client folders.
# Exit 2 blocks the call and shows the reason to Claude.
set -uo pipefail

cmd=$(jq -r '.tool_input.command // ""')
[ -z "$cmd" ] && exit 0

block() {
  echo "Blocked by .claude/hooks/guard-bash.sh: $1" >&2
  echo "If this really is needed, stop and ask the owner first." >&2
  exit 2
}

# Collapse whitespace so patterns are simpler.
c=$(printf '%s' "$cmd" | tr '\n\t' '  ' | tr -s ' ')
sep='(^|[;&|(]|&&|\|\|)[[:space:]]*'

if printf '%s' "$c" | grep -Eq "git([[:space:]]+-C[[:space:]]+[^ ]+)?[[:space:]]+push"; then
  printf '%s' "$c" | grep -Eq -- "(--force|--force-with-lease|--force-if-includes|[[:space:]]-[a-zA-Z]*f[a-zA-Z]*([[:space:]]|$)|[[:space:]]\+[^ ]+)" \
    && block "force-push is not allowed."
  printf '%s' "$c" | grep -Eq "push[^;&|]*([[:space:]]|:)(main|master)([[:space:]]|$|;|&)" \
    && block "pushing to main is not allowed; push a feature branch and open a PR."
  branch=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --abbrev-ref HEAD 2>/dev/null)
  if [[ "$branch" == "main" || "$branch" == "master" ]] && ! printf '%s' "$c" | grep -Eq "push[[:space:]]+[^ ]+[[:space:]]+[^ ]+"; then
    block "you are on $branch and the push names no branch."
  fi
fi

printf '%s' "$c" | grep -Eq "git([[:space:]]+-C[[:space:]]+[^ ]+)?[[:space:]]+reset[^;&|]*--hard" \
  && block "git reset --hard is not allowed."

if printf '%s' "$c" | grep -Eq "git([[:space:]]+-C[[:space:]]+[^ ]+)?[[:space:]]+commit[^;&|]*--amend"; then
  if [ -n "$(git -C "${CLAUDE_PROJECT_DIR:-.}" branch -r --contains HEAD 2>/dev/null)" ]; then
    block "HEAD is already pushed; amending it would rewrite pushed history. Make a new commit."
  fi
fi

printf '%s' "$c" | grep -Eq "${sep}(sudo[[:space:]]+)?(sftp|scp|lftp|ssh|sshpass|rsync|ftp|ncftp|ncftpput|curlftpfs)([[:space:]]|$)" \
  && block "connecting to servers is not allowed; the owner deploys by SFTP by hand. Give them a checklist instead."
printf '%s' "$c" | grep -Eq "curl[^;&|]*([[:space:]]-T|--upload-file|ftp://|sftp://)" \
  && block "uploads with curl are not allowed; the owner deploys by hand."

mod='(rm|mv|cp|touch|tee|truncate|chmod|ln|sed[[:space:]]+-i|perl[[:space:]]+-i|>|>>|git[[:space:]]+rm|git[[:space:]]+mv)'
printf '%s' "$c" | grep -Eq "${mod}[^;&|]*data/\.demo_mode" \
  && block "data/.demo_mode stays as is (owner decision)."
printf '%s' "$c" | grep -Eq "${mod}[^;&|]*api/seed\.php" \
  && block "api/seed.php holds the seeded passwords, which stay as they are (owner decision)."
printf '%s' "$c" | grep -Eiq "${mod}[^;&|]*kairosflow" \
  && block "kairosflow is out of scope for this repo's work."
if [ -f "${CLAUDE_PROJECT_DIR:-.}/.claude/protected-paths.txt" ]; then
  while IFS= read -r p; do
    [[ -z "$p" || "$p" == \#* ]] && continue
    printf '%s' "$c" | grep -Eq "${mod}[^;&|]*${p}" && block "$p is a protected path (.claude/protected-paths.txt)."
  done < "${CLAUDE_PROJECT_DIR:-.}/.claude/protected-paths.txt"
fi
exit 0

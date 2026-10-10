#!/bin/bash
# Self-test for the guard hooks. Feeds simulated tool calls to each script;
# nothing here runs git, ssh or any of the commands it lists.
# Usage: bash .claude/hooks/test-hooks.sh
set -u
dir="$(cd "$(dirname "$0")" && pwd)"
export CLAUDE_PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(cd "$dir/../.." && pwd)}"
fail=0

bash_case() {
  local want=$1 cmd=$2 got
  jq -n --arg c "$cmd" '{tool_input:{command:$c}}' | "$dir/guard-bash.sh" >/dev/null 2>&1
  got=$?
  if [ "$got" -eq "$want" ]; then echo "PASS  bash  $cmd"; else echo "FAIL  bash  $cmd (exit $got, want $want)"; fail=1; fi
}
path_case() {
  local want=$1 file=$2 got
  jq -n --arg f "$file" '{tool_input:{file_path:$f}}' | "$dir/guard-paths.sh" >/dev/null 2>&1
  got=$?
  if [ "$got" -eq "$want" ]; then echo "PASS  edit  $file"; else echo "FAIL  edit  $file (exit $got, want $want)"; fail=1; fi
}

# 2 = blocked, 0 = allowed
bash_case 2 "git push origin main"
bash_case 2 "git push -u origin HEAD:main"
bash_case 2 "git push --force origin feature"
bash_case 2 "git push --force-with-lease origin feature"
bash_case 2 "git push -f origin feature"
bash_case 2 "git push origin +feature"
bash_case 0 "git push -u origin feature"
bash_case 0 "git push -u origin claude/step0-tooling"
bash_case 0 "git push -u origin feature --follow-tags"
bash_case 2 "git reset --hard origin/main"
bash_case 0 "git reset --soft HEAD~1"
bash_case 2 "sftp user@example-host"
bash_case 2 "cd x && scp a.php host:/public_html/"
bash_case 2 "lftp -e 'put index.html' host"
bash_case 2 "rsync -av ./ host:/public_html/"
bash_case 2 "curl -T index.html ftp://host/"
bash_case 0 "curl -sS https://example.com"
bash_case 2 "rm data/.demo_mode"
bash_case 2 "echo 0 > data/.demo_mode"
bash_case 0 "cat data/.demo_mode"
bash_case 2 "sed -i s/old/new/ api/seed.php"
bash_case 0 "grep -n Password api/seed.php"
bash_case 2 "rm -rf ../kairosflow/x"
bash_case 0 "git status && git log --oneline -3"
bash_case 0 "php -l api/auth.php"

path_case 2 "$CLAUDE_PROJECT_DIR/data/.demo_mode"
path_case 2 "$CLAUDE_PROJECT_DIR/api/seed.php"
path_case 2 "/home/user/kairosflow/index.php"
path_case 0 "$CLAUDE_PROJECT_DIR/api/auth.php"

[ "$fail" -eq 0 ] && echo "ALL PASS" || echo "SOME FAILED"
exit "$fail"

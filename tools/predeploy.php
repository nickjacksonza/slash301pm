<?php
declare(strict_types=1);

/**
 * Pre-deploy guard and SFTP manifest.
 *
 * Usage: php tools/predeploy.php <from-ref> [<to-ref>|WORKTREE] [--no-tests] [--no-build] [--repo=PATH]
 *   <from-ref>  what is live now (e.g. the commit or tag you last uploaded)
 *   <to-ref>    what you are about to upload (default HEAD). WORKTREE checks the
 *               working tree as it is now, including uncommitted and new files.
 *   --no-tests  skip php tests/run.php
 *   --no-build  skip the Tailwind rebuild comparison
 *   --repo=PATH check another checkout (used by the tests' fixture repos)
 *
 * Prints the files to upload and delete in the owner's deploy order (sub-folder
 * .htaccess, backend (migrations first, index.php last), frontend, root
 * .htaccess last, then deletes) and:
 * FAILS when
 *   - the upload set holds anything that must never reach the server
 *     (tests/, tools/, docs/, styles/, .claude/, data/ runtime files, .env,
 *     databases, unzipper.php, archives, api/seed.php, backup/, data/.demo_mode);
 *   - an uploaded PHP file has a syntax error, or a migration fails the SQL lint;
 *   - public/js/datastar.js does not match public/js/datastar.js.sha256;
 *   - a migration file does not match migrations/CHECKSUMS.txt, a shipped
 *     migration was edited or deleted in the range, or one is not listed;
 *   - a deny .htaccess (app/, vendor/, migrations/, tests/, tools/, data/,
 *     data/sessions/, data/logs/, api/) is missing;
 *   - the test suite fails (working tree; skip with --no-tests).
 * WARNS (does not fail) when public/css/app.css is older than the Tailwind
 * input or a fresh offline build differs from the committed file.
 * It only reads git and the working tree; it never connects to the server.
 */

$args = array_slice($argv, 1);
$opts = ['no-tests' => false, 'no-build' => false, 'repo' => dirname(__DIR__)];
$pos = [];
foreach ($args as $a) {
    if ($a === '--no-tests' || $a === '--no-build') {
        $opts[substr($a, 2)] = true;
    } elseif (str_starts_with($a, '--repo=')) {
        $opts['repo'] = rtrim(substr($a, 7), '/');
    } else {
        $pos[] = $a;
    }
}
$root = (string) $opts['repo'];
$from = $pos[0] ?? null;
$to = $pos[1] ?? 'HEAD';
if ($from === null) {
    fwrite(STDERR, "Usage: php tools/predeploy.php <from-ref> [<to-ref>|WORKTREE] [--no-tests] [--no-build] [--repo=PATH]\n");
    exit(64);
}
$worktree = $to === 'WORKTREE';

/** @return list<string> */
function git(string $root, string $args): array
{
    $out = [];
    $code = 0;
    exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>&1', $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, "git $args failed:\n" . implode("\n", $out) . "\n");
        exit(1);
    }
    return $out;
}

/** A file's exact bytes at $to (or in the working tree); null when absent. */
function file_at(string $root, string $to, bool $worktree, string $path): ?string
{
    if ($worktree) {
        $full = $root . '/' . $path;
        return is_file($full) ? (string) file_get_contents($full) : null;
    }
    $spec = escapeshellarg($to . ':' . $path);
    $exists = 0;
    $ignored = [];
    exec('git -C ' . escapeshellarg($root) . ' cat-file -e ' . $spec . ' 2>/dev/null', $ignored, $exists);
    if ($exists !== 0) {
        return null;
    }
    $p = proc_open(['git', '-C', $root, 'show', $to . ':' . $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) {
        return null;
    }
    $bytes = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($p);
    return $bytes;
}

/** @return list<string> paths under $dir/ at $to (or in the working tree, tracked or new) */
function list_at(string $root, string $to, bool $worktree, string $dir): array
{
    if ($worktree) {
        $files = git($root, 'ls-files --cached --others --exclude-standard -- ' . escapeshellarg($dir));
        return array_values(array_filter($files, static fn (string $f): bool => is_file($root . '/' . $f)));
    }
    return git($root, 'ls-tree -r --name-only ' . escapeshellarg($to) . ' -- ' . escapeshellarg($dir));
}

// Never uploaded: repo tooling, docs, tests, sources that are build inputs.
const NOT_DEPLOYED = [
    '#^\.(git|claude|github|playwright|vscode|idea)(/|$)#',
    '#^\.git(ignore|attributes)$#',
    '#^(docs|tests|tools|styles)/#',
    '#^migrations/CHECKSUMS\.txt$#',
    '#\.md$#i',
    '#^user-switcher-open\.yaml$#',
    '#^LICENSE#',
];

// Must never reach the server. Uploading any of these fails the check. The
// repo-only folders are listed too, so a mistake in NOT_DEPLOYED can never
// put them in the manifest.
const FORBIDDEN = [
    '#(^|/)unzipper\.php$#i' => 'server unzip tool',
    '#\.(zip|tar|tgz|gz|bz2|xz|7z|rar)$#i' => 'archive',
    '#^api/seed\.php$#' => 'seed script (creates users with the shared seed password)',
    '#^backup(/|$)#' => 'old backup copies',
    '#\.(db|sqlite|sqlite3|db-wal|db-shm|db-journal)$#i' => 'database file',
    '#^data/\.demo_mode$#' => 'demo mode switch (the owner sets this by hand on the server)',
    '#^data/(?!(.*/)?\.htaccess$)#' => 'runtime data (only the deny .htaccess files in data/ are uploaded)',
    '#(^|/)\.env(\.|$)#' => 'environment secrets',
    '#^(tests|tools|docs|styles|\.claude)(/|$)#' => 'repo-only folder',
];

// Folders that must carry their own deny .htaccess at <to>.
const DENY_HTACCESS = ['app', 'vendor', 'migrations', 'tests', 'tools', 'data', 'data/sessions', 'data/logs'];

function deployGroup(string $path): string
{
    if ($path === '.htaccess') {
        return '4 root .htaccess';
    }
    if (str_ends_with($path, '.htaccess')) {
        return '1 sub-folder .htaccess';
    }
    if (preg_match('#^(api|app|vendor|migrations|data)/#', $path) || $path === 'index.php') {
        return '2 backend';
    }
    return '3 frontend';
}

/**
 * Order inside the backend step: migrations first (the code still on the server
 * applies them on its next request, and the new code may need the new tables),
 * then vendor/, data/, api/, app/, and index.php last (it loads app/).
 */
function backendRank(string $path): int
{
    foreach (['migrations/' => 0, 'vendor/' => 1, 'data/' => 2, 'api/' => 3, 'app/' => 4] as $prefix => $rank) {
        if (str_starts_with($path, $prefix)) {
            return $rank;
        }
    }
    return 5;
}

/** @return string|null why $path must never be uploaded */
function forbiddenReason(string $path): ?string
{
    foreach (FORBIDDEN as $re => $why) {
        if (preg_match($re, $path)) {
            return $why;
        }
    }
    return null;
}

// ---- 1. the change set ---------------------------------------------------------------------
if ($worktree) {
    $changes = git($root, 'diff --name-status --no-renames ' . escapeshellarg($from));
    foreach (git($root, 'ls-files --others --exclude-standard') as $new) {
        $changes[] = "A\t" . $new;
    }
} else {
    $changes = git($root, 'diff --name-status --no-renames ' . escapeshellarg($from) . ' ' . escapeshellarg($to));
}
$upload = [];
$delete = [];
$blocked = [];
$skipped = [];
$editedMigrations = [];
foreach ($changes as $line) {
    if ($line === '') {
        continue;
    }
    [$status, $path] = preg_split('/\t/', $line, 2) + [1 => ''];
    if (preg_match('#^migrations/\d{4}_[a-z0-9_]+\.sql$#', $path) && ($status === 'M' || $status === 'D')) {
        $editedMigrations[] = "$path (" . ($status === 'M' ? 'edited' : 'deleted') . ' after it shipped)';
    }
    $isDeploy = true;
    foreach (NOT_DEPLOYED as $re) {
        if (preg_match($re, $path)) {
            $isDeploy = false;
            break;
        }
    }
    if ($status === 'D') {
        if ($isDeploy) {
            $delete['5 deletes'][] = $path;
        }
        continue;
    }
    if (!$isDeploy) {
        $skipped[] = $path;
        continue;
    }
    $why = forbiddenReason($path);
    if ($why !== null) {
        $blocked[] = "$path ($why)";
        continue;
    }
    $upload[deployGroup($path)][] = $path;
}
// Final guard over the manifest itself.
foreach ($upload as $files) {
    foreach ($files as $path) {
        $why = forbiddenReason($path);
        if ($why !== null) {
            $blocked[] = "$path ($why)";
        }
    }
}

$fails = [];
$warns = [];
$passes = [];

// ---- 2. php -l on every PHP file that would be uploaded -----------------------------------
$lintErrors = [];
foreach ($upload as $files) {
    foreach ($files as $path) {
        if (!str_ends_with($path, '.php')) {
            continue;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'lint');
        file_put_contents($tmp, (string) file_at($root, $to, $worktree, $path));
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        unlink($tmp);
        if ($code !== 0) {
            $lintErrors[] = "$path: " . implode(' ', $out);
        }
    }
}
if ($blocked !== []) {
    $fails[] = "these must never be uploaded:\n    " . implode("\n    ", array_unique($blocked));
}
if ($lintErrors !== []) {
    $fails[] = "PHP syntax errors:\n    " . implode("\n    ", $lintErrors);
}

// ---- 3. migrations: SQL lint, checksums, never edited ---------------------------------------
$migrations = array_values(array_filter(list_at($root, $to, $worktree, 'migrations'), static fn (string $f): bool => preg_match('#^migrations/\d{4}_[a-z0-9_]+\.sql$#', $f) === 1));
sort($migrations);
$sqlDir = sys_get_temp_dir() . '/predeploy_sql_' . bin2hex(random_bytes(4));
mkdir($sqlDir, 0700);
$actual = [];
foreach ($migrations as $m) {
    $bytes = (string) file_at($root, $to, $worktree, $m);
    $actual[basename($m)] = hash('sha256', $bytes);
    file_put_contents($sqlDir . '/' . basename($m), $bytes);
}
if ($migrations !== []) {
    $out = [];
    $code = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/lint-sql.php') . ' ' . implode(' ', array_map('escapeshellarg', glob($sqlDir . '/*.sql') ?: [])) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $fails[] = "SQL lint (SQLite 3.34, additive only):\n    " . implode("\n    ", $out);
    } else {
        $passes[] = 'SQL lint: ' . count($migrations) . ' migration(s) clean';
    }
}
foreach (glob($sqlDir . '/*') ?: [] as $f) {
    unlink($f);
}
rmdir($sqlDir);
if ($editedMigrations !== []) {
    $fails[] = "shipped migrations must never change (add a new migration instead):\n    " . implode("\n    ", $editedMigrations);
}
$sums = file_at($root, $to, $worktree, 'migrations/CHECKSUMS.txt');
if ($sums === null) {
    if ($migrations !== []) {
        $warns[] = 'no migrations/CHECKSUMS.txt at ' . $to . '; migration checksums not verified (create it with php tools/gen-checksums.php)';
    }
} else {
    $listed = [];
    foreach (preg_split('/\R/', $sums) ?: [] as $line) {
        if (preg_match('/^([0-9a-f]{64})\s+(\d{4}_[a-z0-9_]+\.sql)$/', trim($line), $mm) === 1) {
            $listed[$mm[2]] = $mm[1];
        }
    }
    $sumProblems = [];
    foreach ($actual as $name => $sha) {
        if (!isset($listed[$name])) {
            $sumProblems[] = "$name is not in migrations/CHECKSUMS.txt (run php tools/gen-checksums.php)";
        } elseif ($listed[$name] !== $sha) {
            $sumProblems[] = "$name sha256 $sha does not match CHECKSUMS.txt {$listed[$name]} (edited after it shipped?)";
        }
    }
    foreach ($listed as $name => $sha) {
        if (!isset($actual[$name])) {
            $sumProblems[] = "$name is in CHECKSUMS.txt but the file is missing";
        }
    }
    if ($sumProblems !== []) {
        $fails[] = "migration checksums:\n    " . implode("\n    ", $sumProblems);
    } else {
        $passes[] = 'migration checksums: ' . count($actual) . ' file(s) match migrations/CHECKSUMS.txt';
    }
}

// ---- 4. datastar.js pin -----------------------------------------------------------------------
$ds = file_at($root, $to, $worktree, 'public/js/datastar.js');
$dsPin = file_at($root, $to, $worktree, 'public/js/datastar.js.sha256');
if ($ds !== null) {
    $want = $dsPin === null ? '' : (string) strtok($dsPin, " \t\r\n");
    $got = hash('sha256', $ds);
    if ($want === '' || !hash_equals($want, $got)) {
        $fails[] = "public/js/datastar.js sha256 $got does not match public/js/datastar.js.sha256 (" . ($want === '' ? 'missing' : $want) . ')';
    } else {
        $passes[] = 'datastar.js matches its pinned sha256';
    }
}

// ---- 5. deny .htaccess files -------------------------------------------------------------------
$missingDeny = [];
foreach (DENY_HTACCESS as $dir) {
    if ($dir === 'data/logs' || $dir === 'data/sessions' || list_at($root, $to, $worktree, $dir) !== []) {
        $h = file_at($root, $to, $worktree, $dir . '/.htaccess');
        if ($h === null || (stripos($h, 'Require all denied') === false && stripos($h, 'Deny from all') === false)) {
            $missingDeny[] = $dir . '/.htaccess';
        }
    }
}
if (list_at($root, $to, $worktree, 'api') !== [] && file_at($root, $to, $worktree, 'api/.htaccess') === null) {
    $missingDeny[] = 'api/.htaccess (protects api/seed.php and database files)';
}
if ($missingDeny !== []) {
    $fails[] = "deny .htaccess missing or not denying:\n    " . implode("\n    ", $missingDeny);
} else {
    $passes[] = 'deny .htaccess present in every internal folder';
}

// ---- 6. Tailwind build (warn only) ---------------------------------------------------------
$cssOut = 'public/css/app.css';
$cssIn = 'styles/app.css';
if (file_at($root, $to, $worktree, $cssOut) !== null && file_at($root, $to, $worktree, $cssIn) !== null) {
    if (!$worktree) {
        $tOut = (int) (git($root, 'log -1 --format=%ct ' . escapeshellarg($to) . ' -- ' . $cssOut)[0] ?? 0);
        $tIn = 0;
        foreach (git($root, 'log -1 --format=%ct ' . escapeshellarg($to) . ' -- styles') as $t) {
            $tIn = max($tIn, (int) $t);
        }
        if ($tIn > $tOut) {
            $warns[] = "$cssOut was last committed before a change in styles/; run bash tools/build-css.sh and commit the output";
        }
    }
    if (!$opts['no-build']) {
        $tmpCss = sys_get_temp_dir() . '/predeploy_css_' . bin2hex(random_bytes(4)) . '.css';
        $out = [];
        $code = 0;
        // Offline: the pinned CLI from the npx cache only; never a download.
        exec('cd ' . escapeshellarg($root) . ' && timeout 120 npx -y --offline "@tailwindcss/cli@' . (getenv('TAILWIND_VERSION') ?: '4.3.3') . '" -i ' . $cssIn . ' -o ' . escapeshellarg($tmpCss) . ' --minify 2>&1', $out, $code);
        if ($code !== 0 || !is_file($tmpCss)) {
            $warns[] = 'could not rebuild Tailwind offline to compare (' . trim((string) end($out)) . '); run bash tools/build-css.sh once with network';
        } elseif (!hash_equals((string) hash_file('sha256', $tmpCss), hash('sha256', (string) file_get_contents($root . '/' . $cssOut)))) {
            $warns[] = "a fresh Tailwind build of the working tree differs from $cssOut; run bash tools/build-css.sh and commit the output";
        } else {
            $passes[] = 'Tailwind rebuild matches ' . $cssOut . ' (working tree)';
        }
        if (is_file($tmpCss)) {
            unlink($tmpCss);
        }
    }
}

// ---- 7. tests (working tree) -----------------------------------------------------------------
if (!$opts['no-tests'] && is_file($root . '/tests/run.php')) {
    $out = [];
    $code = 0;
    exec('cd ' . escapeshellarg($root) . ' && php tests/run.php 2>/dev/null', $out, $code);
    $summary = trim((string) end($out));
    if ($code !== 0) {
        $fails[] = "tests failed (working tree): $summary\n    " . implode("\n    ", array_slice(array_filter($out, static fn (string $l): bool => str_starts_with($l, 'FAIL')), 0, 10));
    } else {
        $passes[] = "tests (working tree): $summary";
    }
}

// ---- report ------------------------------------------------------------------------------------
ksort($upload);
if (isset($upload['2 backend'])) {
    usort($upload['2 backend'], static fn (string $a, string $b): int => [backendRank($a), $a] <=> [backendRank($b), $b]);
}
ksort($delete);
echo "SFTP manifest: $from -> $to (target public_html/projects/slash301pm/)\n\n";
foreach (['1 sub-folder .htaccess', '2 backend', '3 frontend', '4 root .htaccess', '5 deletes'] as $group) {
    $up = $upload[$group] ?? [];
    $del = $delete[$group] ?? [];
    if ($up === [] && $del === []) {
        continue;
    }
    echo "Step $group\n";
    foreach ($up as $p) {
        echo "  upload  $p\n";
    }
    foreach ($del as $p) {
        echo "  delete  $p\n";
    }
    echo "\n";
}
if ($upload === [] && $delete === []) {
    echo "Nothing to upload or delete.\n\n";
}
if (isset($upload['2 backend'])) {
    echo "Step 2 order matters: migrations/ first, then vendor/, api/, app/ (whole folders), index.php last.\n";
}
echo "After upload, open /slash301pm/healthz once; it runs pending migrations and writes a backup in data/backups/.\n";
echo "Always check on the server: no unzipper.php, no *.zip or other archives in the web root, no api/seed.php.\n";
if (isset($upload['3 frontend']) && in_array('legacy/index.html', $upload['3 frontend'], true)) {
    echo "Reminder: bump ?v=N on the CSS/JS includes in legacy/index.html for Cloudflare cache busting.\n";
}
if ($skipped !== []) {
    echo "\nNot for the server (repo only): " . count($skipped) . " file(s).\n";
}
echo "\n";
foreach ($passes as $p) {
    echo "ok    $p\n";
}
foreach ($warns as $w) {
    echo "WARN  $w\n";
}
foreach ($fails as $f) {
    echo "FAIL  $f\n";
}
echo $fails !== [] ? "\nPre-deploy check FAILED.\n" : "\nPre-deploy check passed" . ($warns !== [] ? ' with ' . count($warns) . ' warning(s)' : '') . ".\n";
exit($fails !== [] ? 1 : 0);

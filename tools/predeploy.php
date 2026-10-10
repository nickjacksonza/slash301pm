<?php
declare(strict_types=1);

/**
 * Pre-deploy guard and SFTP manifest.
 *
 * Usage: php tools/predeploy.php <from-ref> [<to-ref>]
 *   <from-ref>  what is live now (e.g. the commit or tag you last uploaded)
 *   <to-ref>    what you are about to upload (default HEAD)
 *
 * Prints the files to upload and delete, grouped in the owner's deploy order
 * (sub-folder .htaccess, backend, frontend, root .htaccess last, then deletes),
 * and fails if anything that must
 * never reach the server would be uploaded. It only reads git; it never
 * connects to the server.
 */

$root = dirname(__DIR__);
$from = $argv[1] ?? null;
$to = $argv[2] ?? 'HEAD';
if ($from === null) {
    fwrite(STDERR, "Usage: php tools/predeploy.php <from-ref> [<to-ref>]\n");
    exit(64);
}

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

// Never uploaded: repo tooling, docs, tests, sources that are build inputs.
const NOT_DEPLOYED = [
    '#^\.(git|claude|github|playwright|vscode|idea)(/|$)#',
    '#^\.git(ignore|attributes)$#',
    '#^(docs|tests|tools|styles)/#',
    '#\.md$#i',
    '#^user-switcher-open\.yaml$#',
    '#^LICENSE#',
];

// Must never reach the server. Uploading any of these fails the check.
const FORBIDDEN = [
    '#(^|/)unzipper\.php$#i' => 'server unzip tool',
    '#\.(zip|tar|tgz|gz|7z|rar)$#i' => 'archive',
    '#^api/seed\.php$#' => 'seed script (creates users with the shared seed password)',
    '#^backup/#' => 'old backup copies',
    '#\.(db|sqlite|sqlite3|db-wal|db-shm)$#i' => 'database file',
    '#^data/\.demo_mode$#' => 'demo mode switch (the owner sets this by hand on the server)',
    '#^data/(sessions|backups|logs)/(?!\.htaccess$)#' => 'runtime data',
    '#(^|/)\.env(\.|$)#' => 'environment secrets',
];

// Safe order: sub-folder .htaccess files first (so app/, vendor/ and friends are never
// exposed), the ROOT .htaccess last (it routes to index.php, app/, vendor/ and legacy/,
// which must already be on the server), deletes after all uploads.
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

$changes = git($root, 'diff --name-status --no-renames ' . escapeshellarg($from) . ' ' . escapeshellarg($to));
$upload = [];
$delete = [];
$blocked = [];
$skipped = [];
foreach ($changes as $line) {
    if ($line === '') {
        continue;
    }
    [$status, $path] = preg_split('/\t/', $line, 2) + [1 => ''];
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
    foreach (FORBIDDEN as $re => $why) {
        if (preg_match($re, $path)) {
            $blocked[] = "$path ($why)";
            continue 2;
        }
    }
    if (!$isDeploy) {
        $skipped[] = $path;
        continue;
    }
    $upload[deployGroup($path)][] = $path;
}

// php -l on every PHP file that would be uploaded (as it is in <to-ref>).
$lintErrors = [];
foreach ($upload as $files) {
    foreach ($files as $path) {
        if (!str_ends_with($path, '.php')) {
            continue;
        }
        $src = implode("\n", git($root, 'show ' . escapeshellarg($to . ':' . $path)));
        $tmp = tempnam(sys_get_temp_dir(), 'lint');
        file_put_contents($tmp, $src);
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        unlink($tmp);
        if ($code !== 0) {
            $lintErrors[] = "$path: " . implode(' ', $out);
        }
    }
}

ksort($upload);
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
echo "After upload, open /slash301pm/healthz once; it runs pending migrations and writes a backup in data/backups/.\n";
echo "Always check on the server: no unzipper.php, no *.zip or other archives in the web root, no api/seed.php.\n";
if (isset($upload['3 frontend']) && in_array('legacy/index.html', $upload['3 frontend'], true)) {
    echo "Reminder: bump ?v=N on the CSS/JS includes in legacy/index.html for Cloudflare cache busting.\n";
}
if ($skipped !== []) {
    echo "\nNot for the server (repo only): " . count($skipped) . " file(s).\n";
}

$fail = false;
if ($blocked !== []) {
    $fail = true;
    echo "\nFAIL: these must never be uploaded:\n  " . implode("\n  ", $blocked) . "\n";
}
if ($lintErrors !== []) {
    $fail = true;
    echo "\nFAIL: PHP syntax errors:\n  " . implode("\n  ", $lintErrors) . "\n";
}
echo $fail ? "\nPre-deploy check FAILED.\n" : "\nPre-deploy check passed.\n";
exit($fail ? 1 : 0);

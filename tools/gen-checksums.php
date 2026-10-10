<?php
declare(strict_types=1);

/**
 * Keeps migrations/CHECKSUMS.txt: the sha256 of every migrations/NNNN_name.sql,
 * the same value the Migrator stores in schema_migrations.sha256 when it
 * applies the file. tools/predeploy.php fails when a file and its line differ,
 * which catches an edited shipped migration before it reaches the server.
 *
 *   php tools/gen-checksums.php           add lines for new migrations
 *   php tools/gen-checksums.php --check   only verify (exit 1 on any difference)
 *
 * An existing line is never rewritten: a changed checksum means a shipped
 * migration was edited, and the fix is a new migration (sqlite-migration skill).
 */

$root = dirname(__DIR__);
$check = in_array('--check', array_slice($argv, 1), true);
$file = $root . '/migrations/CHECKSUMS.txt';

/** @return array<string,string> file name => sha256 */
function checksums_read(string $file): array
{
    $out = [];
    if (!is_file($file)) {
        return $out;
    }
    foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
        if (preg_match('/^([0-9a-f]{64})\s+(\d{4}_[a-z0-9_]+\.sql)$/', trim($line), $m) === 1) {
            $out[$m[2]] = $m[1];
        }
    }
    return $out;
}

$listed = checksums_read($file);
$actual = [];
foreach (glob($root . '/migrations/*.sql') ?: [] as $path) {
    $actual[basename($path)] = (string) hash_file('sha256', $path);
}
ksort($actual);

$problems = [];
$added = [];
foreach ($actual as $name => $sha) {
    if (!isset($listed[$name])) {
        $added[] = $name;
    } elseif ($listed[$name] !== $sha) {
        $problems[] = "$name: file sha256 $sha differs from CHECKSUMS.txt {$listed[$name]} (a shipped migration was edited; restore it and add a new migration instead)";
    }
}
foreach ($listed as $name => $sha) {
    if (!isset($actual[$name])) {
        $problems[] = "$name: listed in CHECKSUMS.txt but the file is missing (never delete a shipped migration)";
    }
}

if ($problems !== []) {
    fwrite(STDERR, "FAIL:\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}
if ($check) {
    if ($added !== []) {
        fwrite(STDERR, "FAIL: not in CHECKSUMS.txt yet (run php tools/gen-checksums.php): " . implode(', ', $added) . "\n");
        exit(1);
    }
    echo 'CHECKSUMS.txt matches ' . count($actual) . " migration file(s).\n";
    exit(0);
}

$lines = [
    '# sha256 of each migration file, as stored in schema_migrations.sha256 when applied.',
    '# Written by php tools/gen-checksums.php (new files only); checked by tools/predeploy.php.',
    '# A shipped migration is never edited: a fix is a new migration.',
];
foreach ($actual as $name => $sha) {
    $lines[] = $sha . '  ' . $name;
}
file_put_contents($file, implode("\n", $lines) . "\n");
echo $added === [] ? "CHECKSUMS.txt already complete.\n" : 'Added: ' . implode(', ', $added) . "\n";

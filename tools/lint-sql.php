<?php
declare(strict_types=1);

/**
 * Rejects SQL the live server cannot run (SQLite 3.34.1) or that breaks the
 * additive-only migration rules while the legacy app shares the database.
 *
 * Usage: php tools/lint-sql.php <file.sql> [...]   (no args: every migrations/*.sql)
 */

const RULES = [
    '/\bRETURNING\b/i' => 'RETURNING needs SQLite 3.35; generate IDs in PHP instead',
    '/\bDROP\s+COLUMN\b/i' => 'DROP COLUMN needs SQLite 3.35 and breaks the legacy app',
    '/\)\s*STRICT\b/i' => 'STRICT tables need SQLite 3.37',
    '/\bunixepoch\s*\(/i' => 'unixepoch() needs SQLite 3.38; use strftime(\'%s\',\'now\')',
    '/->>?/' => 'JSON -> and ->> operators need SQLite 3.38',
    '/\bjson_\w+\s*\(/i' => 'JSON1 functions are not guaranteed on the server; build JSON in PHP',
    '/\bDROP\s+(TABLE|INDEX|TRIGGER|VIEW)\b/i' => 'no DROP while migrations must stay additive',
    '/\bALTER\s+TABLE\s+\S+\s+RENAME\b/i' => 'no renames while the legacy app uses the schema',
    '/\bGENERATED\s+ALWAYS\b/i' => 'avoid generated columns (3.31+) for Go/legacy compatibility',
    '/\bON\s+CONFLICT\s*\([^)]*\)\s*DO\s+UPDATE[\s\S]*?\bWHERE\b[\s\S]*?\bON\s+CONFLICT\b/i' => 'multiple ON CONFLICT clauses need SQLite 3.35',
];

$files = array_slice($argv, 1);
if ($files === []) {
    $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
}
$problems = 0;
foreach ($files as $file) {
    $sql = (string) file_get_contents($file);
    // Ignore comments so explanations can mention forbidden words.
    $code = preg_replace(['~--[^\n]*~', '~/\*[\s\S]*?\*/~'], '', $sql) ?? $sql;
    foreach (RULES as $re => $why) {
        if (preg_match_all($re, $code, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$text, $offset]) {
                $line = substr_count(substr($code, 0, $offset), "\n") + 1;
                echo basename($file) . ":$line: '" . trim($text) . "': $why\n";
                $problems++;
            }
        }
    }
    if (preg_match('/\bCREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', $code)) {
        echo basename($file) . ": CREATE TABLE without IF NOT EXISTS (migrations must be safe to re-run)\n";
        $problems++;
    }
    if (preg_match('/\bCREATE\s+(UNIQUE\s+)?INDEX\s+(?!IF\s+NOT\s+EXISTS)/i', $code)) {
        echo basename($file) . ": CREATE INDEX without IF NOT EXISTS\n";
        $problems++;
    }
}
exit($problems > 0 ? 1 : 0);

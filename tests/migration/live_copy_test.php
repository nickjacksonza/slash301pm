<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Store\Db;
use App\Store\Migrator;

require_once dirname(__DIR__) . '/support/app.php';

/**
 * Rehearsal on the owner's download of the live database (data/live-copy.db,
 * gitignored). Works on a temp COPY; the download itself is only read.
 * Skips (passes with a note) when the file is absent.
 */
function lc_copy(): ?string
{
    $src = dirname(__DIR__, 2) . '/data/live-copy.db';
    if (!is_file($src)) {
        fwrite(STDERR, "note: data/live-copy.db not present, live-copy rehearsal skipped\n");
        return null;
    }
    $dir = ts_temp_dir();
    copy($src, $dir . '/live.db');
    if (is_file($src . '-wal') && filesize($src . '-wal') > 0) {
        copy($src . '-wal', $dir . '/live.db-wal');
    }
    return $dir;
}

/** @return array<string,int> */
function lc_counts(Db $db): array
{
    $out = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'schema_migrations' ORDER BY name") as $r) {
        $out[(string) $r['name']] = (int) $db->scalar('SELECT COUNT(*) FROM "' . str_replace('"', '""', (string) $r['name']) . '"');
    }
    return $out;
}

/** @return array<string,string> */
function lc_schema(Db $db): array
{
    $out = [];
    foreach ($db->query("SELECT type, name, sql FROM sqlite_master WHERE name <> 'schema_migrations' AND name NOT LIKE 'sqlite_autoindex_schema_migrations%' ORDER BY type, name") as $r) {
        $out[$r['type'] . ' ' . $r['name']] = (string) $r['sql'];
    }
    return $out;
}

return [
    'live copy: 0001 is a no-op (counts and schema unchanged), second run does nothing' => function (): void {
        $dir = lc_copy();
        if ($dir === null) {
            return;
        }
        $db = Db::open($dir . '/live.db');
        $countsBefore = lc_counts($db);
        $schemaBefore = lc_schema($db);
        $m = new Migrator($db, dirname(__DIR__, 2) . '/migrations', $dir . '/backups', $dir . '/migrate.lock', $dir . '/migrate-failed.json', new FixedClock(new DateTimeImmutable('2026-10-09 10:00:00')));
        $res = $m->run();
        t_true($res->ok, $res->error);
        t_eq(1, $m->currentVersion());
        t_eq($countsBefore, lc_counts($db), 'row counts');
        t_eq($schemaBefore, lc_schema($db), 'schema objects');
        t_true($res->backupPath !== null && is_file($res->backupPath), 'backup');
        t_eq($countsBefore, lc_counts(Db::open((string) $res->backupPath)), 'backup holds the same rows');
        t_eq('ok', (string) $db->scalar('PRAGMA integrity_check'));
        t_eq([], $m->run()->applied, 'second run');
    },
    'live copy: legacy-style writes still succeed after migrating' => function (): void {
        $dir = lc_copy();
        if ($dir === null) {
            return;
        }
        $db = Db::open($dir . '/live.db');
        $m = new Migrator($db, dirname(__DIR__, 2) . '/migrations', $dir . '/backups', $dir . '/migrate.lock', $dir . '/migrate-failed.json', new FixedClock(new DateTimeImmutable('2026-10-09 10:00:00')));
        t_true($m->run()->ok);
        $job = $db->one('SELECT id FROM jobs LIMIT 1');
        if ($job !== null) {
            t_eq(1, $db->exec("UPDATE jobs SET status = 'In Progress' WHERE id = :id", ['id' => $job['id']]));
        }
        // the legacy SQLite3 connection (api/db.php style) opens and reads the migrated file
        $legacy = new SQLite3($dir . '/live.db');
        $legacy->enableExceptions(true);
        t_true((int) $legacy->querySingle('SELECT COUNT(*) FROM users') > 0);
        $legacy->close();
    },
];

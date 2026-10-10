<?php
declare(strict_types=1);

// Live-copy rehearsal helpers (data/live-copy.db, gitignored). Shared by
// tests/migration/live_copy_test.php and the Phase 3 job search performance test.

use App\Clock\FixedClock;
use App\Store\Db;
use App\Store\Migrator;

require_once __DIR__ . '/app.php';

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

function lc_migrator(Db $db, string $dir): Migrator
{
    return new Migrator($db, dirname(__DIR__, 2) . '/migrations', $dir . '/backups', $dir . '/migrate.lock', $dir . '/migrate-failed.json', new FixedClock(new DateTimeImmutable('2026-10-09 10:00:00')));
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

/** Migrated temp copy, or null when the download is absent. @return array{0:Db,1:string,2:array<string,int>}|null */
function lc_migrated(): ?array
{
    $dir = lc_copy();
    if ($dir === null) {
        return null;
    }
    $db = Db::open($dir . '/live.db');
    $before = lc_counts($db);
    $res = lc_migrator($db, $dir)->run();
    t_true($res->ok, $res->error);
    return [$db, $dir, $before];
}

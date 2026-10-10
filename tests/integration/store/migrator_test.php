<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Store\Db;
use App\Store\MigrationLocked;
use App\Store\Migrator;

require_once dirname(__DIR__, 2) . '/support/app.php';

function mt_migrator(Db $db, string $dir, string $migrationsDir): Migrator
{
    return new Migrator($db, $migrationsDir, $dir . '/backups', $dir . '/migrate.lock', $dir . '/migrate-failed.json', new FixedClock(new DateTimeImmutable('2026-10-09 10:00:00')));
}

/** name => normalized sql of every table, index and trigger (sqlite_ internals and schema_migrations excluded). */
function mt_schema(Db $db): array
{
    $out = [];
    foreach ($db->query("SELECT type, name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' AND name <> 'schema_migrations' ORDER BY type, name") as $r) {
        $sql = (string) $r['sql'];
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;
        $sql = str_ireplace(' IF NOT EXISTS', '', $sql);
        $sql = preg_replace('/\(\s+/', '(', $sql) ?? $sql;
        $sql = preg_replace('/\s+\)/', ')', $sql) ?? $sql;
        $out[$r['type'] . ' ' . $r['name']] = trim($sql);
    }
    return $out;
}

function mt_root(): string
{
    return dirname(__DIR__, 3);
}

return [
    'fresh install builds exactly the legacy api/db.php schema' => function (): void {
        $dir = ts_temp_dir();
        // 0001 alone must equal the legacy schema; later files are checked by the rehearsal tests.
        mkdir($dir . '/only1');
        copy(mt_root() . '/migrations/0001_baseline.sql', $dir . '/only1/0001_baseline.sql');
        $db = Db::open($dir . '/new.db');
        $m = mt_migrator($db, $dir, $dir . '/only1');
        t_eq(0, $m->currentVersion());
        $res = $m->run();
        t_true($res->ok, $res->error);
        t_eq([1], $res->applied);
        t_eq(1, $m->currentVersion());

        // The schema the legacy app would create on an empty file.
        require_once mt_root() . '/api/db.php';
        $legacy = new SQLite3($dir . '/legacy.db');
        createSchema($legacy);
        $legacy->close();
        $ref = mt_schema(Db::open($dir . '/legacy.db'));
        t_eq($ref, mt_schema($db));
        t_eq(25, count($ref), '11 tables, 4 triggers, 10 indexes');
    },
    'second run is a no-op and the backup exists' => function (): void {
        $dir = ts_temp_dir();
        $db = Db::open($dir . '/new.db');
        $m = mt_migrator($db, $dir, mt_root() . '/migrations');
        $first = $m->run();
        t_true($first->backupPath !== null && is_file($first->backupPath), 'backup written');
        t_true(str_starts_with(basename((string) $first->backupPath), 'pre-0001-20261009-100000'), basename((string) $first->backupPath));
        $latest = $m->latestVersion();
        t_eq(range(1, $latest), $first->applied, 'every file applied in order');
        $second = $m->run();
        t_true($second->ok);
        t_eq([], $second->applied);
        t_eq(null, $second->backupPath);
        t_eq(1, count($m->backups()));
        $st = $m->status();
        t_true($st->isCurrent());
        t_eq($latest, count($st->applied));
        t_eq('baseline', $st->applied[0]->name);
        t_eq([], $st->modified);
    },
    'a failing migration rolls back, writes the failure file and blocks until cleared' => function (): void {
        $dir = ts_temp_dir();
        $mig = $dir . '/migrations';
        mkdir($mig);
        copy(mt_root() . '/migrations/0001_baseline.sql', $mig . '/0001_baseline.sql');
        file_put_contents($mig . '/0002_bad.sql', "CREATE TABLE IF NOT EXISTS half_done (id TEXT);\nINSERT INTO no_such_table VALUES (1);\n");
        $db = Db::open($dir . '/new.db');
        $m = mt_migrator($db, $dir, $mig);
        $res = $m->run();
        t_true(!$res->ok);
        t_eq([1], $res->applied);
        t_eq(1, $m->currentVersion());
        t_eq(null, $db->scalar("SELECT name FROM sqlite_master WHERE name = 'half_done'"), 'rolled back');
        $fail = json_decode((string) $m->failure(), true);
        t_eq(2, $fail['version']);
        t_contains('no_such_table', $fail['error']);
        t_true(!$m->ensureCurrent()->ok, 'gate stays closed');
        // the owner fixes it with a new file and retries from /admin/system
        file_put_contents($mig . '/0002_bad.sql', "CREATE TABLE IF NOT EXISTS half_done (id TEXT);\n");
        $m->clearFailure();
        $retry = $m->ensureCurrent();
        t_true($retry->ok, $retry->error);
        t_eq(2, $m->currentVersion());
        t_eq(2, count($m->backups()));
    },
    'a held lock answers MigrationLocked' => function (): void {
        $dir = ts_temp_dir();
        $db = Db::open($dir . '/new.db');
        $m = mt_migrator($db, $dir, mt_root() . '/migrations');
        $h = fopen($dir . '/migrate.lock', 'c');
        flock($h, LOCK_EX);
        t_throws(static fn () => $m->run(), MigrationLocked::class);
        flock($h, LOCK_UN);
        fclose($h);
        t_true($m->run()->ok);
    },
    'only the last 10 backups are kept' => function (): void {
        $dir = ts_temp_dir();
        mkdir($dir . '/backups');
        for ($i = 0; $i < 12; $i++) {
            $p = sprintf('%s/backups/pre-0001-2026010%d-0000%02d.db', $dir, $i % 10, $i);
            file_put_contents($p, 'x');
            touch($p, 1700000000 + $i);
        }
        $db = Db::open($dir . '/new.db');
        $m = mt_migrator($db, $dir, mt_root() . '/migrations');
        t_true($m->run()->ok);
        $names = array_map(static fn ($b) => $b->name, $m->backups());
        t_eq(10, count($names));
        t_true(str_starts_with($names[0], 'pre-0001-20261009'), 'newest first: ' . $names[0]);
        t_true(!in_array('pre-0001-20260100-000000.db', $names, true), 'oldest pruned');
    },
    'bad file names are refused' => function (): void {
        $dir = ts_temp_dir();
        mkdir($dir . '/m');
        file_put_contents($dir . '/m/1_x.sql', 'SELECT 1;');
        $m = mt_migrator(Db::open($dir . '/n.db'), $dir, $dir . '/m');
        t_throws(static fn () => $m->files(), RuntimeException::class);
    },
];

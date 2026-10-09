<?php
declare(strict_types=1);

use App\Store\Db;

require_once dirname(__DIR__, 2) . '/support/app.php';

function dbt_drivers(): array
{
    $out = [];
    if (extension_loaded('pdo_sqlite')) {
        $out[] = 'pdo_sqlite';
    }
    if (class_exists(SQLite3::class)) {
        $out[] = 'sqlite3';
    }
    return $out;
}

return [
    'both drivers: query, one, scalar, exec, params, types' => function (): void {
        foreach (dbt_drivers() as $driver) {
            $db = Db::open(ts_temp_dir() . '/t.db', $driver);
            t_eq($driver, $db->driver());
            t_eq('wal', (string) $db->scalar('PRAGMA journal_mode'), "$driver wal");
            t_eq(1, (int) $db->scalar('PRAGMA foreign_keys'), "$driver fk");
            t_eq(5000, (int) $db->scalar('PRAGMA busy_timeout'), "$driver busy");
            $db->execScript('CREATE TABLE t (id TEXT PRIMARY KEY, n INTEGER, f REAL, s TEXT); CREATE INDEX t_n ON t(n);');
            t_eq(1, $db->exec('INSERT INTO t (id, n, f, s) VALUES (:id, :n, :f, :s)', ['id' => 'a', 'n' => 5, 'f' => 1.5, 's' => null]));
            t_eq(1, $db->exec('INSERT INTO t (id, n, f, s) VALUES (?, ?, ?, ?)', ['b', true, 2.0, "it's"]));
            t_eq(['id' => 'a', 'n' => 5, 'f' => 1.5, 's' => null], $db->one('SELECT * FROM t WHERE id = :id', [':id' => 'a']), "$driver row a");
            t_eq(1, $db->scalar('SELECT n FROM t WHERE id = ?', ['b']), "$driver bool as int");
            t_eq(null, $db->one('SELECT * FROM t WHERE id = :id', ['id' => 'zz']));
            t_eq(2, count($db->query('SELECT id FROM t ORDER BY id')));
            t_eq(2, $db->exec('UPDATE t SET n = n + 1'));
            t_true($db->sqliteVersion() !== '');
            $db->close();
        }
    },
    'both drivers: SQL errors throw' => function (): void {
        foreach (dbt_drivers() as $driver) {
            $db = Db::open(ts_temp_dir() . '/t.db', $driver);
            t_throws(static fn () => $db->query('SELECT * FROM missing_table'));
            t_throws(static fn () => $db->exec('INSERT INTO nope VALUES (1)'));
        }
    },
    'both drivers: txImmediate commits, rolls back on exception, does not nest' => function (): void {
        foreach (dbt_drivers() as $driver) {
            $db = Db::open(ts_temp_dir() . '/t.db', $driver);
            $db->execScript('CREATE TABLE t (id INTEGER PRIMARY KEY)');
            $got = $db->txImmediate(static function (Db $tx): string {
                $tx->exec('INSERT INTO t (id) VALUES (1)');
                return 'done';
            });
            t_eq('done', $got);
            t_throws(static function () use ($db): void {
                $db->txImmediate(static function (Db $tx): void {
                    $tx->exec('INSERT INTO t (id) VALUES (2)');
                    throw new RuntimeException('boom');
                });
            }, RuntimeException::class);
            t_eq(1, (int) $db->scalar('SELECT COUNT(*) FROM t'), "$driver rollback");
            t_true(!$db->inTransaction());
            t_throws(static fn () => $db->txImmediate(static fn (Db $tx) => $tx->txImmediate(static fn (Db $x) => 1)), LogicException::class);
            t_eq(1, (int) $db->scalar('SELECT COUNT(*) FROM t'));
        }
    },
];

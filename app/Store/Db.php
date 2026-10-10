<?php
declare(strict_types=1);

namespace App\Store;

use Closure;
use LogicException;
use PDO;
use RuntimeException;
use SQLite3;
use SQLite3Stmt;
use Throwable;

/**
 * SQLite connection. PDO (pdo_sqlite) when available, otherwise the SQLite3
 * extension with exceptions on; both expose the same methods and return the
 * same shapes. Every SQL error throws. Go: *sql.DB with modernc.org/sqlite.
 *
 * Parameters: a list binds ?, an assoc array binds :name (key with or without ':').
 */
final class Db
{
    private bool $inTx = false;

    private function __construct(
        private readonly ?PDO $pdo,
        private readonly ?SQLite3 $lite,
        public readonly string $path,
    ) {}

    /** @param string $driver 'auto' | 'pdo_sqlite' | 'sqlite3' */
    public static function open(string $path, string $driver = 'auto'): self
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $usePdo = $driver === 'pdo_sqlite' || ($driver === 'auto' && extension_loaded('pdo_sqlite'));
        if ($usePdo) {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $db = new self($pdo, null, $path);
        } else {
            if (!class_exists(SQLite3::class)) {
                throw new RuntimeException('Neither pdo_sqlite nor sqlite3 is available');
            }
            $lite = new SQLite3($path);
            $lite->enableExceptions(true);
            $lite->busyTimeout(5000);
            $db = new self(null, $lite, $path);
        }
        // Same PRAGMAs as the legacy getDb() (api/db.php).
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA synchronous = NORMAL');
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA temp_store = MEMORY');
        return $db;
    }

    public function driver(): string
    {
        return $this->pdo !== null ? 'pdo_sqlite' : 'sqlite3';
    }

    /** @return list<array<string,mixed>> */
    public function query(string $sql, array $params = []): array
    {
        if ($this->pdo !== null) {
            $st = $this->pdo->prepare($sql);
            $st->execute(self::pdoParams($params));
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return array_values($rows);
        }
        $res = $this->liteStmt($sql, $params)->execute();
        $rows = [];
        while (true) {
            $row = $res->fetchArray(SQLITE3_ASSOC);
            if ($row === false) {
                break;
            }
            $rows[] = $row;
        }
        $res->finalize();
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $rows = $this->query($sql, $params);
        return $rows === [] ? null : $rows[0];
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $row = $this->one($sql, $params);
        if ($row === null) {
            return null;
        }
        foreach ($row as $v) {
            return $v;
        }
        return null;
    }

    /** Runs one statement; returns the number of changed rows. */
    public function exec(string $sql, array $params = []): int
    {
        if ($this->pdo !== null) {
            if ($params === []) {
                $n = $this->pdo->exec($sql);
                return $n === false ? 0 : $n;
            }
            $st = $this->pdo->prepare($sql);
            $st->execute(self::pdoParams($params));
            return $st->rowCount();
        }
        if ($params === []) {
            $this->lite->exec($sql);
            return $this->lite->changes();
        }
        $this->liteStmt($sql, $params)->execute();
        return $this->lite->changes();
    }

    /** Runs a script of several statements (migrations). No parameters. */
    public function execScript(string $sql): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec($sql);
            return;
        }
        $this->lite->exec($sql);
    }

    /**
     * BEGIN IMMEDIATE ... COMMIT around $fn; ROLLBACK and rethrow on any exception.
     * @template T
     * @param Closure(self): T $fn
     * @return T
     */
    public function txImmediate(Closure $fn): mixed
    {
        if ($this->inTx) {
            throw new LogicException('txImmediate does not nest');
        }
        $this->exec('BEGIN IMMEDIATE');
        $this->inTx = true;
        try {
            $result = $fn($this);
            $this->exec('COMMIT');
            $this->inTx = false;
            return $result;
        } catch (Throwable $e) {
            $this->inTx = false;
            try {
                $this->exec('ROLLBACK');
            } catch (Throwable $ignored) {
                // SQLite may already have rolled back (for example on SQLITE_FULL)
            }
            throw $e;
        }
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }

    public function sqliteVersion(): string
    {
        return (string) $this->scalar('SELECT sqlite_version()');
    }

    public function close(): void
    {
        if ($this->lite !== null) {
            $this->lite->close();
        }
    }

    private static function pdoParams(array $params): array
    {
        $isList = array_is_list($params);
        $out = [];
        foreach ($params as $k => $v) {
            $value = is_bool($v) ? ($v ? 1 : 0) : $v;
            if ($isList) {
                $out[] = $value;
            } else {
                $out[str_starts_with((string) $k, ':') ? (string) $k : ':' . $k] = $value;
            }
        }
        return $out;
    }

    private function liteStmt(string $sql, array $params): SQLite3Stmt
    {
        $st = $this->lite->prepare($sql);
        if ($st === false) {
            throw new RuntimeException('prepare failed: ' . $this->lite->lastErrorMsg());
        }
        $isList = array_is_list($params);
        $i = 0;
        foreach ($params as $k => $v) {
            $i++;
            $key = $isList ? $i : (str_starts_with((string) $k, ':') ? (string) $k : ':' . $k);
            if ($v === null) {
                $st->bindValue($key, null, SQLITE3_NULL);
            } elseif (is_bool($v)) {
                $st->bindValue($key, $v ? 1 : 0, SQLITE3_INTEGER);
            } elseif (is_int($v)) {
                $st->bindValue($key, $v, SQLITE3_INTEGER);
            } elseif (is_float($v)) {
                $st->bindValue($key, $v, SQLITE3_FLOAT);
            } else {
                $st->bindValue($key, (string) $v, SQLITE3_TEXT);
            }
        }
        return $st;
    }
}

<?php
declare(strict_types=1);

namespace App\Store;

use App\Clock\Clock;
use RuntimeException;
use Throwable;

/**
 * Applies migrations/NNNN_name.sql in order on the first request after a deploy
 * (see the sqlite-migration skill). PRAGMA user_version is the applied level;
 * schema_migrations records what ran. On failure nothing of that migration is
 * kept, data/migrate-failed.json is written and the new app shows a maintenance
 * page until /admin/system retries. The legacy app keeps working because every
 * migration is additive.
 */
final class Migrator
{
    public const KEEP_BACKUPS = 10;

    public function __construct(
        private readonly Db $db,
        private readonly string $migrationsDir,
        private readonly string $backupsDir,
        private readonly string $lockPath,
        private readonly string $failurePath,
        private readonly Clock $clock,
    ) {}

    /** @return list<MigrationFile> sorted by version */
    public function files(): array
    {
        $paths = glob($this->migrationsDir . '/*.sql');
        $files = [];
        $seen = [];
        foreach ($paths === false ? [] : $paths as $path) {
            $base = basename($path);
            if (preg_match('/^(\d{4})_([a-z0-9_]+)\.sql$/', $base, $m) !== 1) {
                throw new RuntimeException("Migration file name must be NNNN_snake_name.sql: $base");
            }
            $version = (int) $m[1];
            if (isset($seen[$version])) {
                throw new RuntimeException("Two migrations share version $version");
            }
            $seen[$version] = true;
            $files[] = new MigrationFile($version, $m[2], $path, (string) hash_file('sha256', $path));
        }
        usort($files, static fn (MigrationFile $a, MigrationFile $b): int => $a->version <=> $b->version);
        return $files;
    }

    public function currentVersion(): int
    {
        return (int) $this->db->scalar('PRAGMA user_version');
    }

    public function latestVersion(): int
    {
        $latest = 0;
        foreach ($this->files() as $f) {
            $latest = max($latest, $f->version);
        }
        return $latest;
    }

    /** @return list<MigrationFile> */
    public function pending(): array
    {
        $current = $this->currentVersion();
        $out = [];
        foreach ($this->files() as $f) {
            if ($f->version > $current) {
                $out[] = $f;
            }
        }
        return $out;
    }

    public function failure(): ?string
    {
        return is_file($this->failurePath) ? (string) file_get_contents($this->failurePath) : null;
    }

    /** Called from /admin/system before a retry. */
    public function clearFailure(): void
    {
        if (is_file($this->failurePath)) {
            unlink($this->failurePath);
        }
    }

    /** MigrateGate: run pending migrations unless a failure is waiting for the owner. */
    public function ensureCurrent(): MigrationResult
    {
        if ($this->failure() !== null) {
            return new MigrationResult(false, [], null, 'A previous migration failed; see /admin/system.');
        }
        if ($this->pending() === []) {
            return MigrationResult::nothing();
        }
        return $this->run();
    }

    /** @throws MigrationLocked when another request is migrating */
    public function run(): MigrationResult
    {
        if (!is_dir($this->backupsDir)) {
            mkdir($this->backupsDir, 0700, true);
        }
        if (!is_dir(dirname($this->lockPath))) {
            mkdir(dirname($this->lockPath), 0750, true);
        }
        $lock = fopen($this->lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open ' . $this->lockPath);
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new MigrationLocked('Another request is applying migrations');
            }
            return $this->runLocked();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runLocked(): MigrationResult
    {
        $pending = $this->pending();
        if ($pending === []) {
            return MigrationResult::nothing();
        }
        $first = $pending[0];
        $backup = null;
        $applied = [];
        try {
            $this->assertIntegrity('before');
            $fkBefore = $this->foreignKeyProblems();
            $this->assertDiskSpace();
            $backup = $this->backup($first->version);
            $this->pruneBackups();
            foreach ($pending as $file) {
                $this->apply($file);
                $applied[] = $file->version;
            }
            $this->assertIntegrity('after');
            $fkAfter = $this->foreignKeyProblems();
            if ($fkAfter > $fkBefore) {
                throw new RuntimeException("foreign_key_check found $fkAfter problems after migrating (was $fkBefore)");
            }
            return new MigrationResult(true, $applied, $backup, '');
        } catch (Throwable $e) {
            $failed = $applied === [] ? $first : ($this->nextAfter($pending, $applied) ?? $first);
            $this->writeFailure($failed, $e->getMessage(), $backup, $applied);
            return new MigrationResult(false, $applied, $backup, $e->getMessage());
        }
    }

    private function apply(MigrationFile $file): void
    {
        $sql = (string) file_get_contents($file->path);
        $started = hrtime(true);
        $this->db->txImmediate(function (Db $db) use ($file, $sql, $started): void {
            $db->execScript(
                'CREATE TABLE IF NOT EXISTS schema_migrations ('
                . 'version INTEGER PRIMARY KEY, name TEXT NOT NULL, sha256 TEXT NOT NULL, '
                . 'applied_at TEXT NOT NULL, ms INTEGER NOT NULL)'
            );
            $db->execScript($sql);
            $ms = intdiv(hrtime(true) - $started, 1000000);
            $db->exec(
                'INSERT INTO schema_migrations (version, name, sha256, applied_at, ms) VALUES (:v, :n, :s, :a, :ms)',
                ['v' => $file->version, 'n' => $file->name, 's' => $file->sha256, 'a' => $this->clock->now()->format('Y-m-d H:i:s'), 'ms' => $ms],
            );
            // user_version cannot be bound; the value is an int from the file name.
            $db->execScript('PRAGMA user_version = ' . $file->version);
        });
    }

    private function backup(int $version): string
    {
        $stamp = $this->clock->now()->format('Ymd-His');
        $path = sprintf('%s/pre-%04d-%s.db', $this->backupsDir, $version, $stamp);
        $n = 2;
        while (file_exists($path)) {
            $path = sprintf('%s/pre-%04d-%s-%d.db', $this->backupsDir, $version, $stamp, $n);
            $n++;
        }
        $this->db->exec('VACUUM INTO :p', ['p' => $path]);
        if (!is_file($path)) {
            throw new RuntimeException('Backup was not written: ' . $path);
        }
        chmod($path, 0600);
        return $path;
    }

    private function pruneBackups(): void
    {
        $backups = $this->backups();
        for ($i = self::KEEP_BACKUPS; $i < count($backups); $i++) {
            unlink($this->backupsDir . '/' . $backups[$i]->name);
        }
    }

    /** @return list<BackupFile> newest first */
    public function backups(): array
    {
        $paths = glob($this->backupsDir . '/pre-*.db');
        $out = [];
        foreach ($paths === false ? [] : $paths as $p) {
            $out[] = new BackupFile(basename($p), (int) filesize($p), (int) filemtime($p));
        }
        usort($out, static function (BackupFile $a, BackupFile $b): int {
            $byTime = $b->modifiedAt <=> $a->modifiedAt;
            return $byTime !== 0 ? $byTime : strcmp($b->name, $a->name);
        });
        return $out;
    }

    public function status(): MigrationStatus
    {
        $applied = [];
        $hasTable = $this->db->scalar("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'") !== null;
        if ($hasTable) {
            foreach ($this->db->query('SELECT version, name, sha256, applied_at, ms FROM schema_migrations ORDER BY version') as $row) {
                $applied[] = AppliedMigration::fromRow($row);
            }
        }
        $byVersion = [];
        foreach ($this->files() as $f) {
            $byVersion[$f->version] = $f->sha256;
        }
        $modified = [];
        foreach ($applied as $a) {
            if (isset($byVersion[$a->version]) && $byVersion[$a->version] !== $a->sha256) {
                $modified[] = $a->version;
            }
        }
        return new MigrationStatus(
            $this->currentVersion(),
            $this->latestVersion(),
            $this->pending(),
            $applied,
            is_dir($this->backupsDir) ? $this->backups() : [],
            $this->failure(),
            $modified,
        );
    }

    private function assertIntegrity(string $when): void
    {
        $rows = $this->db->query('PRAGMA integrity_check');
        $first = $rows === [] ? '' : (string) array_values($rows[0])[0];
        if ($first !== 'ok') {
            throw new RuntimeException("integrity_check $when migrating: $first");
        }
    }

    /** Count, not a hard stop: live data may already hold orphans; a migration must not add any. */
    private function foreignKeyProblems(): int
    {
        return count($this->db->query('PRAGMA foreign_key_check'));
    }

    private function assertDiskSpace(): void
    {
        if (!function_exists('disk_free_space') || !is_file($this->db->path)) {
            return;
        }
        $free = @disk_free_space($this->backupsDir);
        $size = (int) filesize($this->db->path);
        if ($free !== false && $free < 2 * $size) {
            throw new RuntimeException(sprintf('Not enough disk space for a backup: %d bytes free, %d needed', (int) $free, 2 * $size));
        }
    }

    /**
     * @param list<MigrationFile> $pending
     * @param list<int> $applied
     */
    private function nextAfter(array $pending, array $applied): ?MigrationFile
    {
        foreach ($pending as $f) {
            if (!in_array($f->version, $applied, true)) {
                return $f;
            }
        }
        return null;
    }

    /** @param list<int> $applied */
    private function writeFailure(MigrationFile $file, string $error, ?string $backup, array $applied): void
    {
        $payload = [
            'version' => $file->version,
            'name' => $file->name,
            'error' => $error,
            'at' => $this->clock->now()->format('c'),
            'backup' => $backup !== null ? basename($backup) : null,
            'applied_before_failure' => $applied,
            'rollback' => 'The failed migration was rolled back. To undo earlier ones, upload the backup over data/slash301pm.db by SFTP.',
        ];
        file_put_contents($this->failurePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }
}

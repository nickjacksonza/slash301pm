<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\SeedPasswordReport;

/**
 * Beta gate check (audit C3): which active users can still sign in with the
 * shared seed password. password_verify on a cost-12 hash takes about a quarter
 * of a second, so results are cached in data/seed-password-check.json keyed by
 * a fingerprint of each user's hash: a user is checked again only after their
 * hash changes, and each call checks at most $maxChecks users and stops after
 * about BUDGET_MS (the rest are reported as not checked yet, so /admin/system
 * never hangs).
 * The cache holds user ids, fingerprints and booleans only, never a hash.
 */
final class SeedPasswordAudit
{
    /** The password api/seed.php gives every seeded user (public in the repo history). Used only to check, never to sign in. */
    public const SEED_PASSWORD = 'Password123!';
    public const MAX_CHECKS_PER_CALL = 6;
    /** Stop checking once this much time went into password_verify (shared hosting is slower than a laptop). */
    public const BUDGET_MS = 1500;

    public function __construct(private readonly Db $db, private readonly string $cachePath) {}

    public function check(int $maxChecks = self::MAX_CHECKS_PER_CALL): SeedPasswordReport
    {
        $cache = $this->readCache();
        $next = [];
        $seed = [];
        $unchecked = 0;
        $checks = 0;
        $started = hrtime(true);
        foreach ($this->db->query('SELECT id, username, password_hash FROM users WHERE is_active = 1 ORDER BY username') as $r) {
            $id = (string) $r['id'];
            $hash = (string) ($r['password_hash'] ?? '');
            $fp = hash('sha256', $hash);
            $known = $cache[$id] ?? null;
            if (is_array($known) && ($known['fp'] ?? '') === $fp && is_bool($known['seed'] ?? null)) {
                $isSeed = $known['seed'];
            } elseif ($checks < $maxChecks && ($checks === 0 || intdiv(hrtime(true) - $started, 1_000_000) < self::BUDGET_MS)) {
                $checks++;
                $isSeed = $hash !== '' && password_verify(self::SEED_PASSWORD, $hash);
            } else {
                $unchecked++;
                continue;
            }
            $next[$id] = ['fp' => $fp, 'seed' => $isSeed];
            if ($isSeed) {
                $seed[] = (string) $r['username'];
            }
        }
        if ($checks > 0 || count($next) !== count($cache)) {
            $this->writeCache($next);
        }
        return new SeedPasswordReport($seed, $unchecked);
    }

    /** @return array<string,mixed> */
    private function readCache(): array
    {
        if (!is_file($this->cachePath)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->cachePath), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string,array{fp:string,seed:bool}> $data */
    private function writeCache(array $data): void
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $tmp = $this->cachePath . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
        chmod($tmp, 0600);
        rename($tmp, $this->cachePath);
    }
}

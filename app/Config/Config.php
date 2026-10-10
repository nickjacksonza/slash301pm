<?php
declare(strict_types=1);

namespace App\Config;

use App\Domain\Role;

/**
 * Everything environment-specific, decided once per request by app/config.php.
 * Go: type Config struct, filled in main.go.
 */
final class Config
{
    /**
     * @param list<Role> $newUiRoles roles BetaGate lets into the new UI
     */
    public function __construct(
        public readonly Env $env,
        public readonly string $rootDir,
        public readonly string $dataDir,
        public readonly string $dbPath,
        public readonly string $basePath,
        public readonly string $liveHost,
        public readonly string $cookieDomain,
        public readonly bool $cookieSecure,
        public readonly Transport $transport,
        public readonly array $newUiRoles,
        /** Reviews: the AM is warned when a post's review round passes this (owner default 3; 0 turns it off). */
        public readonly int $reviewRoundLimit = 3,
    ) {}

    /**
     * @param array<string,mixed> $server $_SERVER
     * @param array<string,string> $env   getenv()
     */
    public static function fromEnvironment(array $server, array $env, string $sapi, string $rootDir, Transport $defaultTransport, int $reviewRoundLimit = 3): self
    {
        $local = self::detectLocal($server, $sapi);
        $dataDir = isset($env['S301_DATA_DIR']) && $env['S301_DATA_DIR'] !== '' ? rtrim($env['S301_DATA_DIR'], '/') : $rootDir . '/data';
        $dbPath = isset($env['S301_DB']) && $env['S301_DB'] !== '' ? $env['S301_DB'] : $dataDir . '/slash301pm.db';
        $transport = $defaultTransport;
        if (isset($env['S301_TRANSPORT']) && Transport::tryFrom($env['S301_TRANSPORT']) !== null) {
            $transport = Transport::from($env['S301_TRANSPORT']);
        }
        $liveHost = 'projects.slash301.com';
        return new self(
            $local ? Env::Local : Env::Live,
            $rootDir,
            $dataDir,
            $dbPath,
            '/slash301pm',
            $liveHost,
            $local ? '' : $liveHost,
            !$local,
            $transport,
            self::defaultNewUiRoles(),
            max(0, min(99, $reviewRoundLimit)),
        );
    }

    /**
     * Every agency role uses the new UI (docs/roles.md waves 1 to 6). Clients stay
     * on /legacy/ until the client portal exists. @return list<Role>
     */
    public static function defaultNewUiRoles(): array
    {
        return [
            Role::COO, Role::ECD, Role::AM, Role::PM, Role::Producer, Role::Traffic, Role::CD,
            Role::Copywriter, Role::Designer, Role::QA, Role::Developer, Role::SEO, Role::Social,
        ];
    }

    /**
     * Local when run by the CLI or php -S, or when both the Host header and the
     * peer address are loopback (same rule as api/auth.php, so a forged Host
     * header on live changes nothing).
     */
    public static function detectLocal(array $server, string $sapi): bool
    {
        if ($sapi === 'cli' || $sapi === 'cli-server') {
            return true;
        }
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($server['HTTP_HOST'] ?? '')));
        $remote = (string) ($server['REMOTE_ADDR'] ?? '');
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) && in_array($remote, ['127.0.0.1', '::1'], true);
    }

    public function isLive(): bool
    {
        return $this->env === Env::Live;
    }

    public function sessionsDir(): string
    {
        return $this->dataDir . '/sessions';
    }

    /** app-YYYY-MM-DD.log and php-YYYY-MM-DD.log (App\Logs\AppLog), never served. */
    public function logsDir(): string
    {
        return $this->dataDir . '/logs';
    }

    /**
     * PHP settings made at bootstrap. Errors are never displayed (live or local:
     * no stack trace or path can reach a response) and always logged to
     * data/logs/php-<date>.log, not to the host's default error_log, which on
     * shared hosting can be a file inside the web root.
     * @return array<string,string>
     */
    public function iniSettings(string $todaySast): array
    {
        return [
            'display_errors' => '0',
            'display_startup_errors' => '0',
            'html_errors' => '0',
            'log_errors' => '1',
            'error_log' => $this->logsDir() . '/php-' . $todaySast . '.log',
        ];
    }

    public function backupsDir(): string
    {
        return $this->dataDir . '/backups';
    }

    public function migrationsDir(): string
    {
        return $this->rootDir . '/migrations';
    }

    public function migrateLockPath(): string
    {
        return $this->dataDir . '/migrate.lock';
    }

    public function migrateFailurePath(): string
    {
        return $this->dataDir . '/migrate-failed.json';
    }

    /** The legacy switch file. Read only; never written by the new app. */
    public function demoFlagPath(): string
    {
        return $this->dataDir . '/.demo_mode';
    }

    /** Read on every call: the owner flips demo mode by hand on the server. */
    public function demoMode(): bool
    {
        return is_file($this->demoFlagPath());
    }

    /** Cookie path shared with the legacy app. */
    public function cookiePath(): string
    {
        return $this->basePath . '/';
    }

    /** Expected Origin of same-site requests; local uses the request host. */
    public function expectedOrigin(string $requestScheme, string $requestHost): string
    {
        if ($this->isLive()) {
            return 'https://' . $this->liveHost;
        }
        return $requestScheme . '://' . $requestHost;
    }
}

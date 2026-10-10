<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\Config;
use RuntimeException;

/**
 * PHP file sessions with exactly the legacy configuration from api/auth.php
 * (name, save path data/sessions, cookie params, 1 hour gc), so one login works
 * for both apps. Only the cookie domain and secure flag differ locally, where
 * the cookie is host-only and not secure (Config decides). The cookie path is
 * /slash301pm/ in both environments.
 */
final class PhpSession implements Session
{
    public const NAME = 'SLASH301PM_SID';
    public const IDLE_SECONDS = 3600;

    private bool $started = false;

    public function __construct(private readonly Config $config) {}

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $dir = $this->config->sessionsDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        ini_set('session.save_path', $dir);
        $ok = session_start([
            'name' => self::NAME,
            'cookie_lifetime' => 0,
            'cookie_path' => $this->config->cookiePath(),
            'cookie_domain' => $this->config->cookieDomain,
            'cookie_secure' => $this->config->cookieSecure,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'sid_length' => 48,
            'gc_maxlifetime' => self::IDLE_SECONDS,
        ]);
        if (!$ok) {
            throw new RuntimeException('session_start failed');
        }
        $this->started = true;
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Same steps as legacy destroySession(). */
    public function destroy(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
        session_destroy();
        $this->started = false;
    }

    public function close(): void
    {
        if ($this->started && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $this->started = false;
    }
}

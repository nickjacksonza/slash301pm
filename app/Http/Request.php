<?php
declare(strict_types=1);

namespace App\Http;

use App\Domain\Types\User;

/**
 * An immutable HTTP request. path() is relative to the base path
 * (/slash301pm/jobs/12 -> /jobs/12). Middleware adds the session and user with
 * the with*() methods. Go: *http.Request plus context values.
 */
final class Request
{
    /**
     * @param array<string,mixed>  $query   decoded query string ($_GET)
     * @param array<string,string> $headers lower-case names
     * @param array<string,mixed>  $form    urlencoded or multipart fields ($_POST)
     * @param array<string,string> $pathValues set by the Router
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $form,
        public readonly string $remoteAddr,
        public readonly string $scheme,
        public readonly array $pathValues = [],
        public readonly ?Session $session = null,
        public readonly ?User $user = null,
    ) {}

    public static function fromGlobals(string $basePath): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }
        $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
            || strtolower($headers['x-forwarded-proto'] ?? '') === 'https';
        $body = in_array($method, ['GET', 'HEAD', 'DELETE'], true) ? '' : (string) file_get_contents('php://input');
        return new self(
            $method,
            self::relativePath($uriPath, $basePath),
            $_GET,
            $headers,
            $body,
            $_POST,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            $https ? 'https' : 'http',
        );
    }

    /** '/slash301pm/jobs' -> '/jobs'; '/slash301pm' -> '/'. A path outside the base stays as is. */
    public static function relativePath(string $uriPath, string $basePath): string
    {
        $base = rtrim($basePath, '/');
        $p = $uriPath === '' ? '/' : $uriPath;
        if ($base !== '' && ($p === $base || str_starts_with($p, $base . '/'))) {
            $p = substr($p, strlen($base));
        }
        return $p === '' || $p === false ? '/' : $p;
    }

    public function withPathValues(array $values): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->body, $this->form, $this->remoteAddr, $this->scheme, $values, $this->session, $this->user);
    }

    public function withSession(Session $session): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->body, $this->form, $this->remoteAddr, $this->scheme, $this->pathValues, $session, $this->user);
    }

    public function withUser(?User $user): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->body, $this->form, $this->remoteAddr, $this->scheme, $this->pathValues, $this->session, $user);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $name): string
    {
        $v = $this->query[$name] ?? '';
        return is_string($v) ? $v : '';
    }

    public function pathValue(string $name): string
    {
        return $this->pathValues[$name] ?? '';
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function form(string $name): string
    {
        $v = $this->form[$name] ?? '';
        return is_string($v) ? $v : '';
    }

    public function host(): string
    {
        return $this->header('host');
    }

    public function isDatastar(): bool
    {
        return $this->header('datastar-request') === 'true';
    }

    public function isFormPost(): bool
    {
        $ct = strtolower($this->header('content-type'));
        return str_starts_with($ct, 'application/x-www-form-urlencoded') || str_starts_with($ct, 'multipart/form-data');
    }

    /**
     * Datastar signals: GET and DELETE carry ?datastar=<json>, other methods a
     * JSON body. Untrusted input; anything that is not a JSON object gives [].
     * @return array<string,mixed>
     */
    public function signals(): array
    {
        if (in_array($this->method, ['GET', 'HEAD', 'DELETE'], true)) {
            $raw = $this->query('datastar');
        } else {
            $raw = $this->isFormPost() ? '' : $this->body;
        }
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true, 64);
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /** Only valid after the Session middleware ran. */
    public function session(): Session
    {
        return $this->session ?? new MemorySession();
    }

    public function clientIp(): string
    {
        return ClientIp::resolve($this->remoteAddr, $this->header('cf-connecting-ip'));
    }

    /** The session CSRF token ('' before the Session middleware). */
    public function csrfToken(): string
    {
        $t = $this->session === null ? null : $this->session->get('csrf_token');
        return is_string($t) ? $t : '';
    }
}

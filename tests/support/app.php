<?php
declare(strict_types=1);

// Shared setup for tests that touch Http, Store or View code.
// Every database and data dir is a fresh temp copy; the real data/ is never written.

use App\Clock\FixedClock;
use App\Config\Config;
use App\Config\Env;
use App\Config\Transport;
use App\Domain\Role;
use App\Http\Deps;
use App\Http\Kernel;
use App\Http\MemorySession;
use App\Http\Request;
use App\Http\Response;

require_once dirname(__DIR__, 2) . '/app/autoload.php';
require_once dirname(__DIR__, 2) . '/app/View/load.php';
date_default_timezone_set('Africa/Johannesburg');

/** A fresh temp directory, removed at exit. */
function ts_temp_dir(): string
{
    $dir = sys_get_temp_dir() . '/s301_' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    register_shutdown_function(static function () use ($dir): void {
        ts_rm_rf($dir);
    });
    return $dir;
}

function ts_rm_rf(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                ts_rm_rf($path . '/' . $f);
            }
        }
        @rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        @unlink($path);
    }
}

function ts_config(string $dataDir, ?string $dbPath = null, Transport $t = Transport::Sse): Config
{
    return new Config(
        Env::Local, dirname(__DIR__, 2), $dataDir, $dbPath ?? $dataDir . '/test.db', '/slash301pm',
        'projects.slash301.com', '', false, $t, [Role::AM, Role::COO, Role::ECD],
    );
}

/** Deps on a migrated temp database. $demo creates the demo flag in the TEMP data dir only. */
function ts_deps(bool $demo = false, string $driver = 'auto'): Deps
{
    app_base_path('/slash301pm');
    $dir = ts_temp_dir();
    if ($demo) {
        file_put_contents($dir . '/.demo_mode', '1');
    }
    $deps = Deps::build(ts_config($dir), new FixedClock(new DateTimeImmutable('2026-10-09 09:00:00')), $driver);
    $result = $deps->migrator->run();
    if (!$result->ok) {
        throw new RuntimeException('test migration failed: ' . $result->error);
    }
    return $deps;
}

/** Insert a user straight into the temp DB; returns the id. */
function ts_user(Deps $d, string $username, Role $role, string $password = 'correct horse battery', bool $active = true): string
{
    $id = $d->users->create($username, password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), ucfirst($username), null, $role, '#3b82f6', null, null, $d->clock->now());
    if (!$active) {
        $d->users->setActive($id, false, null, $d->clock->now());
    }
    return $id;
}

/** @param array<string,string> $headers lower-case names */
function ts_request(string $method, string $path, array $headers = [], string $body = '', array $form = [], array $query = []): Request
{
    $headers = $headers + ['host' => '127.0.0.1:8301'];
    if ($form !== [] && !isset($headers['content-type'])) {
        $headers['content-type'] = 'application/x-www-form-urlencoded';
    }
    return new Request(strtoupper($method), $path, $query, $headers, $body, $form, '127.0.0.1', 'http');
}

/** Datastar action request with the session's CSRF token. */
function ts_ds_request(string $method, string $path, MemorySession $s, array $signals = [], bool $withToken = true): Request
{
    $headers = ['datastar-request' => 'true', 'content-type' => 'application/json'];
    if ($withToken) {
        $headers['x-csrf-token'] = (string) $s->get('csrf_token');
    }
    $json = json_encode($signals === [] ? new stdClass() : $signals, JSON_THROW_ON_ERROR);
    if (in_array(strtoupper($method), ['GET', 'DELETE'], true)) {
        return ts_request($method, $path, $headers, '', [], ['datastar' => $json]);
    }
    return ts_request($method, $path, $headers, $json);
}

function ts_app(MemorySession $s): Closure
{
    return Kernel::build(require dirname(__DIR__, 2) . '/app/routes.php', $s);
}

function ts_body(Response $r, Transport $t = Transport::Sse): string
{
    return $r->render($t)->body();
}

<?php
declare(strict_types=1);

/**
 * Local dev server that behaves like the live .htaccess, under the same base
 * path, so cookies (path /slash301pm/) and URLs match live:
 *
 *   php -S 127.0.0.1:8301 tools/dev-router.php
 *   open http://127.0.0.1:8301/slash301pm/
 *
 * Env: S301_DB=/tmp/copy.db (use a copy, never the live download itself),
 *      S301_DATA_DIR=/tmp/s301data (sessions, backups, demo flag), S301_TRANSPORT=html.
 *
 * Serves real files under /slash301pm/{public,legacy,api}/, denies the same
 * internal folders and file types as .htaccess, and routes the rest to index.php.
 */

$root = dirname(__DIR__);
$base = '/slash301pm';
$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$path = rawurldecode($path);

if ($path === '/' || $path === '') {
    header('Location: ' . $base . '/', true, 302);
    return true;
}
if ($path !== $base && !str_starts_with($path, $base . '/')) {
    http_response_code(404);
    echo "Not found (the app lives under $base/)\n";
    return true;
}
$rel = substr($path, strlen($base));   // '' or '/x/y'

if (str_contains($rel, '/.') || str_contains($rel, '..')
    || preg_match('#^/(app|migrations|tests|tools|vendor|styles|docs|data|backup)(/|$)#', $rel) === 1
    || preg_match('#\.(md|sql|sh|ya?ml|lock|db|db-wal|db-shm|sqlite|sqlite3)$#i', $rel) === 1) {
    http_response_code(403);
    echo "Forbidden\n";
    return true;
}

if (preg_match('#^/(legacy|api|public)(/|$)#', $rel) === 1) {
    $file = $root . $rel;
    if (is_dir($file)) {
        if (!str_ends_with($rel, '/')) {
            header('Location: ' . $base . $rel . '/' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
            return true;
        }
        $file = is_file($file . 'index.php') ? $file . 'index.php' : $file . 'index.html';
    }
    if (!is_file($file)) {
        http_response_code(404);
        echo "Not found\n";
        return true;
    }
    if (str_ends_with($file, '.php')) {
        // Run legacy PHP (api/api.php) as Apache would.
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME'] = $base . substr($file, strlen($root));
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        chdir(dirname($file));
        require $file;
        return true;
    }
    $types = [
        'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff2' => 'font/woff2', 'woff' => 'font/woff', 'map' => 'application/json', 'txt' => 'text/plain; charset=utf-8',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);
    return true;
}

// Everything else: the front controller.
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['SCRIPT_NAME'] = $base . '/index.php';
chdir($root);
require $root . '/index.php';
return true;

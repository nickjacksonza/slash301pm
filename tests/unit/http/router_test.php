<?php
declare(strict_types=1);

use App\Http\Router;

require_once dirname(__DIR__, 2) . '/support/app.php';

function rt_router(): Router
{
    $r = new Router();
    foreach ([
        'GET /{$}', 'GET /today', 'GET /jobs/{id}', 'PATCH /jobs/{id}/fields/{field}', 'GET /jobs/board',
        'GET /files/{path...}', 'GET /static/', 'POST /login', 'GET /login', '/any', 'DELETE /jobs/{id}',
    ] as $p) {
        $r->add($p, static fn (): string => $p);
    }
    return $r;
}

return [
    'router matches the table' => function (): void {
        $cases = [
            // method, path, status, pattern, values
            ['GET', '/', 200, 'GET /{$}', []],
            ['GET', '/today', 200, 'GET /today', []],
            ['GET', '/today/', 404, null, []],
            ['GET', '/jobs/12', 200, 'GET /jobs/{id}', ['id' => '12']],
            ['GET', '/jobs/board', 200, 'GET /jobs/board', []],             // literal beats {id}
            ['PATCH', '/jobs/12/fields/title', 200, 'PATCH /jobs/{id}/fields/{field}', ['id' => '12', 'field' => 'title']],
            ['GET', '/jobs/a%20b', 200, 'GET /jobs/{id}', ['id' => 'a b']],
            ['GET', '/files/a/b/c.txt', 200, 'GET /files/{path...}', ['path' => 'a/b/c.txt']],
            ['GET', '/files/', 200, 'GET /files/{path...}', ['path' => '']],
            ['GET', '/files', 404, null, []],
            ['GET', '/static/x/y', 200, 'GET /static/', []],
            ['HEAD', '/today', 200, 'GET /today', []],
            ['PUT', '/any', 200, '/any', []],
            ['GET', '/nope', 404, null, []],
            ['POST', '/today', 405, null, []],
            ['PUT', '/jobs/12', 405, null, []],
        ];
        $r = rt_router();
        foreach ($cases as [$method, $path, $status, $pattern, $values]) {
            $m = $r->match($method, $path);
            t_eq($status, $m->status, "$method $path status");
            if ($pattern !== null) {
                $h = $m->handler;
                t_eq($pattern, $h(), "$method $path pattern");
                t_eq($values, $m->values, "$method $path values");
            }
        }
    },
    'router 405 lists allowed methods' => function (): void {
        $m = rt_router()->match('PUT', '/jobs/12');
        t_eq(['DELETE', 'GET', 'HEAD'], $m->allowed);
    },
    'router rejects duplicates and bad patterns' => function (): void {
        $r = new Router();
        $r->add('GET /jobs/{id}', static fn (): int => 1);
        t_throws(static fn () => $r->add('GET /jobs/{other}', static fn (): int => 2), InvalidArgumentException::class);
        t_throws(static fn () => $r->add('GET /a/{rest...}/b', static fn (): int => 3), InvalidArgumentException::class);
        t_throws(static fn () => $r->add('GET jobs', static fn (): int => 4), InvalidArgumentException::class);
        t_throws(static fn () => $r->add('GET /x{y}', static fn (): int => 5), InvalidArgumentException::class);
    },
    'the app route table parses' => function (): void {
        $table = require dirname(__DIR__, 3) . '/app/routes.php';
        $r = Router::fromTable($table);
        t_eq(200, $r->match('POST', '/admin/users/abc/reset')->status);
        t_eq('abc', $r->match('POST', '/admin/users/abc/reset')->values['id']);
        t_eq(200, $r->match('GET', '/')->status);
    },
];

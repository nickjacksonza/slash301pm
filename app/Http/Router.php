<?php
declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

/**
 * Go 1.22 ServeMux patterns: "[METHOD ]/path/{name}/{rest...}", "/{$}" for an
 * exact match, a trailing "/" for a prefix match. The most specific pattern
 * wins (literal over {name} over {rest...}); GET also serves HEAD. No match is
 * 404; a path match with the wrong method is 405 with an Allow header.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @param list<array{0:string,1:callable}> $table from app/routes.php */
    public static function fromTable(array $table): self
    {
        $r = new self();
        foreach ($table as $entry) {
            $r->add($entry[0], $entry[1]);
        }
        return $r;
    }

    public function add(string $pattern, callable $handler): void
    {
        $route = Route::parse($pattern, $handler);
        foreach ($this->routes as $existing) {
            if ($existing->method === $route->method && $existing->key() === $route->key()) {
                throw new InvalidArgumentException("Duplicate route pattern: $pattern");
            }
        }
        $this->routes[] = $route;
    }

    public function match(string $method, string $path): RouteMatch
    {
        $segments = self::split($path);
        $trailingSlash = str_ends_with($path, '/');
        $best = null;
        $bestValues = [];
        $allowed = [];
        foreach ($this->routes as $route) {
            $values = $route->matchPath($segments, $trailingSlash);
            if ($values === null) {
                continue;
            }
            $methodOk = $route->method === '' || $route->method === $method || ($method === 'HEAD' && $route->method === 'GET');
            if (!$methodOk) {
                $allowed[] = $route->method;
                if ($route->method === 'GET') {
                    $allowed[] = 'HEAD';
                }
                continue;
            }
            if ($best === null || $route->moreSpecificThan($best)) {
                $best = $route;
                $bestValues = $values;
            }
        }
        if ($best !== null) {
            return new RouteMatch(200, $best->handler, $bestValues, []);
        }
        if ($allowed !== []) {
            $allowed = array_values(array_unique($allowed));
            sort($allowed);
            return new RouteMatch(405, null, [], $allowed);
        }
        return new RouteMatch(404, null, [], []);
    }

    /** The innermost handler: match, then call with path values set. */
    public function handler(): \Closure
    {
        return function (Request $r, Deps $d): Response {
            $m = $this->match($r->method(), $r->path());
            if ($m->status === 404 || $m->handler === null) {
                return $m->status === 405
                    ? Response::page(page_error(405, 'Method not allowed', 'This address does not accept that request.'), 405)->withHeader('Allow', implode(', ', $m->allowed))
                    : Response::notFound();
            }
            $handler = $m->handler;
            return $handler($r->withPathValues($m->values), $d);
        };
    }

    /** @return list<string> raw (still percent-encoded) segments */
    public static function split(string $path): array
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }
}

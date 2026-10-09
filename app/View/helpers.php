<?php
declare(strict_types=1);

// Escaping and URL helpers shared by every template. Plain functions, so the Go
// port maps them to templ's built-in escaping plus a few helpers.
//
//   e($v)    text content between tags
//   attr($v) a value inside a double-quoted HTML attribute (not a data-* expression)
//   js($v)   a value used inside a Datastar data-* expression or action argument;
//            produces a JS literal (string, number, bool, null, array, object),
//            already HTML-attribute-escaped; in a template write
//            data-on:click="@get(" then echo js($url) then ")"
//   url($p)  an app path with the configured base path, e.g. url('/jobs') -> /slash301pm/jobs
//   asset($p) a public/ file URL with a content-hash version for cache busting

function e(string|int|float|null $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function attr(string|int|float|null $v): string
{
    return e($v);
}

function js(mixed $v): string
{
    $json = json_encode(
        $v,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
    );
    return htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Base path for URLs, e.g. '/slash301pm' on live, '' or '/slash301pm' locally. Set once by bootstrap. */
function app_base_path(?string $set = null): string
{
    static $base = '';
    if ($set !== null) {
        $base = rtrim($set, '/');
    }
    return $base;
}

function url(string $path, array $query = []): string
{
    $u = app_base_path() . '/' . ltrim($path, '/');
    if ($query !== []) {
        $u .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    return $u;
}

function asset(string $path): string
{
    $rel = 'public/' . ltrim($path, '/');
    $file = dirname(__DIR__, 2) . '/' . $rel;
    $v = is_file($file) ? substr(hash_file('sha256', $file) ?: '', 0, 10) : '0';
    return app_base_path() . '/' . $rel . '?v=' . $v;
}

/** Join literal Tailwind class strings, skipping empty ones. Never build class names from parts. */
function cx(string ...$classes): string
{
    return implode(' ', array_filter($classes, static fn (string $c): bool => $c !== ''));
}

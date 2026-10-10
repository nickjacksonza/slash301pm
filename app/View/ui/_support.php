<?php
declare(strict_types=1);

/**
 * Shared helpers for the ui_* partials. Plain global functions, loaded by _all.php.
 * Go port: these map to templ.Attributes handling and utils.Signals in datastarui.
 *
 * Escaping contract
 *   text nodes          e()          (callers pass already escaped children as string $children)
 *   attribute values    attr()       every value in ui_attrs() and every prop that lands in an attribute
 *   Datastar expression the expression text is built here from code plus ui_js_raw() / ui_js_sq() literals,
 *                       then the WHOLE expression goes through attr(). Never concatenate data into an expression.
 *   ids and tokens      ui_id() / ui_tok() throw InvalidArgumentException for anything outside a safe charset,
 *                       so they can sit inside JS strings and signal paths without escaping.
 */

/** Attribute names allowed in an attrs array. Inline event handlers (on*) are refused. */
const UI_ATTR_KEY_RE = '/^[a-z0-9:_.\-]+$/';

/**
 * Render extra attributes as ' key="value" key2'. Returns '' for an empty array, otherwise starts with a space.
 * true prints a bare attribute, false and null are skipped, anything else is escaped with attr().
 *
 * @param array<string,string|int|float|bool|null> $attrs
 */
function ui_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $key => $value) {
        $key = (string) $key;
        if (preg_match(UI_ATTR_KEY_RE, $key) !== 1 || str_starts_with($key, 'on')) {
            throw new InvalidArgumentException('ui: invalid attribute name ' . json_encode($key));
        }
        if ($value === false || $value === null) {
            continue;
        }
        $out .= $value === true ? ' ' . $key : ' ' . $key . '="' . attr($value) . '"';
    }
    return $out;
}

/** One attribute, skipped when the value is empty: ' name="value"'. */
function ui_attr_if(string $name, string $value): string
{
    return $value === '' ? '' : ' ' . $name . '="' . attr($value) . '"';
}

/** A boolean attribute: ' name' or ''. */
function ui_bool(string $name, bool $on): string
{
    return $on ? ' ' . $name : '';
}

/** An element id usable inside JS strings and signal paths. */
function ui_id(string $id): string
{
    if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id) !== 1) {
        throw new InvalidArgumentException('ui: invalid id ' . json_encode($id));
    }
    return $id;
}

/** A developer constant such as a tab value. Wider than an id but still safe inside a single quoted JS string. */
function ui_tok(string $v): string
{
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/', $v) !== 1) {
        throw new InvalidArgumentException('ui: invalid token ' . json_encode($v));
    }
    return $v;
}

/** Signal root for an id: hyphens become underscores ('confirm-delete' gives 'confirm_delete'). */
function ui_sig(string $id): string
{
    return str_replace('-', '_', ui_id($id));
}

/** JS literal for data, NOT HTML-escaped. Put the whole expression through attr() afterwards. */
function ui_js_raw(mixed $v): string
{
    return json_encode(
        $v,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
    );
}

/**
 * Single quoted JS string literal, NOT HTML-escaped (mirrors the upstream '...' strings in data-text).
 * Backslash, quote and control characters are escaped; angle brackets and ampersands are left for attr().
 */
function ui_js_sq(string $s): string
{
    $s = str_replace(['\\', "'", "\r", "\n", "\u{2028}", "\u{2029}"], ['\\\\', "\\'", '\\r', '\\n', '\\u2028', '\\u2029'], $s);
    return "'" . $s . "'";
}

/** data-signals value (unescaped JSON, pass through attr()): {"<id as signal>":{...fields}}. */
function ui_signals_json(string $id, array $fields): string
{
    return json_encode(
        [ui_sig($id) => $fields],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

/** ' data-signals="..."' for a namespaced signal object. */
function ui_signals_attr(string $id, array $fields): string
{
    return ' data-signals="' . attr(ui_signals_json($id, $fields)) . '"';
}

/** Always prints class (upstream templ prints class even when empty). */
function ui_class_attr(string $class): string
{
    return ' class="' . attr($class) . '"';
}

/** Join literal class strings, skipping empty ones, with the caller's extra class last. */
function ui_cx(string ...$classes): string
{
    return cx(...$classes);
}

/** Props type guard used by variant maps: unknown variants fall back to the default entry. */
function ui_pick(array $map, string $key, string $default): string
{
    return $map[$key] ?? $map[$default];
}

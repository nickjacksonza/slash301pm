<?php
declare(strict_types=1);

/**
 * Canonical HTML for comparing a PHP partial with the Go (templ) reference output.
 * Twin of render/normalize.go. Rules, keep both in sync:
 *  - one node per line, 2 space indent per depth, comments dropped
 *  - attribute names lowercased and sorted, first duplicate wins, empty value prints as bare name
 *  - class tokens sorted, de-duplicated, joined by one space
 *  - data-class "{'a': x, 'b': y}" top-level entries sorted (Go builds them from a map, so order is random)
 *  - attribute values and text decoded, then escaped with & < > (and " in attributes)
 *  - text whitespace collapsed and trimmed, empty text dropped
 *  - void tags print as <tag ... />, self-closing non-void tags become open + close
 *
 * Copy this file to tests/unit/ui/_normalize.php (or require it from there) when the first
 * component test is created. Do not edit the copy independently of render/normalize.go.
 */
function ui_normalize_html(string $html): string
{
    static $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
    $esc = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    $dec = static fn (string $s): string => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Tokens: comment | end tag | start tag (attributes may contain > inside quotes) | text
    $re = '~<!--.*?-->|</([A-Za-z][^\s/>]*)\s*>|<([A-Za-z][^\s/>]*)((?:"[^"]*"|\'[^\']*\'|[^\'">])*?)(/?)>|[^<]+|<~s';
    preg_match_all($re, $html, $m, PREG_SET_ORDER);

    $out = [];
    $depth = 0;
    $n = count($m);
    for ($i = 0; $i < $n; $i++) {
        $t = $m[$i];
        $tok = $t[0];
        if (str_starts_with($tok, '<!--')) {
            continue;
        }
        if (!empty($t[1])) { // end tag
            $tag = strtolower($t[1]);
            if (in_array($tag, $void, true)) {
                continue;
            }
            $depth = max(0, $depth - 1);
            $out[] = str_repeat('  ', $depth) . '</' . $tag . '>';
            continue;
        }
        if (!empty($t[2])) { // start tag
            $tag = strtolower($t[2]);
            $selfClosing = ($t[4] ?? '') === '/';
            $attrs = [];
            preg_match_all('~([^\s"\'<>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~', $t[3], $am, PREG_SET_ORDER);
            foreach ($am as $a) {
                $k = strtolower($a[1]);
                if (isset($attrs[$k])) {
                    continue;
                }
                $v = $a[2] ?? '';
                if ($v === '') {
                    $v = ($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? '');
                }
                $v = $dec($v);
                if ($k === 'class') {
                    $tokens = preg_split('~\s+~', trim($v), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $tokens = array_unique($tokens);
                    sort($tokens, SORT_STRING);
                    $v = implode(' ', $tokens);
                }
                if ($k === 'data-class') {
                    $v = ui_sort_object_entries($v);
                }
                $attrs[$k] = $v;
            }
            ksort($attrs, SORT_STRING);
            $line = '<' . $tag;
            foreach ($attrs as $k => $v) {
                $line .= $v === '' ? ' ' . $k : ' ' . $k . '="' . str_replace('"', '&quot;', $esc($v)) . '"';
            }
            $pad = str_repeat('  ', $depth);
            if (in_array($tag, $void, true)) {
                $out[] = $pad . $line . ' />';
                continue;
            }
            $out[] = $pad . $line . '>';
            if ($selfClosing) {
                $out[] = $pad . '</' . $tag . '>';
            } else {
                $depth++;
                if ($tag === 'script' || $tag === 'style') { // raw text up to the matching end tag
                    $raw = '';
                    while ($i + 1 < $n && !(isset($m[$i + 1][1]) && strtolower($m[$i + 1][1]) === $tag)) {
                        $raw .= $m[++$i][0];
                    }
                    $raw = trim(preg_replace('~\s+~', ' ', $raw) ?? '');
                    if ($raw !== '') {
                        $out[] = str_repeat('  ', $depth) . $esc($raw);
                    }
                }
            }
            continue;
        }
        // text (a lone "<" is text too)
        $text = trim(preg_replace('~\s+~', ' ', $dec($tok)) ?? '');
        if ($text !== '') {
            $out[] = str_repeat('  ', $depth) . $esc($text);
        }
    }
    return implode("\n", $out);
}

/** Sort the top-level entries of a JS object literal. Twin of sortObjectEntries in normalize.go. */
function ui_sort_object_entries(string $v): string
{
    $t = trim($v);
    if (strlen($t) < 2 || $t[0] !== '{' || $t[strlen($t) - 1] !== '}') {
        return $v;
    }
    $inner = substr($t, 1, -1);
    $parts = [];
    $depth = 0;
    $start = 0;
    $quote = '';
    $len = strlen($inner);
    for ($i = 0; $i < $len; $i++) {
        $c = $inner[$i];
        if ($quote !== '') {
            if ($c === '\\') {
                $i++;
            } elseif ($c === $quote) {
                $quote = '';
            }
        } elseif ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif ($c === '(' || $c === '[' || $c === '{') {
            $depth++;
        } elseif ($c === ')' || $c === ']' || $c === '}') {
            $depth--;
        } elseif ($c === ',' && $depth === 0) {
            $parts[] = trim(substr($inner, $start, $i - $start));
            $start = $i + 1;
        }
    }
    $parts[] = trim(substr($inner, $start));
    sort($parts, SORT_STRING);
    return '{' . implode(', ', $parts) . '}';
}

/**
 * Compare a rendered partial with a fixture file (Go reference output or a hand written fixture).
 * Both sides are normalised, so fixtures may be pretty printed freely. Returns null when equal,
 * otherwise a readable first-difference message for the test failure output.
 */
function ui_fixture_diff(string $actualHtml, string $fixturePath): ?string
{
    $fixture = @file_get_contents($fixturePath);
    if ($fixture === false) {
        return "fixture not found: $fixturePath";
    }
    $want = explode("\n", ui_normalize_html($fixture));
    $got = explode("\n", ui_normalize_html($actualHtml));
    if ($want === $got) {
        return null;
    }
    $n = max(count($want), count($got));
    for ($i = 0; $i < $n; $i++) {
        if (($want[$i] ?? null) !== ($got[$i] ?? null)) {
            return sprintf("%s differs at line %d\n  want: %s\n  got:  %s", basename($fixturePath), $i + 1, $want[$i] ?? '(end)', $got[$i] ?? '(end)');
        }
    }
    return null;
}

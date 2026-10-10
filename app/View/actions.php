<?php
declare(strict_types=1);

// Datastar expression helpers (the "ds_*" helpers of the plan).

/**
 * A Datastar action expression with the CSRF header, already escaped for a
 * data-* attribute:  data-on:click="<?= act('post', url('/x')) ?>"
 * $opts is developer-written JS only (e.g. "filterSignals: {include: /^pw\\./}"), never user data.
 */
function act(string $method, string $url, string $opts = ''): string
{
    return e(act_raw($method, $url, $opts));
}

/**
 * The same expression NOT escaped, for props whose attrs are escaped by the UI
 * kit (ui_attrs runs attr() on every value): ['data-on:click' => act_raw('post', $u)].
 */
function act_raw(string $method, string $url, string $opts = ''): string
{
    $m = strtolower($method);
    if (!in_array($m, ['get', 'post', 'put', 'patch', 'delete'], true)) {
        throw new InvalidArgumentException('Unknown Datastar action: ' . $method);
    }
    $urlJs = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return '@' . $m . '(' . $urlJs . ", {headers: {'X-CSRF-Token': \$_csrf}" . ($opts !== '' ? ', ' . $opts : '') . '})';
}

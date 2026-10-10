<?php
declare(strict_types=1);

/**
 * /system/spike. Each test writes 'pass' or 'fail: ...' into $spike.<key>.
 * Developer-written expressions only; the one piece of "user-like" data
 * (the echo string) goes through js().
 */
function page_spike(string $echoString): string
{
    $keys = ['outer', 'inner', 'replace', 'prepend', 'append', 'before', 'after', 'remove', 'signals', 'toast',
        'm_get', 'm_post', 'm_put', 'm_patch', 'm_delete', 'slow', 'html_elements', 'html_inner', 'html_signals'];
    $spike = [];
    foreach ($keys as $k) {
        $spike[$k] = '';
    }
    $spike['_slow_start'] = 0;
    $spike['_slow_first'] = -1;
    $spike['_slow_step'] = 0;
    $echoOnly = 'filterSignals: {include: /^spike_echo\\./}';
    $base = '/system/spike';

    $rows = [
        ['outer', 'patch-elements, mode outer (morph by id, no selector)', act('get', url($base . '/patch/outer'))],
        ['inner', 'mode inner into #t-inner', act('get', url($base . '/patch/inner'))],
        ['replace', 'mode replace (no selector)', act('get', url($base . '/patch/replace'))],
        ['prepend', 'mode prepend into #t-prepend', act('get', url($base . '/patch/prepend'))],
        ['append', 'mode append into #t-append', act('get', url($base . '/patch/append'))],
        ['before', 'mode before #t-before', act('get', url($base . '/patch/before'))],
        ['after', 'mode after #t-after', act('get', url($base . '/patch/after'))],
        ['remove', 'mode remove #t-remove, then a checker', act('get', url($base . '/patch/remove'))],
        ['signals', 'patch-signals', act('get', url($base . '/signals'))],
        ['toast', 'toast appended to #toasts', act('get', url($base . '/toast'))],
        ['m_get', 'GET, signals in ?datastar=', act('get', url($base . '/method'), $echoOnly)],
        ['m_post', 'POST, signals in the JSON body, CSRF header', act('post', url($base . '/method'), $echoOnly)],
        ['m_put', 'PUT reaches PHP', act('put', url($base . '/method'), $echoOnly)],
        ['m_patch', 'PATCH reaches PHP', act('patch', url($base . '/method'), $echoOnly)],
        ['m_delete', 'DELETE reaches PHP, signals in ?datastar=', act('delete', url($base . '/method'), $echoOnly)],
        ['html_elements', 'transport=html: text/html body, morph by id', act('get', url($base . '/html/elements'))],
        ['html_inner', 'transport=html: Datastar-Selector + Datastar-Mode headers', act('get', url($base . '/html/inner'))],
        ['html_signals', 'transport=html: application/json signal patch', act('get', url($base . '/html/signals'))],
    ];
    $runAll = [];
    foreach ($rows as $row) {
        $runAll[] = $row[2];
    }
    $slowAction = attr("\$spike._slow_start = Date.now(); \$spike._slow_first = -1; \$spike.slow = 'running (about 3 s)'; ") . act('get', url($base . '/slow'));
    $noCsrf = e('@post(' . json_encode(url($base . '/method'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ')');
    $btn = 'inline-flex h-8 items-center rounded-md border border-border px-3 text-xs hover:bg-accent hover:text-accent-foreground';
    ob_start(); ?>
<div class="flex max-w-5xl flex-col gap-6"
     data-signals="<?= js(['spike' => $spike, 'spike_echo' => ['n' => 41, 's' => $echoString]]) ?>">
  <p class="text-sm text-muted-foreground">
    Run on live through Cloudflare (Rocket Loader on). Every row should read <strong>pass</strong>.
    Record the transport choice in the ADR. Reload the page to reset.
  </p>
  <div class="flex flex-wrap gap-2">
    <button type="button" class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            data-on:click="<?= implode('; ', $runAll) ?>">Run all quick tests</button>
    <button type="button" class="<?= attr($btn) ?>" data-on:click="<?= $slowAction ?>">Run the 3 second test</button>
  </div>

  <div class="overflow-x-auto rounded-xl border border-border bg-card text-card-foreground shadow-sm">
    <table class="w-full text-sm">
      <thead class="border-b border-border"><tr>
        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Test</th>
        <th class="h-10 px-3 text-left font-medium text-muted-foreground">What it proves</th>
        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Run</th>
        <th class="h-10 px-3 text-left font-medium text-muted-foreground">Result</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr class="border-b border-border">
          <td class="px-3 py-2 font-mono text-xs"><?= e($row[0]) ?></td>
          <td class="px-3 py-2"><?= e($row[1]) ?></td>
          <td class="px-3 py-2"><button type="button" class="<?= attr($btn) ?>" data-on:click="<?= $row[2] ?>">Run</button></td>
          <td class="px-3 py-2"><?= spike_result_cell($row[0]) ?></td>
        </tr>
      <?php endforeach; ?>
        <tr class="border-b border-border">
          <td class="px-3 py-2 font-mono text-xs">slow</td>
          <td class="px-3 py-2">4 SSE events 1 s apart: the first must arrive at once (no buffering by PHP, Apache or Cloudflare)</td>
          <td class="px-3 py-2"><button type="button" class="<?= attr($btn) ?>" data-on:click="<?= $slowAction ?>">Run</button></td>
          <td class="px-3 py-2"><?= spike_result_cell('slow') ?> <div id="t-slow" class="text-xs text-muted-foreground">not started</div></td>
        </tr>
        <tr class="border-b border-border">
          <td class="px-3 py-2 font-mono text-xs">csrf</td>
          <td class="px-3 py-2">POST without the CSRF header: expect a red "Security check failed" toast (HTTP 200)</td>
          <td class="px-3 py-2"><button type="button" class="<?= attr($btn) ?>" data-on:click="<?= $noCsrf ?>">Run</button></td>
          <td class="px-3 py-2 text-muted-foreground">check the toast</td>
        </tr>
        <tr class="border-b border-border">
          <td class="px-3 py-2 font-mono text-xs">error hook</td>
          <td class="px-3 py-2">GET a missing URL: expect the "A request failed (404)" banner at the top</td>
          <td class="px-3 py-2"><button type="button" class="<?= attr($btn) ?>" data-on:click="<?= act('get', url($base . '/missing')) ?>">Run</button></td>
          <td class="px-3 py-2 text-muted-foreground">check the banner</td>
        </tr>
        <tr>
          <td class="px-3 py-2 font-mono text-xs">html toast</td>
          <td class="px-3 py-2">transport=html toast (replaces the toast region)</td>
          <td class="px-3 py-2"><button type="button" class="<?= attr($btn) ?>" data-on:click="<?= act('get', url($base . '/html/toast')) ?>">Run</button></td>
          <td class="px-3 py-2 text-muted-foreground">check the toast</td>
        </tr>
      </tbody>
    </table>
  </div>

  <section class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">
    <h2 class="text-base font-semibold">Patch targets</h2>
    <div class="mt-3 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
      <div id="t-outer" data-v="old" class="rounded-md bg-muted p-2">outer: original</div>
      <div id="t-replace" data-v="old" class="rounded-md bg-muted p-2">replace: original</div>
      <div id="t-inner" class="rounded-md bg-muted p-2"><span>inner: original</span></div>
      <ul id="t-prepend" class="rounded-md bg-muted p-2"><li>prepend: existing item</li></ul>
      <ul id="t-append" class="rounded-md bg-muted p-2"><li>append: existing item</li></ul>
      <div class="rounded-md bg-muted p-2"><div id="t-before">before: anchor</div></div>
      <div class="rounded-md bg-muted p-2"><div id="t-after">after: anchor</div></div>
      <div id="t-remove-box" class="rounded-md bg-muted p-2"><div id="t-remove">remove: I should disappear</div></div>
      <div id="t-html-el" data-v="old" class="rounded-md bg-muted p-2">html elements: original</div>
      <div id="t-html-inner" class="rounded-md bg-muted p-2"><span>html inner: original</span></div>
    </div>
  </section>
</div>
<?php
    return (string) ob_get_clean();
}

function spike_result_cell(string $key): string
{
    $path = '$spike.' . $key;
    return '<span class="font-medium" data-text="' . attr($path . " || 'not run'") . '"'
        . ' data-class="' . attr("{'text-primary': " . $path . " === 'pass' || " . $path . ".startsWith('pass'), 'text-destructive': " . $path . ".startsWith('fail')}") . '">not run</span>';
}

/** HTML for /system/spike/patch/{mode}; each piece reports its own placement. */
function spike_fragment(string $mode): string
{
    $check = [
        'outer' => ['<div id="t-outer" data-v="new" class="rounded-md bg-muted p-2" data-init="', "\$spike.outer = document.querySelectorAll('#t-outer').length === 1 && el.dataset.v === 'new' ? 'pass' : 'fail'", '">outer: patched</div>'],
        'replace' => ['<div id="t-replace" data-v="new" class="rounded-md bg-muted p-2" data-init="', "\$spike.replace = document.querySelectorAll('#t-replace').length === 1 && el.dataset.v === 'new' ? 'pass' : 'fail'", '">replace: patched</div>'],
        'inner' => ['<span data-init="', "\$spike.inner = el.parentElement !== null && el.parentElement.id === 't-inner' && el.parentElement.children.length === 1 ? 'pass' : 'fail'", '">inner: patched</span>'],
        'prepend' => ['<li data-init="', "\$spike.prepend = el.parentElement.id === 't-prepend' && el.parentElement.firstElementChild === el ? 'pass' : 'fail'", '">prepended</li>'],
        'append' => ['<li data-init="', "\$spike.append = el.parentElement.id === 't-append' && el.parentElement.lastElementChild === el ? 'pass' : 'fail'", '">appended</li>'],
        'before' => ['<div data-init="', "\$spike.before = el.nextElementSibling !== null && el.nextElementSibling.id === 't-before' ? 'pass' : 'fail'", '">inserted before</div>'],
        'after' => ['<div data-init="', "\$spike.after = el.previousElementSibling !== null && el.previousElementSibling.id === 't-after' ? 'pass' : 'fail'", '">inserted after</div>'],
        'remove' => ['<span data-init="', "\$spike.remove = document.getElementById('t-remove') === null ? 'pass' : 'fail'; el.remove()", '"></span>'],
    ];
    if (!isset($check[$mode])) {
        return '';
    }
    return $check[$mode][0] . attr($check[$mode][1]) . $check[$mode][2];
}

function spike_slow_step(int $step): string
{
    $init = match ($step) {
        1 => '$spike._slow_first = Date.now() - $spike._slow_start',
        4 => "\$spike.slow = (\$spike._slow_first >= 0 && \$spike._slow_first < 900 && Date.now() - \$spike._slow_start >= 2900)"
            . " ? 'pass (first event after ' + \$spike._slow_first + ' ms, total ' + (Date.now() - \$spike._slow_start) + ' ms)'"
            . " : 'fail: buffered or too fast (first event after ' + \$spike._slow_first + ' ms, total ' + (Date.now() - \$spike._slow_start) + ' ms)'",
        default => '$spike._slow_step = ' . $step,
    };
    return '<div id="t-slow" class="text-xs text-muted-foreground" data-init="' . attr($init) . '">step ' . e($step) . ' of 4</div>';
}

function spike_html_fragment(string $kind): string
{
    if ($kind === 'elements') {
        return '<div id="t-html-el" data-v="new" class="rounded-md bg-muted p-2" data-init="'
            . attr("\$spike.html_elements = el.dataset.v === 'new' ? 'pass' : 'fail'") . '">html elements: patched via text/html</div>';
    }
    return '<span data-init="' . attr("\$spike.html_inner = el.parentElement !== null && el.parentElement.id === 't-html-inner' ? 'pass' : 'fail'") . '">html inner: patched</span>';
}

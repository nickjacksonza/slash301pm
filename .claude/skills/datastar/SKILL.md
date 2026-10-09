---
name: datastar
description: Use when writing or reviewing any Datastar markup (data-* attributes, signals, @get/@post actions), Datastar SSE responses, or PHP handlers and Response events in the Slash 301 PM repo. Source-verified against Datastar 1.0.4 and the official PHP SDK, free core features only.
---

# Datastar in Slash 301 PM

Everything here was derived from the Datastar v1.0.4 source and `datastar-php` source, not from the website. Long tables, client internals and the full spike checklist are in `reference.md` (same folder). Load it when a detail below is not enough. "Verify in spike" means the source is ambiguous or looks buggy: test it in Phase 1 before relying on it.

## 1. Mental model

1. The server owns data and HTML. The browser holds one small tree of signals (reactive JSON) and `data-*` attributes that bind, show, or send it.
2. An action (`@get`, `@post`, ...) sends every non-underscore signal to the server (GET/DELETE: `?datastar=<json>`, others: JSON body), then applies the response.
3. A response is HTML patched into the page by element id (morph), a signal merge-patch, or a script. Only HTTP 200 is applied; any other status is ignored except for an `error` event.
4. Handlers are `fn(Request, Deps): Response`. Responses are short: events, then close. Only `Response::send()` touches the PHP SDK.
5. Signals are snake_case and namespaced (`brief.due_date`). `_name` signals are UI-only and never sent. User data enters attributes only through `js()`, text only through `e()`.

## 2. Attributes (free core, 1.0.4)

Form: `data-<name>[:key][__mod[.tag]...]`. Value is a JavaScript expression: `$sig` reads a signal (`$a.b` nests), `@act(...)` calls an action, `evt` and `el` are in scope. Time tags are `300ms`, `2s` or a bare number of ms, and must be the FIRST tag.

| Attribute | Syntax | Modifiers | Example |
|---|---|---|---|
| `data-signals` | `:path="expr"` or `="{obj}"` | `ifmissing`, `case` | `data-signals:filters="{stage: '', q: ''}"` |
| `data-bind` | `:path` or `="path"` | `case`, `prop.x`, `event.x` | `<input data-bind:brief.title>` |
| `data-text` | `="expr"` | none | `<span data-text="$brief.title"></span>` |
| `data-show` | `="expr"` | none | `<p data-show="$_saving">Saving</p>` |
| `data-class` | `:name="expr"` or `="{a: expr}"` | `case` | `data-class:is-open="$_open"` |
| `data-attr` | `:name="expr"` or `="{a: expr}"` | none | `data-attr:disabled="$_saving"` |
| `data-style` | `:css-prop="expr"` or `="{prop: expr}"` | none | `data-style:width="$pct + '%'"` |
| `data-computed` | `:name="expr"` (read-only) | `case` | `data-computed:_total="$a + $b"` |
| `data-effect` | `="stmts"` | none | `data-effect="document.title = $title"` |
| `data-ref` | `:name` or `="name"` | `case` | `data-ref:_dlg` (signal holds the element) |
| `data-indicator` | `:name` or `="name"` | `case` | `data-indicator:_saving` |
| `data-init` | `="stmts"` | `delay.T`, `viewtransition` | `data-init__delay.4s="el.remove()"` |
| `data-on` | `:event="stmts"` | `window`, `document`, `outside`, `once`, `prevent`, `stop`, `capture`, `passive`, `debounce.T[.leading][.notrailing]`, `throttle.T[.noleading][.trailing]`, `delay.T`, `viewtransition`, `case` | `data-on:input__debounce.300ms="@get('/x')"` |
| `data-on-intersect` | `="stmts"` | `once`, `exit`, `full`, `half`, `threshold.N`, `debounce`, `throttle`, `delay`, `viewtransition` | `data-on-intersect__once="@get('/more')"` |
| `data-on-interval` | `="stmts"` | `duration.T[.leading]` (default 1s), `viewtransition` | `data-on-interval__duration.30s="@get('/poll')"` |
| `data-on-signal-patch` | `="expr using patch"` + `data-on-signal-patch-filter="{include: /re/}"` | `debounce`, `throttle`, `delay` | see autosave note |
| `data-json-signals` | bare, optional filter object | `terse` | debug only |
| `data-ignore` | bare | `self` | `data-ignore__self` skips only this element |
| `data-ignore-morph` | bare | none | morph leaves the element alone |
| `data-preserve-attr` | `="value open"` | none | on the incoming element, attrs the morph keeps |

Rules that bite:
- `data-on` needs a value even for pure modifiers: `data-on:dragover__prevent="1"`.
- `data-on:submit` on a `<form>` auto calls `preventDefault()`.
- `data-init` runs when the element is created or patched into the DOM, so `data-init="@get('/x')"` is the load-time fetch, `data-init="el.showModal()"` opens a dialog.
- Value-returning attributes auto-`return` the last statement; `data-on`, `data-init`, `data-effect` do not.
- `$count-1` reads a signal literally named `count-1`. Always write `$count - 1`.
- Never use `__` inside a key or signal name (it starts a modifier).
- Reading an undeclared signal creates it as `''` and it is then sent to the server. A typo is a phantom signal.
- Assigning into an undeclared object (`$move.to = 'x'`) silently does nothing. Declare with `data-signals` first.
- Not allowed to mix: `data-bind:foo="bar"` (key and value together) throws.

Key case conversion: HTML lowercases attribute names, so `data-bind:fooBar` is `foobar`. For signal-name keys (bind, signals, computed, indicator, ref) the default is camel: `foo-bar` becomes `fooBar`; underscores and dots are untouched, so `brief.due_date` stays exactly that. `__case.snake` turns hyphens into underscores. `data-class` and `data-on` keys are used verbatim (hyphens kept). The value form `data-bind="brief.due_date"` is never converted. Convention here: snake_case with `_` and `.` only, no hyphens in signal keys.

Underscore rule: default `filterSignals.exclude` is `/(^|\.)_/` tested on each dotted leaf path, so any segment starting with `_` (`_saving`, `edit._tmp`) is excluded from `@get/@post/...`. Passing `filterSignals: {include: ...}` keeps that exclude. Passing your own `exclude` replaces it. `@setAll` and `@toggleAll` do not apply it.

## 3. Actions

Free core registers: `@get @post @put @patch @delete @query` (HTTP actions), `@peek(fn)`, `@setAll(value, {include, exclude})`, `@toggleAll({include, exclude})`. Nothing else exists (see section 7 for Pro). Do not use `@query` (HTTP QUERY method). Our routes use PATCH/PUT/DELETE, which the client supports (`@patch`, `@put`, `@delete`), but verify in the spike that Apache and Cloudflare deliver them to PHP (body via `php://input`; DELETE has no body, its signals ride in `?datastar=`). Fallback: `@post` plus a method-override route.

`@post(url, {options})` options and defaults:

| Option | Default | Meaning |
|---|---|---|
| `headers` | none | extra request headers: `{headers: {'X-CSRF-Token': $_csrf}}` |
| `contentType` | `'json'` | `'form'` sends the enclosing form instead of signals |
| `selector` | none | CSS selector of the FORM in form mode (not a patch target) |
| `filterSignals` | `{include: /.*/, exclude: /(^\|\.)_/}` | which signals to send |
| `payload` | none | send this object instead of signals |
| `openWhenHidden` | `false` for GET, `true` otherwise | abort and resend on tab visibility |
| `requestCancellation` | `'auto'` | new request with same method+url aborts the old one; `'cleanup'`, `'disabled'`, or an AbortController |
| `retry` | `'auto'` | `'auto'`, `'error'`, `'always'`, `'never'` (non-200 is never retried under `'auto'`) |
| `retryInterval` / `retryScaler` / `retryMaxWait` / `retryMaxCount` | `1000` / `2` / `30000` / `10` | backoff |

Always sent: `Accept: text/event-stream, text/html, application/json` and `Datastar-Request: true`. There is no global header setting, so the CSRF header must be on each call: use the `act()` helper in section 6.

Response content types handled by the client (this matters for `transport=html`):
- `text/event-stream`: SSE events (section 4).
- `text/html`: body is one patch-elements. Optional response headers `Datastar-Selector`, `Datastar-Mode`, `Datastar-Namespace`, `Datastar-Use-View-Transition`. No selector header means outer morph by top-level element ids.
- `application/json`: body is a signal merge patch. Optional header `Datastar-Only-If-Missing: true`.
- `text/javascript`: body is executed as a script (optional `Datastar-Script-Attributes` JSON header). Verify in spike: the promise never resolves, so `data-indicator` stays true.
- Any other type is ignored. Non-200 status: body ignored, `datastar-fetch` event `error` fires with `argsRaw.status`. Never answer an action with a 3xx (fetch follows it and the page HTML gets patched).
- `responseOverrides` is typed but not in the bundle. Do not use.
- Network errors retry with backoff even with `retry:'never'`, which can resend a POST. For writes consider `retryMaxCount: 0` (verify in spike).

Global error hook (put once on `<body>`): `data-on:datastar-fetch="evt.detail.type === 'error' && ($_net_error = evt.detail.argsRaw.status)"`.

## 4. SSE wire format

Block: `event: <type>`, optional `id:`, optional `retry:` (omitted when 1000), `data: <key> <value>` lines, blank line. Event types: `datastar-patch-elements`, `datastar-patch-signals`.

```
event: datastar-patch-elements
data: selector #toasts
data: mode append
data: elements <div class="toast">Saved</div>

event: datastar-patch-signals
data: signals {"brief":{"saved_at":"12:03"}}

```

| Event | Data keys (defaults, omitted when default) |
|---|---|
| patch-elements | `selector` (none = match each top-level element by `id`), `mode` (`outer`), `namespace` (`html`; `svg`, `mathml`), `useViewTransition` (false), `viewTransitionSelector` (only with useViewTransition), `elements` (one data line per HTML line; omit for remove) |
| patch-signals | `signals` (JSON, RFC 7386 merge patch, `null` deletes), `onlyIfMissing` (false) |

Modes: `outer` morph the element (keeps state), `inner` morph children, `replace` swap without morph, `prepend`, `append`, `before`, `after`, `remove`. Only `outer` and `replace` may omit the selector; `remove` needs a selector. Top-level elements need stable unique `id`s when there is no selector. Scripts in patched HTML run once.

Required headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive` (HTTP/1.1 only). The PHP SDK adds `X-Accel-Buffering: no`. Use LF line endings, no stray `\r`.

Request signals: GET and DELETE carry `?datastar=<url-encoded json>`, POST/PUT/PATCH carry a JSON body. Same shape both ways. Large GET payloads can exceed URL limits, so restrict with `filterSignals.include`.

## 5. PHP SDK

Package `starfederation/datastar-php`, namespace `starfederation\datastar`, PHP 8.1+. Classes: `ServerSentEventGenerator`, enums `ElementPatchMode` (`Outer Inner Remove Replace Prepend Append Before After`), `NamespaceType`, `EventType`.

```php
use starfederation\datastar\ServerSentEventGenerator;
use starfederation\datastar\enums\ElementPatchMode;

http_response_code(200);
$sse = new ServerSentEventGenerator();          // calls ignore_user_abort(false)
$sse->sendHeaders();                            // static::headers(); skipped if headers_sent()
$sse->patchElements($html, ['selector' => '#toasts', 'mode' => ElementPatchMode::Append]);
$sse->patchSignals(json_encode($arr, JSON_THROW_ON_ERROR));   // array|string
$sse->removeElements('#row-12');
$sse->executeScript('window.location = ' . json_encode($url, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP));
```
Option keys: patchElements `selector mode namespace useViewTransition viewTransitionSelector eventId retryDuration`; patchSignals `onlyIfMissing eventId retryDuration`; executeScript `autoRemove attributes eventId retryDuration`. `ServerSentEventGenerator::readSignals()` is static but reads `$_GET['datastar']` without `isset`, so write our own reader in Request (GET/DELETE: `$_GET['datastar'] ?? ''`, others: `php://input`, `json_decode(..., true)`, treat as untrusted input).

SDK traps: `location()` interpolates the URI unescaped into JS (use `executeScript` as above). Empty PHP arrays encode as `[]`, send `(object)[]` for an empty object. Each call flushes, so any earlier output corrupts the stream: render HTML with `ob_start()` first. The SDK never sets a status code and has no toast helper.

How `Response::send()` should wrap it (single file, the only place that imports the SDK):

```php
final class Response {
    private function __construct(private ?string $page, private array $events, private int $status) {}
    public static function page(string $html, int $status = 200): self { return new self($html, [], $status); }
    public static function events(PatchElements|PatchSignals|Toast|Redirect ...$e): self { return new self(null, $e, 200); }

    public function send(): void {
        http_response_code($this->status);
        if ($this->page !== null) { header('Content-Type: text/html; charset=utf-8'); echo $this->page; return; }
        Config::transport() === 'html' ? $this->sendHtml() : $this->sendSse();
    }
    private function sendSse(): void {
        $sse = new ServerSentEventGenerator();
        $sse->sendHeaders();
        foreach ($this->events as $ev) {
            match (true) {
                $ev instanceof PatchElements => $sse->patchElements($ev->html, array_filter(['selector' => $ev->selector, 'mode' => $ev->mode])),
                $ev instanceof PatchSignals  => $sse->patchSignals(json_encode($ev->signals, JSON_THROW_ON_ERROR)),
                $ev instanceof Toast         => $sse->patchElements($ev->html(), ['selector' => '#toasts', 'mode' => 'append']),
                $ev instanceof Redirect      => $sse->executeScript('window.location = ' . js_raw($ev->url)),
            };
        }
    }
}
```
`transport=html` (our rule on top of the client behavior in section 3): one `text/html` response can carry only ONE patch: elements sharing one selector and mode (send `Datastar-Selector`/`Datastar-Mode` headers, bodies concatenated), or a signals-only `application/json` body, or a `text/javascript` body for Redirect. Anything mixed (row patch plus toast, two selectors) cannot be represented: `sendHtml()` must throw a `LogicException`, and in html mode a Toast must be an `outer` patch of the `#toasts` container (replace, not append). Always set `http_response_code(200)` for anything the client must process.

## 6. Project patterns

Helpers (plain PHP, not Datastar):
```php
const JSF = JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_THROW_ON_ERROR;
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function js_raw(mixed $v): string { return json_encode($v, JSF); }          // for Response/script use
function js(mixed $v): string { return e(js_raw($v)); }                      // for data-* attribute values
function act(string $m, string $url, string $opts = ''): string {            // $opts: developer JS only, never user data
    return e('@' . $m . '(' . js_raw($url) . ", {headers: {'X-CSRF-Token': \$_csrf}" . ($opts ? ", $opts" : '') . '})');
}
```
Page root once: `<body data-signals:_csrf="<?= js($csrf) ?>">`, `<div id="toasts" aria-live="polite"></div>` outside any morphed region, and the global error hook from section 3. Script tag: `<script type="module" data-cfasync="false" src="public/js/datastar.js?v=N"></script>` (the plain `datastar.js` bundle, never the rocket one).

Inline grid cell edit (fetch editor, patch row on save):
```php
<tr id="row-<?= (int)$t['id'] ?>">
  <td id="cell-<?= (int)$t['id'] ?>-title" data-on:dblclick="<?= act('get', "/tasks/{$t['id']}/cell/title") ?>"><?= e($t['title']) ?></td>
</tr>
<!-- GET response: Response::events(PatchElements::html($editor)) where $editor is: -->
<td id="cell-12-title" data-signals:edit.value="<?= js($title) ?>">
  <input data-bind:edit.value data-init="el.focus()"
         data-on:keydown="evt.key === 'Enter' && <?= act('post', '/tasks/12/cell/title', 'filterSignals: {include: /^edit\./}') ?>">
  <button data-on:click="<?= act('get', '/tasks/12/row') ?>">Cancel</button>
</td>
<!-- POST response: Response::events(PatchElements::html(render_row($task)), Toast::ok('Saved')). -->
<!-- Validation failure: HTTP 200 with Toast::error('Title required') only; the editor stays open. -->
```
The cell and row ids must be unique and stable; the row patch matches `row-12` by id and morphs it back to display mode. Cancel uses a GET that returns the row.

Filter signals with debounce (list is one patched container):
```php
<div data-signals:filters="<?= js(['stage' => $stage, 'q' => $q]) ?>">
  <input data-bind:filters.q data-on:input__debounce.300ms="<?= act('get', '/tasks/list', 'filterSignals: {include: /^filters\./}') ?>">
  <select data-bind:filters.stage data-on:change="<?= act('get', '/tasks/list', 'filterSignals: {include: /^filters\./}') ?>">...</select>
  <button data-on:click="@setAll('', {include: /^filters\./})">Clear</button>
</div>
<div id="task-list">...</div>   <!-- GET response patches this id -->
```
`requestCancellation:'auto'` aborts a stale list request for free. The server reads `filters.q` and `filters.stage` from `?datastar=`.

Kanban drop posting `move.*`:
```php
<div class="board" data-signals:move="{card_id: 0, to: ''}">
  <ul id="col-doing" data-on:dragover__prevent="1"
      data-on:drop__prevent="$move.to = 'doing'; <?= act('post', '/cards/move', 'filterSignals: {include: /^move\./}') ?>">
    <li id="card-7" draggable="true" data-on:dragstart="$move.card_id = 7">...</li>
  </ul>
</div>
<!-- Response: PatchElements of BOTH affected columns (<ul id="col-todo">, <ul id="col-doing">), one string, no selector. -->
```
On a rejected move return the original columns plus a Toast, still HTTP 200, so the card snaps back.

Brief autosave with indicator (indicator must sit on the SAME element that runs the action):
```php
<form id="brief-form" data-signals:brief="<?= js($brief) ?>" data-indicator:_saving
      data-on:input__debounce.800ms="<?= act('post', '/briefs/12/autosave', 'filterSignals: {include: /^brief\./}') ?>"
      data-on:change__debounce.800ms="<?= act('post', '/briefs/12/autosave', 'filterSignals: {include: /^brief\./}') ?>">
  <input data-bind:brief.title> <input type="date" data-bind:brief.due_date>
  <span data-show="$_saving">Saving...</span><span id="brief-status">Saved</span>
</form>
<!-- Response patches only #brief-status. Never re-patch the form itself or data-signals overwrites typing. -->
```
Prefer this input/change wrapper over `data-on-signal-patch` for autosave: the latter also fires for server patches and probably for the initial bind (verify in spike) and can loop.

Toast append: `Toast::ok|warn|error($msg)` renders `<div class="toast toast-{kind}" role="status" data-init__delay.4s="el.remove()">{e(msg)}</div>` and `Response::send()` appends it to `#toasts` (selector `#toasts`, mode `append`).

Dialog or sheet content fetch:
```php
<button data-on:click="<?= act('get', '/briefs/12/sheet') ?>">Open</button>
<div id="sheet-root"></div>
<!-- Response: PatchElements::html('<dialog id="sheet" data-init="el.showModal()" data-on:close="el.remove()">...<button data-on:click="el.closest(\'dialog\').close()">Close</button></dialog>', '#sheet-root', 'inner') -->
```
Use `data-indicator:_loading` on the opening button for a spinner. Native `<dialog>` keeps focus handling free.

## 7. Pitfalls

- Escaping: text via `e()`, anything inside a `data-*` expression via `js()` (JSON with HEX flags, then htmlspecialchars). Never concatenate user data into an expression, a selector, a URL path segment (cast ids with `(int)`), or `executeScript`. Patched `<script>` runs, so unescaped user HTML is code execution.
- Status codes: validation and CSRF failures should be HTTP 200 with a Toast/PatchElements (the client ignores bodies of 4xx/5xx). Reserve real error statuses for true faults and show them via the `datastar-fetch` error hook.
- Case: keys are lowercased by the HTML parser, camel-converted on hyphens, never on underscores (section 2). Declare every signal in snake_case, namespaced, and read it back identically in PHP (`$signals['brief']['due_date']`).
- Signal payload: whole tree goes on every request. Namespace by screen and restrict with `filterSignals.include`. Name refs, indicators, computed and UI flags with `_`.
- Types: `data-bind` coerces by existing signal type; numbers arrive as numbers or strings. Validate and cast server-side; never trust signals (they are user input).
- Morph: every patched top-level element needs a unique stable `id`; duplicates break matching. `data-signals` inside patched markup overwrites live state unless `__ifmissing`. Use `data-ignore-morph` for widgets that must survive patches.
- Rocket Loader / Cloudflare: keep `data-cfasync="false"` on the module script and cache bust with `?v=N`. Verify in spike that the module script is untouched and that Cloudflare does not buffer or rewrite fragments (Email Obfuscation applies to `text/html` bodies).
- Buffering: our responses are short, so proxy buffering only delays the whole response. Still call `flush()` via the SDK and keep gzip and `output_buffering` from adding output before `sendHeaders()`.
- Pro-only, do not use: anything not in section 2 and 3. From Datastar's public docs (unverifiable offline): `data-animate`, `data-custom-validity`, `data-match-media`, `data-on-raf`, `data-on-resize`, `data-persist`, `data-query-string`, `data-replace-url`, `data-scroll-into-view`, `data-view-transition`, `@clipboard`, `@fit`, `@intl`, and the Rocket component bundle (`datastar-rocket.js`, in the repo but a separate bundle). `tsconfig` maps `@pro/*` to a source tree that is absent from the free repo. Core replacements: `__viewtransition` modifier and `useViewTransition` patch option, plain JS in `data-on` for localStorage, `executeScript` for `history.replaceState`.
- CSP: expressions compile with `Function()`. A CSP without `unsafe-eval` needs the `data-nonce` mode (see `reference.md`).
- Verify in spike (full list in `reference.md` section J): `responseOverrides` unwired; `text/javascript` leaves indicator stuck; `retry:'never'` and network retries; `data-on-signal-patch` on initial bind; morph with a focused input; PATCH/PUT/DELETE and SSE passing through Apache and Cloudflare; number and date bind coercion; `Connection: keep-alive` behind Cloudflare.

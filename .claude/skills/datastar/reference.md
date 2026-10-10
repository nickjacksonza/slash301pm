# Datastar 1.0.4 reference (derived from source)

Sources: Datastar v1.0.4 `library/src` (plugins, engine), `sdk/ADR.md`, official `datastar-php` 1.0.1. Nothing here comes from the website. "Verify in spike" marks anything the source leaves ambiguous.

## A. Attribute requirements and every modifier

Attribute name is `data-<plugin>[:key][__mod[.tag[.tag]]...]`. Parsing (`parseAttributeKey`): split on `__` first, then the first `:` splits plugin from key, then each modifier splits on `.` into tags. So a key or signal name must never contain `__`, and the key part keeps its dots (`filters.q`).

If a requirement is violated Datastar throws a console error (KeyNotAllowed, KeyRequired, ValueNotAllowed, ValueRequired, KeyAndValueProvided, KeyOrValueRequired).

| Plugin | Key | Value | Returns value | Modifiers (tags) |
|---|---|---|---|---|
| attr | optional | must | yes | none |
| bind | exclusive with value | exclusive with key | no | `case`, `prop.<name>`, `event.<name>[.<name>...]` |
| class | optional | must | yes | `case` |
| computed | optional | must | yes | `case` |
| effect | denied | must | no | none |
| indicator | exclusive | exclusive | no | `case` |
| init | denied | must | no | `delay.<time>`, `viewtransition` |
| json-signals | denied | optional (filter object) | n/a | `terse` |
| on | must | must | no | `window`, `document`, `outside`, `capture`, `passive`, `once`, `prevent`, `stop`, `debounce.<time>[.leading][.notrailing]`, `throttle.<time>[.noleading][.trailing]`, `delay.<time>`, `viewtransition`, `case` |
| on-intersect | denied | must | no | `full`, `half`, `threshold.<0-100>`, `exit`, `once`, `debounce`, `throttle`, `delay`, `viewtransition` |
| on-interval | denied | must | no | `duration.<time>[.leading]` (default 1000ms), `viewtransition` |
| on-signal-patch | only `filter` allowed (see below) | must | yes | `debounce`, `throttle`, `delay` (same tags as `on`) |
| ref | exclusive | exclusive | no | `case` |
| show | denied | must | yes | none |
| signals | optional | optional | yes | `ifmissing`, `case` |
| style | optional | must | yes | none |
| text | denied | must | yes | none |

Engine-level attributes (not plugins): `data-ignore` (skip this element and descendants), `data-ignore__self` (skip only this element), `data-ignore-morph` (patching skips morphing this element when both old and new have it, and skips its descendants), `data-preserve-attr="value open"` (on the NEW element: space separated attribute names the morph must not overwrite), `data-nonce` on `<html>` (CSP mode, read once then removed).

Time tags: `500ms`, `2s`, or a bare number meaning ms. The FIRST tag of the modifier must be the time (`__debounce.300ms.leading`, never `__debounce.leading.300ms`).

Timing defaults: debounce = trailing only (add `.leading` for leading, `.notrailing` to drop trailing). throttle = leading only (`.noleading` to drop, `.trailing` to add). Modifier order inside the callback: delay wraps first, then debounce, then throttle.

`on` details: event name comes from the key (`data-on:click`, `data-on:keydown`). Default case rule for event names is "kebab", which is a no-op in 1.0.4 (see section C). `data-on:submit` on a `<form>` calls `preventDefault()` automatically. Events named `datastar-fetch` and `datastar-signal-patch` attach to `document` automatically. `__outside` listens on document and fires when the event target is outside the element. The expression gets `evt` (the Event) and `el`. A value is mandatory even for pure-modifier uses (`data-on:dragover__prevent="1"`).

`on-signal-patch`: the expression receives `patch` (the filtered patch object). Filter lives in a SEPARATE attribute on the same element: `data-on-signal-patch-filter='{"include":"^brief\\."}'` or JS form `{include: /^brief\./}`. The `:filter` key form is accepted by the code but the filter value is read from the `-filter` attribute, so use that. It fires for patches from any source, including the server and including the initial creation of bound signals (verify in spike).

`json-signals`: debug only. Sets the element text to JSON of the signals, reactive.

`style`: `data-style:background-color="$c"` (key used as the CSS property verbatim) or `data-style="{backgroundColor: $c}"` (object keys are kebab-cased). Falsy (except 0) restores the original inline value.

`class`: `data-class:is-open="$x"` or `data-class="{'a b': $x, c: !$x}"`. Keys with spaces apply several classes. Cleanup removes the classes.

`attr`: `true` or `''` sets an empty attribute, `false`/`null`/`undefined` removes it, objects and functions are stringified.

## B. data-bind behaviour

- Signal name is the key (`data-bind:brief.due_date`) or the value (`data-bind="brief.due_date"`). Never both.
- If the signal does not exist it is created from the element's current value (`ifMissing`). If it exists, the element is set from the signal.
- Type coercion is by the current signal type: `type=number`/`range` give a number unless the signal is already a string; text, date, textarea, select give strings. Always cast and validate on the server.
- Checkbox: no `value` attribute (value "on") gives boolean (or string if the signal is a string); with `value="x"` gives `"x"` or `""`. Several checkboxes bound to the same name with an array signal give an array.
- Radio: string (number if the signal is a number); a missing signal adopts the checked radio; sets `name` to the signal name if absent.
- `select multiple`: array. `input[type=file]`: signal becomes an array of `{name, contents(base64), mime}` (do not use for uploads; use `contentType: 'form'`).
- Custom elements (tag has `-`): `value` property or attribute, listens to `input` and `change`.
- Events listened: `input` for text-like, number, range, checkbox, radio, textarea; `change` for select. `__event.<name>` overrides, `__prop.<name>` binds another property.
- The morph dispatches an internal `datastar-prop-change` event so a patched `value`/`checked` re-syncs the signal.

## C. Expressions

- Source of truth: `genRx` in `engine.ts`. Expressions are compiled with `Function(el, $, __action, evt, ...)`. Globals (`window`, `document`, `console`, `setTimeout`) are reachable.
- `$name` becomes `$['name']`. The regex is `\$(\w+(?:[.-]\w+)*)`: dots nest (`$a.b` is `$['a']['b']`) and a HYPHEN inside is part of the name. So `$count-1` reads signal `count-1`; write `$count - 1`. `$a-$b` is fine.
- `@name(` becomes `__action("name", evt, ...)`. Only registered actions work, otherwise `UndefinedAction`.
- Attributes that return a value (see section A) split the code on `;` and auto-`return` the last statement. `data-on`, `data-init`, `data-effect` run the text as statements (no auto return).
- Reading a signal that does not exist creates it as `''` and fires a signal patch. A typo therefore creates a phantom signal that is sent to the server.
- Writing `$move.card_id = 1` when `move` is undeclared fails silently (the read of `$move` returns the string `''`). Declare objects with `data-signals` first.
- Every event handler body runs inside `beginBatch/endBatch`, so effects run once after the handler.
- Values given to `data-signals` are JavaScript expressions, not JSON: strings need quotes (`data-signals:name="'abc'"`).

### Case conversion of keys

1. HTML lowercases attribute names, so `data-bind:fooBar` arrives as `foobar`. You cannot type camelCase in a key.
2. `bind`, `signals`, `computed`, `indicator`, `ref`: default is `camel`: `-x` becomes `X` (`foo-bar` becomes `fooBar`). Underscores and dots are untouched, so `due_date` and `brief.due_date` stay as written. `__case.snake` turns hyphens into underscores. `__case.pascal` upper-cases the first letter then camel-cases. `__case.camel.pascal` applies in order. `kebab` exists as a default label for `class` and `on` but has NO implementation in 1.0.4, so those keys are used verbatim; `__case.camel` on `on` or `class` does convert hyphens to camelCase.
3. Value form (`data-bind="brief.due_date"`) is never case-converted.
4. Expression references (`$brief.due_date`) are case-sensitive and verbatim.
5. Rule for this repo: write signal keys in snake_case with underscores and dots only, no hyphens, no uppercase.

### Underscore rule (what is excluded from requests)

`@get/@post/...` default `filterSignals.exclude` is `/(^|\.)_/`. It is tested against each leaf path, so a path is excluded when ANY segment starts with `_` (`_saving`, `edit._tmp`, `_ui.open`). If you pass `filterSignals: {include: ...}` without `exclude`, the default exclude still applies. If you pass your own `exclude`, you lose the underscore rule unless you repeat it. `@setAll`/`@toggleAll` do NOT apply the underscore rule. Underscore signals are still patchable by the server and still readable in expressions.

## D. Actions in full

Registered actions in the free bundle: `get`, `post`, `put`, `patch`, `delete`, `query`, `peek`, `setAll`, `toggleAll`. Nothing else.

Signature: `@post(url, options?)`. All options below are destructured in `fetch.ts`:

| Option | Default | Notes |
|---|---|---|
| `contentType` | `'json'` | `'json'` sends signals; `'form'` sends the form instead (signals NOT sent) |
| `selector` | none | CSS selector of the FORM for `contentType:'form'`; default is `el.closest('form')`. It is not a patch target |
| `headers` | none | object merged over defaults |
| `filterSignals` | `{include: /.*/, exclude: /(^\|\.)_/}` | regex or string; tested against dotted leaf paths |
| `payload` | none | if set, replaces the filtered signals as the JSON payload |
| `openWhenHidden` | `false` for `@get`, `true` for the rest | when false the request is aborted when the tab is hidden and re-sent when visible |
| `requestCancellation` | `'auto'` | `'auto'` aborts a previous in-flight request of the same method and same url string; `'cleanup'` also aborts when the element is removed; `'disabled'`; or an `AbortController` |
| `retry` | `'auto'` | `'auto'\|'error'\|'always'\|'never'` |
| `retryInterval` | `1000` ms | first retry delay |
| `retryScaler` | `2` | multiplier per retry |
| `retryMaxWait` | `30000` ms | cap |
| `retryMaxCount` | `10` | cap |
| `responseOverrides` | n/a | declared in the TypeScript type only; the compiled bundle does not contain it. Do not use |

Request building:
- Headers always sent: `Accept: text/event-stream, text/html, application/json` and `Datastar-Request: true`. Add `Content-Type: application/json` for json mode on methods with a body.
- GET and DELETE: signals JSON goes in the query string as `datastar=<json>` (URL-encoded). POST, PUT, PATCH, QUERY: JSON body.
- `contentType:'form'`: form is validated (`checkValidity`, skipped with `novalidate`); the submit button's name/value is appended; GET/DELETE put fields in the query string, others send urlencoded (or multipart when the form has `enctype="multipart/form-data"`). Needs a form; otherwise `FetchFormNotFound`.
- `@query` uses HTTP method `QUERY`. Do not use on shared Apache.

Response handling (`fetch.ts`, in order):
1. Status 400 and above: fires a `datastar-fetch` event of type `error` with `argsRaw.status`.
2. Any status other than 200: body is ignored. It retries only if `retry:'always'`, or `retry:'error'` and status is 4xx/5xx; never for 204 or 3xx. With `'auto'` it never retries a non-200. So validation errors must be sent as 200.
3. Status 200 and `Content-Type` contains `text/html`: whole body becomes a `datastar-patch-elements` with `elements`. Optional response headers: `Datastar-Selector`, `Datastar-Mode`, `Datastar-Namespace`, `Datastar-Use-View-Transition`. No other header is read (no `viewTransitionSelector`).
4. `application/json`: body is a `datastar-patch-signals` (JSON merge patch). Optional header `Datastar-Only-If-Missing: true`.
5. `text/javascript`: a `<script>` is created and appended to `<head>`. Optional header `Datastar-Script-Attributes` (JSON object of attributes). In the source this branch does `dispose(); return` without resolving the promise, so the `finished` event never fires and `data-indicator` stays true (verify in spike).
6. Anything else (including `text/event-stream`): parsed as SSE. Only events whose name starts with `datastar` are used. Other content types (for example `text/plain`) silently do nothing.
7. Redirect statuses are followed by `fetch` itself, so the browser would patch the redirected page body. Never answer an action with 301/302.

Retry quirk: a network failure (fetch throws) calls `retryRequest()` regardless of the `retry` option, up to `retryMaxCount` with backoff, even for `'never'`. A POST can therefore be re-sent. For mutating calls consider `retryMaxCount: 0` plus server-side idempotency (verify in spike).

Events on `document` (`datastar-fetch`, `evt.detail = {type, el, argsRaw}`): types `started`, `finished`, `error`, `retrying`, `retries-failed`, plus `datastar-patch-elements` and `datastar-patch-signals` as they are applied. Example: `data-on:datastar-fetch="evt.detail.type === 'error' && ($_net_error = evt.detail.argsRaw.status)"`. Every action in the page fires it, so compare `evt.detail.el` if you need one element.

`data-indicator:name` sets `name` true while a request started FROM THAT SAME ELEMENT is in flight (counter based, so overlapping requests are fine) and false at start.

Other actions: `@peek(() => $x)` reads without subscribing. `@setAll(value, {include, exclude})` sets all matching leaf signals. `@toggleAll({include, exclude})` flips booleans. Always pass a filter.

## E. How patch-elements is applied (client)

- Modes: `outer` (default, morph), `inner` (morph children), `replace` (replaceWith, no morph), `prepend`, `append`, `before`, `after`, `remove`. Invalid mode throws.
- No selector: allowed only for `outer`/`replace`. Each top-level element is matched to the page by `id` via `getElementById`; a missing id logs a warning and skips that element. `<html>`, `<head>`, `<body>` top-level elements patch the document ones. Every other mode requires a selector (error `PatchElementsExpectedSelector`).
- With a selector the target is `querySelectorAll`; a string payload is cloned into every match. No match logs a warning.
- HTML is parsed inside `<template>`, so `<tr>`, `<td>`, `<option>` work as top-level elements.
- Morph: matches by id, then by tag; keeps focus and input state of matched nodes; copies attributes; `value`, `checked`, `selected`, `disabled` are synced only when the attribute presence/value differs between old and new markup. Duplicate ids on the page break matching.
- A changed `data-*` attribute on an existing element re-applies that plugin. A new element with `data-signals` re-runs it and overwrites signals unless `__ifmissing`.
- `<script>` tags in patched content are executed once (new script elements only).
- `useViewTransition` and `viewTransitionSelector` use `document.startViewTransition` when the browser supports it.
- `namespace` `svg` or `mathml` wraps the payload so children get the right namespace.

## F. Signals engine facts

- Patch semantics are JSON Merge Patch: objects merge, `null` deletes the key, arrays replace wholesale, scalars replace. `onlyIfMissing:true` writes only keys that do not exist.
- Signal names are dotted paths into one tree. Declare parents as objects.
- Filtering for requests walks plain objects only; arrays and non-plain values (including DOM elements from `data-ref`) are leaves. Name refs with `_`.
- Computed signals are read-only and are part of the tree (sent unless `_`).

## G. SSE wire format (ADR.md) with PHP output

Event block, exact order: `event: <type>`, optional `id: <id>`, optional `retry: <ms>` (omitted when 1000), then one `data: <key> <value>` line per datum, then a blank line.

```
event: datastar-patch-elements
data: selector #toasts
data: mode append
data: elements <div class="toast">Saved</div>

event: datastar-patch-signals
data: onlyIfMissing true
data: signals {"brief":{"saved_at":"12:03"}}

```
Rules: each line of multi-line HTML or JSON becomes its own `data: elements ...` / `data: signals ...` line. Only non-default values are written (`mode` omitted for outer, `namespace` for html, `useViewTransition` only when true, `viewTransitionSelector` only when useViewTransition is true). The client splits each data line at the FIRST space into key and value. Lines must end in `\n`; a stray `\r` in HTML is treated as a line break by the SSE parser and corrupts the patch, so normalise templates to LF.

Required response headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive` (HTTP/1.1 only). The PHP SDK also sends `X-Accel-Buffering: no`.

ExecuteScript is sugar: a `datastar-patch-elements` with `selector body`, `mode append`, element `<script data-effect="el.remove()">...</script>` (the `data-effect` is dropped when autoRemove is false).

Request side: GET and DELETE carry `?datastar=<url-encoded json>`; POST, PUT, PATCH carry a JSON body. Same JSON shape both ways, nested by the dotted signal paths.

## H. PHP SDK (starfederation/datastar-php 1.0.1, PHP 8.1+, namespace `starfederation\datastar`)

| Member | Signature | Notes |
|---|---|---|
| `ServerSentEventGenerator::headers()` | `static(): array` | the four headers above |
| `ServerSentEventGenerator::readSignals()` | `static(): array` | GET/DELETE read `$_GET['datastar']` with no `isset` (warning if absent); others read `php://input`; `json_decode(..., true)`; non-array gives `[]` |
| `new ServerSentEventGenerator()` | | calls `ignore_user_abort(false)` |
| `sendHeaders()` | `(): void` | skips if `headers_sent()`; does not set the status code |
| `patchElements($elements, $options)` | `(string, array): string` | options: `selector`, `mode` (enum or string), `namespace`, `useViewTransition`, `viewTransitionSelector`, `eventId`, `retryDuration`; invalid mode or namespace throws `Exception` |
| `patchSignals($signals, $options)` | `(array\|string, array): string` | array goes through plain `json_encode` (no flags, a failure yields `''`); options `onlyIfMissing`, `eventId`, `retryDuration` |
| `removeElements($selector, $options)` | `(string, array): string` | mode remove; options `useViewTransition`, `viewTransitionSelector`, `eventId`, `retryDuration` |
| `executeScript($script, $options)` | `(string, array): string` | options `autoRemove` (default true), `attributes` (assoc array name => value, escaped), `eventId`, `retryDuration`; the script text is NOT escaped |
| `location($uri, $options)` | `(string, array): string` | emits `setTimeout(() => window.location = '$uri')` with `$uri` unescaped |

Enums: `enums\ElementPatchMode` (`Outer, Inner, Remove, Replace, Prepend, Append, Before, After`), `enums\NamespaceType` (`Html, Svg, MathML`), `enums\EventType` (`PatchElements, PatchSignals`).

Each call writes the event to output, does `ob_end_flush()` if a buffer is active (one level only), then `flush()`. `getMultiDataLines` applies `trim()` to the payload and splits on `\n`.

Gotchas:
- Empty PHP array encodes as `[]`, not `{}`. Send `(object)[]` for an empty object signal.
- `json_encode` of invalid UTF-8 fails; pass your own string encoded with `JSON_THROW_ON_ERROR` to `patchSignals`.
- `location()` is injectable. Use `executeScript('window.location = ' . json_encode($url, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP))`.
- Any output before `sendHeaders()` (notices, BOM, whitespace) corrupts the stream. Render templates with `ob_start()/ob_get_clean()` first.

## I. Not in the free bundle

Free bundle `datastar.js` (identical plugin list to `datastar-aliased.js`) registers exactly: attributes `attr bind class computed effect indicator init json-signals on on-intersect on-interval on-signal-patch ref show signals style text`; actions `get post put patch delete query peek setAll toggleAll`; watchers `datastar-patch-elements datastar-patch-signals`. `datastar-core.js` is the bare engine with no plugins. `datastar-rocket*.js` adds the Rocket web-component layer on top (separate bundle, also `data-scope-children`); do not load it.

`library/tsconfig.json` maps `@pro/*` to `./src/pro/*`, a directory that is not in the free source, confirming a separate Pro tree. Anything not in the lists above is therefore not available. From Datastar's public docs (could not be checked offline, so treat as a do-not-use list): attributes `data-animate`, `data-custom-validity`, `data-match-media`, `data-on-raf`, `data-on-resize`, `data-persist`, `data-query-string`, `data-replace-url`, `data-scroll-into-view`, `data-view-transition`; actions `@clipboard`, `@fit`, `@intl`; and the Rocket component system. Core already covers the common needs: `__viewtransition` modifiers, `useViewTransition` patch option, `localStorage` via plain JS in `data-on`, history via an `executeScript`.

## J. Verify-in-spike checklist

1. `responseOverrides` is typed but not wired (confirm by calling it once).
2. `text/javascript` response leaves `data-indicator` stuck true.
3. `retry:'never'` still retries network errors; does `retryMaxCount: 0` stop it cleanly.
4. `data-on-signal-patch` firing on initial `data-bind` signal creation (autosave loop risk).
5. Morph behaviour with a focused input inside a patched row.
6. `data-on:datastar-fetch` error handler wiring and `evt.detail.el` comparison.
7. Does Cloudflare or the host buffer/compress `text/event-stream`; do `PATCH/PUT/DELETE` reach PHP on this host.
8. In `transport=html`, Cloudflare Email Address Obfuscation may rewrite emails in `text/html` fragments.
9. `SERVER_PROTOCOL` behind Cloudflare and whether `Connection: keep-alive` is harmless.
10. `data-bind` number coercion for `type=number` and date inputs with our initial values.
11. `data-signals` inside patched markup overwriting user edits.
12. Whether a script in `<head>` module script with `data-cfasync="false"` is left alone by Rocket Loader on every page.

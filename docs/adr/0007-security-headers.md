# ADR 0007: Security headers and the Content Security Policy

Status: accepted (Phase 5, 2026-10-10)

## Context
The new app renders HTML on the server and uses Datastar 1.0.4 in the browser. Datastar compiles every `data-*` expression with `Function()`, so a Content Security Policy without `'unsafe-eval'` breaks the whole UI (Datastar's alternative, its `data-nonce` mode, needs a per-request nonce on every page and in every fragment that carries a script). The markup uses `style` attributes: `style="display: none"` on elements that `data-show` reveals (so nothing flashes before Datastar loads), `data-attr:style` in the checkbox, avatar colours and popover positions in the DatastarUI ports. (`data-show` itself writes through the CSSOM, which a CSP does not restrict.) The owner decided: keep `script-src 'self' 'unsafe-eval'`, no inline scripts, no CDNs.

Before this ADR the app sent `X-Content-Type-Options`, `X-Frame-Options` and `Referrer-Policy` from `Response::render()`, and no CSP. A Datastar `Redirect` was sent as a patched `<script>`, which a CSP without `'unsafe-inline'` blocks (checked in Chromium: "Refused to execute inline script").

## Decision
- All security headers come from one place, `app/Http/Middleware/SecurityHeaders.php`. It is the outermost middleware in `Kernel`, so pages, Datastar answers, redirects, JSON, maintenance and error pages all carry the set; `index.php` applies the same set to the error page it builds when the Kernel itself throws.
- The policy:

  ```
  default-src 'self'; script-src 'self' 'unsafe-eval';
  style-src 'self' 'unsafe-inline'; style-src-elem 'self'; style-src-attr 'unsafe-inline';
  img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none';
  frame-ancestors 'none'; base-uri 'self'; form-action 'self'
  ```

  plus `Referrer-Policy: strict-origin-when-cross-origin`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`, `Cross-Origin-Opener-Policy: same-origin`, and on live only `Strict-Transport-Security: max-age=63072000; includeSubDomains` (local runs on plain http).
- No inline scripts anywhere. `Redirect` is now a signal patch (`{"_redirect": "/slash301pm/..."}`, `application/json` in the html transport) and the body of both layouts watches it: `data-effect="$_redirect && $_redirect.startsWith('/') && !$_redirect.startsWith('//') && window.location.assign($_redirect)"`. Only same-origin paths are followed.
- Style attributes are allowed (`style-src-attr 'unsafe-inline'`), `<style>` elements are not (`style-src-elem 'self'`). The brief print page's `@page` rule moved from an inline `<style>` to `public/css/print.css`. `style-src` keeps `'unsafe-inline'` only as the fallback for browsers without the CSP Level 3 split directives (Chrome 75+, Firefox 108+ and Safari 15.4+ use the split ones).
- No fonts, images or scripts from other origins. If a web font is added later it must be self-hosted under `public/` (`font-src 'self'`).
- `tests/integration/http/hostile_pages_test.php` renders every GET route and fails on any inline `<script>`, `<style>` element, `on*=` handler or `javascript:`/`data:` URL, and checks the header set on every response. `tests/unit/http/security_headers_test.php` pins the policy.

## Consequences
- `'unsafe-eval'` means an attacker who can inject a `data-*` attribute can run script through Datastar. The defence is output escaping: every value in an attribute goes through `attr()`/`js()`, and the hostile-string test covers every page and fragment. `'unsafe-eval'` does not allow inline `<script>` or `on*=` handlers, so classic injected markup is still blocked.
- `style-src-attr 'unsafe-inline'` allows CSS in style attributes. CSS cannot run script in current browsers; the residual risk is UI redressing inside our own pages, which escaping also prevents.
- The legacy React app (`/legacy/`, `api/`) is served by Apache directly and gets none of these headers. It is retired area by area after the beta; adding a policy for it is out of scope.
- Cloudflare features that inject inline scripts (Rocket Loader on non-module scripts, Email Obfuscation, Web Analytics auto-injection) are blocked by this policy. Our scripts carry `data-cfasync="false"`; the owner should keep Email Obfuscation and automatic analytics injection off for `/slash301pm/`, or the console shows CSP violations (functionality is unaffected).

## Alternatives considered
- Datastar's `data-nonce` mode without `'unsafe-eval'`. Rejected by the owner: every page and every SSE fragment would have to carry a per-request nonce.
- `style-src 'self'` with every style attribute moved to classes. The initial `display: none` cannot become a class, because `data-show` only removes the inline property and a `hidden` class would keep the element hidden; avatar colours are per user. It would also mean diverging the UI kit from DatastarUI, which we keep as close to upstream as we can.

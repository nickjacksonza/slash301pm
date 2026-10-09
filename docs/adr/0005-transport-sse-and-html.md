# ADR 0005: Short SSE responses, with a plain HTML fallback

Status: accepted (Phase 0); confirmed or changed by the Phase 1 spike

## Context
Datastar normally answers with Server-Sent Events. The app runs on PHP-FPM behind Apache and Cloudflare (with Rocket Loader on). Long-lived streams hold a worker per viewer, and proxies or output buffering can hold events back until the response ends. The host may also block PUT, PATCH or DELETE.

## Decision
- Every response is short: request, events, close. Nothing streams long-lived on PHP-FPM.
- SSE responses send `X-Accel-Buffering: no`, no gzip, and flush output.
- A handler returns a `Response`: a full page, or a list of events (PatchElements, PatchSignals, Toast, Redirect). Only `Response::send()` calls the Datastar SDK, so the transport is one switch.
- `config transport=html` switches to Datastar's plain `text/html` responses (the fragment is patched by its id) if SSE misbehaves behind Cloudflare. Handlers do not change.
- Live refresh is a 60-second poll per section, never a held-open connection.
- If the host blocks PUT, PATCH or DELETE, those routes fall back to POST.
- A `/system/spike` page in Phase 1 tests every patch mode, signal patches, all HTTP methods, a 3-second slow response and the html transport, through Cloudflare with Rocket Loader on. The transport choice is recorded after that test. It is a gate for Phase 1.
- Every Datastar request carries an `X-CSRF-Token` header; the server also checks `Datastar-Request`, `Origin` and `Sec-Fetch-Site`.

## Consequences
- Polling gives up instant updates for predictable worker use and simple behaviour on shared hosting.
- The extra `text/html` path has to be kept working and tested, even if SSE wins the spike.
- A Go port can use `datastar-go` with long-lived streams later without changing handlers, because the Response abstraction is the only thing that knows about the wire format.
- `starfederation/datastar-php` 1.0.1 (PHP 8.1+) is vendored and committed; the server runs no Composer.

## Alternatives considered
- Held-open SSE streams for live updates. Rejected: one PHP-FPM worker per open tab on shared hosting, and Cloudflare may buffer or cut idle streams.
- WebSockets. Rejected: not available on the plan and not needed for a team of 5 to 20.
- Plain HTML only, no SSE. Kept as the fallback, but SSE is the default because one response can patch several elements and signals at once.

## Open questions for Phase 1
- Which Cloudflare setting, if any, buffers short SSE responses (Rocket Loader, compression, caching rules).
- Whether Xneelo's Apache passes PUT, PATCH and DELETE to PHP.

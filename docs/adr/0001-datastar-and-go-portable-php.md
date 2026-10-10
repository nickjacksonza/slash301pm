# ADR 0001: Datastar front end and Go-portable PHP

Status: accepted (Phase 0)

## Context
The legacy app is a React 18 single page app compiled in the browser by Babel. It keeps state in localStorage and syncs to the API without waiting for replies, so many writes are lost (see `docs/audit.md`, H7). The owner wants to rebuild the UI, keep the Airtable feel and the Kanban, build the Account Manager role first, and later port the backend to Go.

The host is Xneelo shared hosting: PHP only, SFTP deploys, no shell, SQLite 3.34, Cloudflare in front. The owner deploys by hand.

## Decision
1. The new front end is server-rendered HTML driven by Datastar 1.0.4 (MIT, free core features only). The server owns the state; the browser holds signals for UI state only.
2. The backend stays PHP 8.3 on Xneelo, written so that a Go port is mechanical:
   - `declare(strict_types=1)` everywhere, no magic (`__get`, DI container, globals).
   - One namespace per future Go package: `Http`, `Domain`, `Store`, `View`.
   - Data passes in `final readonly` DTOs (Go structs); string-backed enums become Go string constants.
   - `Domain` is pure: no I/O, "now" is passed in, validation returns errors.
   - SQL lives only in `Store/`, with prepared statements and `BEGIN IMMEDIATE` for writes. IDs are generated in PHP.
   - Handlers have the shape `fn(Request, Deps): Response`. Only `Response::send()` talks to the Datastar SDK.
   - Routes use Go 1.22 `ServeMux` patterns.
   - Templates are functions of a view model; they never touch the database or session.
3. Styling is Tailwind v4 plus ports of DatastarUI components. The one build step is the Tailwind CLI; its output `public/css/app.css` is committed so the server runs no build.
4. User data reaches a `data-*` expression only through `js()` (JSON with the HEX flags) and text only through `e()`. Tailwind class strings are always written out in full.
5. The legacy React app moves to `/legacy/` and keeps running until each area reaches parity. Its API endpoints in `api/` receive hotfixes only.

## Consequences
- Fewer moving parts in the browser, and writes are confirmed by the server before the screen changes.
- The portability rules cost some convenience in PHP (no container, no static helpers with hidden state), and they are enforced by review and tests, not by the language.
- DatastarUI is templ only, with no table, badge or combobox, so about 15 components are ported by hand and the source commit is recorded in each file.
- Datastar Pro-only attributes are avoided; small vanilla JS handles URL state and grid keys.
- Two apps share one database during the transition, which drives ADR 0002 and ADR 0004.
- Cloudflare Rocket Loader can break module scripts, so the Datastar script tag carries `data-cfasync="false"`.

## Alternatives considered
- Keep React and fix the sync layer. Rejected: the fix is a rewrite of the data layer, and the browser-side Babel build is slow and hard to test.
- Move to Go now. Rejected: the owner deploys by SFTP to a PHP host; a Go binary needs a different hosting plan. The rules above keep that option open.
- htmx or Alpine. Rejected: Datastar's signals and server-patched DOM fit the grid and Kanban interactions with the least client code, and a Go SDK exists for the later port.

---
name: datastarui-port
description: Load when porting or modifying a DatastarUI-derived component in app/View/ui/ (button, input, dialog, toast, select, tabs, table, badge and so on), or when setting up the Tailwind v4 theme for them. Covers rendering the real Go reference HTML, writing the PHP partial, and the fixture test.
---

# Porting a DatastarUI component to PHP

Goal: every partial in `app/View/ui/` emits the same markup, class strings, `data-*` attributes and signal names as the
real DatastarUI Go/templ component, so the later Go port can swap the templ components back in with no front end change.
"Identical" is checked by a test against HTML produced by the real Go component, not by eye.

## Pinned source

- Repo `github.com/coreycole/datastarui` (MIT), commit `feb9af0c58ade31f8fefc1443b7e7e15ad413242` (2026-09-12).
- Component path pattern: `components/<name>/<name>.templ` plus `args.go` (props), `variants.go` (classes), `expressions.go` (JS strings).
- Every partial header must say which commit and path it was ported from (see template below). When the pin moves, run
  `go get github.com/coreycole/datastarui@<hash>` in `render/` (see its header), regenerate fixtures, fix failing tests.
- `inventory.md` (this folder): every component, its args, variants, signals, JS needs, Pro status. Read the entry first.
- `missing.md`: table, badge, skeleton, combobox (not in DatastarUI) with shadcn class strings and recipes.
- Only `infinitescroll` needs Datastar Pro (`data-on:intersect`). Do not port it. Everything else runs on the free bundle.

## Tooling in this folder

- `render/` Go program that renders the real components. Needs Go 1.24+ and network on first run (module download).
  Run from the repo root:
  ```
  go run -C .claude/skills/datastarui-port/render . --list
  go run -C .claude/skills/datastarui-port/render . button --variant=destructive --size=lg --text=Delete
  go run -C .claude/skills/datastarui-port/render . input --type=email --name=email --id=email --required
  go run -C .claude/skills/datastarui-port/render . toast.item --id=saved --title=Saved --variant=success
  go run -C .claude/skills/datastarui-port/render . button --variant=outline --classes     # decoded class strings to paste
  go run -C .claude/skills/datastarui-port/render . --cases=cases/dialog.json --out="$PWD/tests/fixtures/ui"
  ```
  Flags are args-struct field names (case and hyphens ignored). `--json=@file` passes a whole args object, `--attr.data-x=v`
  adds to `Attributes`. Nested components (dialog with header, title, footer) use a case file: copy `render/cases/dialog.json`.
  Case files hold named nodes; `"matrix": {"variant": [...], "size": [...]}` expands to one fixture per combination.
  A registry entry is one line (`"name": reg(pkg.Templ)`), so adding calendar, datepicker or sidebar is a small edit in
  `render/main.go`. Components using `crypto/rand` ids need an explicit `--id`.
- `normalize.php`: `ui_normalize_html()` and `ui_fixture_diff($html, $fixturePath)`. Twin of `render/normalize.go`. It sorts
  attributes, class tokens (deduplicated) and `data-class` entries, drops comments, collapses whitespace. Needed because the Go
  output has random class order (tailwind-merge) and random `data-class` entry order (Go map), so raw output never repeats.
  Copy it to `tests/unit/ui/_normalize.php` once (if absent) and `require_once` it from each test.
  Keep the two normalisers in sync; if you change one, change the other and rerun a few fixtures through both.

## Procedure (one component)

1. **Read the inventory entry** and the source `.templ`, `args.go`, `variants.go`, `expressions.go`. Note signals, the
   variants, which sub-components exist (dialog is 9 functions), and anything marked "not in renderer".
2. **Render the reference.** Write `render/cases/<name>.json` (copy an existing one) covering every variant and size, disabled,
   asChild, extra `class`, extra `attributes`, open and closed states. Generate fixtures into `tests/fixtures/ui/`:
   `go run -C .claude/skills/datastarui-port/render . --cases=cases/<name>.json --out="$PWD/tests/fixtures/ui"`.
   Fixtures are named `<component>.<case>.html` (matrix: `button.destructive.lg.html`). Commit them; they are the contract.
3. **Create `app/View/ui/<name>.php`** with `declare(strict_types=1);`, a final readonly props class and one function:
   ```php
   <?php
   declare(strict_types=1);

   /**
    * Ported from DatastarUI components/button/button.templ (+ variants.go)
    * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
    * Keep markup, classes and signal names identical to the source. Fixtures: tests/fixtures/ui/button.*.html
    */
   final readonly class ButtonProps
   {
       /** @param array<string,string|true> $attrs extra attributes, rendered last (templ's Attributes) */
       public function __construct(
           public string $variant = 'default',
           public string $size = 'default',
           public bool $disabled = false,
           public string $type = 'button',
           public string $class = '',
           public array $attrs = [],
       ) {}
   }

   function ui_button(ButtonProps $p, string $children = ''): string { /* ... */ }
   ```
   One props class and one `ui_<name>()` per templ function (`ui_dialog`, `ui_dialog_trigger`, `ui_dialog_title` ...), file per
   component. Props mirror the Go args struct field for field (same meaning, camelCase), plus `string $children` as second
   parameter where the templ uses `{ children... }`. `$children` is already escaped HTML built by the caller.
4. **Copy classes as full literal strings.** Tailwind only sees complete tokens. Use `match`/array maps of whole strings per
   variant; never build `bg-` . $color. The post-edit hook flags concatenation. Take the strings from the fixture or from
   `--classes`, not from `variants.go`: Go output is post-`TwMerge`, so overridden tokens are gone (destructive loses
   `focus-visible:ring-ring/50`, size `sm` loses `gap-2`, duplicate `rounded-md` collapses). PHP has no merge, so split the base
   string so no variant string conflicts with it. Extra `$p->class` is appended as is; do not pass conflicting utilities
   (the Go side would merge them, PHP cannot). Order of tokens does not matter to the test or to Tailwind.
5. **Keep every attribute and signal name.** Attribute names, `data-slot`, `role`, `aria-*`, `data-on:click` colon syntax,
   `data-show`, `data-bind`, `data-class` and the exact expression text. Signal root is the id with `-` replaced by `_`
   (`confirm-delete` gives `$confirm_delete.open`, DOM id stays `confirm-delete`). Signal shape and field order come from the
   Go `*Signals` struct (see inventory); emit `data-signals` with
   `json_encode([$sid => ['open' => $open]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)`.
   Do not rename, merge or "improve" signals, even where the Go source looks odd (toast `data-state` holds expression text,
   `data-on:mount` is inert, form `data-indicator-fetching` uses hyphen syntax). Note oddities in a comment, fix them upstream
   later in both ports together.
6. **Escape on the way out.** `e()` for text nodes, `attr()` for every attribute value, `js()` when user or database data is
   placed inside a Datastar expression (it returns a JS literal, so wrap the whole expression in `attr()` afterwards:
   `data-on:click="<?= attr('$x.value = ' . js($v)) ?>"`). The expression text from `expressions.go` is code and is not
   escaped, only the interpolated data is. Where Go uses a single-quoted JS string (`'confirmed'`), mirror it with a
   `js_sq()` helper if the app has one, otherwise ask; do not hand-escape quotes inline. Optional attributes are omitted when
   empty, exactly as the templ does (`input` skips empty `placeholder`, `value`, `name`, `id`). Boolean attributes print bare.
7. **Write the test** `tests/unit/ui/<name>_test.php`: for each fixture, build the same props, render, and assert
   `ui_fixture_diff($html, $fixturePath) === null`, printing the returned message on failure. Use whatever assertion helper
   `tests/run.php` provides. Include one case per variant, size, state and the extra class/attributes case.
8. **Run** `php -l` on the new files, then the test (`php tests/run.php tests/unit/ui/<name>_test.php`, or the repo's
   current runner). Report the commands and results. Do not mark done while any fixture differs.
9. **Use it**: pages call `ui_button(new ButtonProps(variant: 'outline'), e($label))`. Never copy-paste component markup
   into a page; if a page needs a variation, add a prop to the partial and a fixture.

## Gotchas seen while building the renderer

- Go output is not repeatable: class order, `data-class` entry order. Compare normalised, never raw.
- Same page, two components with the same id share signals. Ids must be unique per page (dialogs, selects, tabs).
- `themetoggle` needs the global `$theme` signal on `<body data-signals="{theme: initTheme(), ...}">` and the `initTheme()` head
  script (see `layouts/root.templ` in the source repo). It is the only component that uses a non-namespaced signal.
- `popover`, `tooltip`, `select`, `dropdown` rely on the native Popover API and CSS anchor positioning. Copy the `[popover]` and
  `.anchor-positioned` CSS (below) and load the two polyfills from the demo layout if older browsers matter.
- `dialog` / `sheet` markup has `style="display: none;"` until open; `data-show` toggles it.
- `form` uses `@post(...)`; our handlers return Datastar SSE events, so check the response contract before porting.
- Components with children (card, dialog) take pre-rendered `string $children`; build inner pieces first.

## Tailwind v4 theme setup for `styles/app.css`

DatastarUI uses shadcn tokens stored as bare HSL triplets (`--background: 0 0% 100%`) wrapped by `hsl(var(--x))` in `@theme`.
Copy from the source repo (all paths relative to its root, commit above):

1. `static/css/theme.css` whole file: the `@theme { --color-background: hsl(var(--background)); ... }` block (includes the
   sidebar tokens), then `@layer base` with `:root` light tokens, `.dark` dark tokens, `* { border-color: ... }`, body colours,
   scrollbars, input colours. Our `app.css` should contain this verbatim, then our brand overrides after it (override the HSL
   triplets, not the `@theme` mapping, so the class names keep working).
2. `tailwind/utilities.css` (the `animate-in` / `animate-out` utilities and `enter` / `exit` keyframes).
3. `static/css/index.css` lines 298 to 1160: the tailwindcss-animate port that defines `fade-in-0`, `fade-out-0`, `zoom-in-95`,
   `zoom-out-95`, `slide-in-from-*`, `slide-out-to-*`, `duration-*`, `delay-*` (dialog, sheet, toast, select use them). These sit
   inside `@layer utilities { ... }`; wrap the copied block the same way. Skip lines 1 to 55 unless porting sidebar (they define
   `.bg-sidebar*`, `hover:bg-sidebar-accent` helpers). Do not use `tw-animate-css` unless the build can resolve npm packages.
4. `static/css/index.css` lines 57 to 148 (inside `@layer base`) only when porting popover, tooltip, dropdown, select or datepicker: `@position-fallback`,
   `.anchor-positioned`, and the `[popover]` open/close transition rules.
5. **Dark mode: add this line, DatastarUI does not have it:** `@custom-variant dark (&:where(.dark, .dark *));`. The source
   `tailwind.config.js` says `darkMode: "class"` but Tailwind v4 ignores that file, so its `dark:` utilities actually follow the
   OS setting while the `.dark` class only flips the CSS variables. With the custom variant, toggling `.dark` on `<html>` drives both.
6. Content scanning: `@source "../app/View";` (and any template dirs) so literal class strings in PHP partials are found.
   Top of file: `@import "tailwindcss";`. Run the app's Tailwind build after adding classes; unscanned classes silently vanish.
7. Head script before first paint (from the source layout): read `localStorage.theme` or `prefers-color-scheme`, then
   `document.documentElement.classList.toggle('dark', theme === 'dark')`. Body gets `data-signals="{theme: ...}"` if
   themetoggle is used. Wrap storage access in try/catch.

## Building components DatastarUI lacks

See `missing.md`. Short version: table, badge, skeleton are static markup, take the shadcn new-york-v4 class strings given there,
keep `data-slot`, write the fixture by hand and say so in its first line. Combobox is `select` plus a search input and a
`query` signal, reusing select's reference output as the base. Use the same Props/function/test procedure, with
`Source: shadcn/ui new-york-v4 <name>.tsx` in the header. Do not invent new visual styles, use only existing tokens
(`bg-accent`, `text-muted-foreground`, `border-input`, ...).

## Definition of done

- `app/View/ui/<name>.php` with header comment (commit hash, source path), final readonly props, `ui_<name>()` functions.
- Fixtures in `tests/fixtures/ui/` generated from the Go renderer (or hand written for missing components) and passing tests in
  `tests/unit/ui/<name>_test.php` for every variant and state.
- Class strings are literal, signal names unchanged, all dynamic data passed through `e()`, `attr()` or `js()`.
- `php -l` clean. Final report lists files, commands run and results, and every deliberate deviation from the Go source.

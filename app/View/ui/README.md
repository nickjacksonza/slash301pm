# app/View/ui

PHP ports of DatastarUI (Go + templ) components plus badge, skeleton, table, combobox and native select (shadcn new-york-v4).
Pinned source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242. Process and rules: `.claude/skills/datastarui-port`.

## Layout
- `<name>.php` global functions `ui_<name>(<Name>Props $p, string $children = ''): string`. Load with `require_once app/View/ui/_all.php` (autoload.php only autoloads classes).
- Props classes live in namespace `App\View\ui` as `app/View/ui/<Name>Props.php` (one class per file, autoloaded). Final readonly, camelCase fields mirroring the Go args.
- `PartProps(class, attrs)` is the shared props type for stateless parts (card parts, dialog header/footer/title/description/content, sheet header..., tabs list, table body/cell/head/footer/caption, dropdown separator/shortcut/group).
- `_support.php` helpers: `ui_attrs`, `ui_id`, `ui_tok`, `ui_sig`, `ui_js_raw`, `ui_js_sq`, `ui_signals_attr`.
- `$children` is already escaped HTML (use `e()` for text). `attrs` is `array<string,string|int|bool>`: keys must match `/^[a-z0-9:_.\-]+$/` and not start with `on`, values are escaped, `true` prints a bare attribute.
- Ids (`id`, `dialogId`, `sheetId`, tab `value`) go inside JS strings and signal paths, so they are validated (`InvalidArgumentException`). Signal root = id with `-` replaced by `_`.
- Class strings are literals. PHP has no tailwind-merge: do not pass a class that conflicts with a base utility (exceptions handled: dialog `max-w-*`, avatar `size-*`).

## Rebuild CSS and gallery
`bash tools/build-css.sh` then `php tools/ui-gallery.php` (writes tests/fixtures/ui/gallery.html). Tests: `php tests/run.php --unit`.
Fixtures from Go: `go run -C .claude/skills/datastarui-port/render . --cases=$PWD/tests/fixtures/ui/cases/<x>.json --out=$PWD/tests/fixtures/ui`.

## Patterns for pages
- Toast region once in the layout: `ui_toast_region(new ToastRegionProps(id: 'toasts', class: 'w-96 max-w-[100vw]'))`; a Toast event appends `ui_toast(new ToastProps(id: <unique>, title: .., kind: 'ok'|'warn'|'error'|'info', open: true, durationMs: 4000))` to `#toasts`.
- Sheet: render `ui_sheet(new SheetProps(id: 'sheet', modal: true))` once; patch inner HTML into `#sheet` (mode inner) and send signal patch `{"sheet":{"open":true}}`. Close with `ui_sheet_close`.
- Dark mode: toggle class `dark` on `<html>`.

## Deviations from upstream
- Dialog Attributes (ignored upstream) land on the id element. DialogOverlay, DropdownMenuFormItem/CustomItem, SelectTrigger/Content/Item manual composition, popover, breadcrumb, calendar, datepicker, sidebar, form, themetoggle are not ported.
- Toast: added `warning` variant, `open` and `durationMs` (auto-dismiss with data-init + setTimeout). Avatar: `name` derives initials and colour.
- CSS: `@theme inline` (needed for `.dark` to reach utilities), obsolete `@position-fallback` replaced by `position-try-fallbacks`, dark `text-destructive` uses `--destructive-text`.
- Upstream quirks kept: inert `data-on:mount`, toast `data-state` holds expression text, select hidden input `data-bind="$id.value"`.

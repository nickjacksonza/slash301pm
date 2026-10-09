# Components DatastarUI does not have

There is no Go reference for these, so the fixture is hand written. Use shadcn/ui "new-york-v4" markup and class strings
(snapshot below taken from `apps/v4/registry/new-york-v4/ui/*.tsx` on shadcn-ui/ui main, 2026-10-09; re-fetch with
`curl -s https://raw.githubusercontent.com/shadcn-ui/ui/main/apps/v4/registry/new-york-v4/ui/<name>.tsx`).
DatastarUI copies an older v4 snapshot, so small drift is expected (this badge is a pill, the old one was `rounded-md`).

Procedure for each: write `app/View/ui/<name>.php` like any port, header comment
`Source: shadcn/ui new-york-v4 <name>.tsx (no DatastarUI equivalent)`, then hand write
`tests/fixtures/ui/<name>.<case>.html` from the strings below (one element per line is fine, the normaliser reformats it).
Keep `data-slot="<name>"` attributes, they are how a later Go templ port and Tailwind selectors line up.
Never invent a Go "reference"; say in the fixture's first line comment that it is hand written.

## badge
`<span data-slot="badge" data-variant="{variant}" class="{base} {variant}">`. Use `<a>` instead of `<span>` when it has an href
(the `[a&]:hover:` classes only fire on anchors).
- base: `inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full border border-transparent px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none [&>svg]:size-3`
- default: `bg-primary text-primary-foreground [a&]:hover:bg-primary/90`
- secondary: `bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90`
- destructive: `bg-destructive text-white focus-visible:ring-destructive/20 dark:bg-destructive/60 dark:focus-visible:ring-destructive/40 [a&]:hover:bg-destructive/90`
- outline: `border-border text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground` (note: `border-border` overrides `border-transparent`, so drop `border-transparent` from this variant's string, as tailwind-merge would)
- ghost: `[a&]:hover:bg-accent [a&]:hover:text-accent-foreground`
- link: `text-primary underline-offset-4 [a&]:hover:underline`
For task status chips map status to a variant in PHP (a `match` returning full literal strings), never build `bg-{color}` strings.

## skeleton
`<div data-slot="skeleton" class="animate-pulse rounded-md bg-accent {extra}"></div>` Size comes from the caller's class (`h-4 w-[250px]`).

## table
All parts are plain elements with `data-slot`. Props: only `class` (extra classes, appended) plus content strings already escaped.
- wrapper: `<div data-slot="table-container" class="relative w-full overflow-x-auto">` around
  `<table data-slot="table" class="w-full caption-bottom text-sm">`
- thead `data-slot="table-header"`: `[&_tr]:border-b`
- tbody `data-slot="table-body"`: `[&_tr:last-child]:border-0`
- tfoot `data-slot="table-footer"`: `border-t bg-muted/50 font-medium [&>tr]:last:border-b-0`
- tr `data-slot="table-row"`: `border-b transition-colors hover:bg-muted/50 has-aria-expanded:bg-muted/50 data-[state=selected]:bg-muted`
- th `data-slot="table-head"`: `h-10 px-2 text-left align-middle font-medium whitespace-nowrap text-foreground [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]`
- td `data-slot="table-cell"`: `p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]`
- caption `data-slot="table-caption"`: `mt-4 text-sm text-muted-foreground`
PHP shape: a `TableProps` for the whole table is awkward; instead write one function per part (`ui_table`, `ui_table_row`,
`ui_table_cell`...) each taking a small Props class and an already built `string $children`. Rows with row click or selection
use `data-state="selected"` driven by a signal via `data-attr:data-state`.

## combobox (searchable select)
shadcn builds it from Popover + Command. We build it from DatastarUI's `select` (render it first: `render select --id=x --json=@opts.json`)
and add a search input, so keyboard, `data-select-id` handling and the check icon are copied rather than invented.
1. Signals `{<id>: {open: false, value: "", label: "", query: "", highlighted: -1}}` (select's shape plus `query`).
   Name the root `data-combobox-id` instead of `data-select-id` and update every `querySelector` in the copied handlers.
2. Trigger: the select trigger markup with button variant outline classes, `role="combobox"`, `justify-between`, `aria-expanded` via
   `data-attr:aria-expanded`.
3. Content panel: select content classes plus `p-0`. First child is the shadcn `command-input-wrapper`:
   `<div data-slot="command-input-wrapper" class="flex h-9 items-center gap-2 border-b px-3">` + search svg
   (`size-4 shrink-0 opacity-50`) + `<input data-slot="command-input" data-bind="<id>.query" placeholder="Search..." class="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50">`.
4. List `data-slot="command-list"`: `max-h-[300px] scroll-py-1 overflow-x-hidden overflow-y-auto`.
   Item `data-slot="command-item"`: `relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground`.
   Empty `data-slot="command-empty"`: `py-6 text-center text-sm`. Group wrapper `overflow-hidden p-1 text-foreground`.
5. Filtering is client side and cheap: every item gets
   `data-show="<?= attr(js($label)) ?>.toLowerCase().includes($<id>.query.toLowerCase())"`; the empty row gets
   `data-show="!<?= attr(js($allLabels)) ?>.some(l => l.toLowerCase().includes($<id>.query.toLowerCase()))"`.
   Reset `query` to `''` when closing (add to the open/close handlers). Over about 200 options switch to a server search:
   `data-on:input__debounce.250ms="@get('/ui/combobox/<id>?q=' + encodeURIComponent($<id>.query))"` returning item patches.
6. Selecting sets `value` and `label`, closes, and writes the value into a hidden `<input name=... data-bind="<id>.value">`
   so plain form posts still work.
Fixture: hand written; compare structure and signal names, not the (absent) Go output.

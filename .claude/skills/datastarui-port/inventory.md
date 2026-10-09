# DatastarUI inventory

Source: github.com/coreycole/datastarui (MIT), commit `feb9af0c58ade31f8fefc1443b7e7e15ad413242` (2026-09-12),
Go 1.24 + templ v0.3.977 + Tailwind v4, server side datastar-go v1.2.2. Component dir: `components/<name>/`
(`*.templ`, generated `*_templ.go`, `args.go`, `variants.go`, sometimes `expressions.go`).
Regenerate the facts below with the renderer (`--list`, `--classes`) and by reading `args.go`.

## Rules that apply to every component

- Signals are namespaced: `utils.Signals(id, struct)` emits `data-signals='{"<id>":{...}}'` where `<id>` is the id with
  `-` replaced by `_`. Expressions read them as `$<id>.<field>`. An element `id="confirm-delete"` therefore pairs with
  signal root `confirm_delete`. The DOM id keeps its hyphens.
- Attribute syntax is Datastar v1 with a colon: `data-on:click`, `data-on:keydown__window`, `data-on:click__outside`,
  `data-attr:aria-expanded`. Do not "fix" to hyphen syntax.
- Every class string goes through `utils.TwMerge` (tailwind-merge-go). Output is post-merge: conflicting tokens are
  dropped (button `destructive` loses base `focus-visible:ring-ring/50`, size `sm` loses base `gap-2`) and token order is
  random between runs. PHP has no merge, so write the merged result as literals and compare normalised HTML.
- `data-class` object entries come from a Go map, so their order is random. The normaliser sorts them.
- Ids generated with `crypto/rand` when `ID` is empty: calendar, dateinput, datepicker, dropdown, select, tabs.
  Always pass an explicit id in references and in PHP.
- Free Datastar covers everything below except `infinitescroll`. No Pro attribute (`data-persist`, `data-animate`,
  `data-query-string`, `data-custom-validity`, `data-scroll-into-view`, `data-view-transition`, `@clipboard`, `@fit`,
  `@intl`) appears in `components/` or `utils/`. `infinitescroll` uses `data-on:intersect`, which is Pro in Datastar v1.
  The demo layout tries `/js/datastar-pro-v1.js` and falls back to the free v1.0.1 bundle.
- Every component accepts `Class` (or `ClassName` for checkbox) and `Attributes templ.Attributes` (arbitrary attributes,
  rendered after the component's own).
- Browser features relied on by several components: Popover API (`popover="auto"`, `showPopover()`), CSS anchor
  positioning (`anchor-name`, `position-anchor`; the demo layout loads polyfills), `data-on:mount="evt.target.focus()"`
  in dialog and sheet (not a standard DOM event, so inert unless something dispatches `mount`; copy verbatim).

## Components

Sub-component names are the templ function names. `[reg]` marks names the renderer knows (`render --list`).

### avatar [reg]
Avatar{Class, Attributes, BackgroundColor, TextColor}. Inline style only when BackgroundColor set. Signals none. JS none.

### breadcrumb [reg]
Breadcrumb, BreadcrumbList, BreadcrumbItem, BreadcrumbLink{AsChild, Href}, BreadcrumbPage, BreadcrumbSeparator{CustomIcon},
BreadcrumbEllipsis, FromItems([]BreadcrumbItemData) (templ helper, not in renderer). Each has Class, Attributes. Signals none.

### button [reg]
Button{Variant, Size, AsChild, Class, Attributes, Disabled, Type}; LinkButton{Href, Variant, Size, Target, Rel, Class, Attributes}.
Variants: default, destructive, outline, secondary, ghost, link. Sizes: default, sm, lg, icon.
AsChild renders a `<span>` (no attribute transfer) with `aria-disabled` when disabled. Default `type="button"`.
Signals none. Render matrix: `cases/button.json`.

### calendar (not in renderer)
Calendar{ID, Mode, NumberOfMonths, HideOutsideDays, DefaultDate, SelectedDate, RangeStart, RangeEnd, Disabled string,
MinDate, MaxDate, DatePickerInputsID, Class, Attributes}; CalendarHeader, CalendarGrid, CalendarDay (internal).
Signals `{currentDate, mode, today, dateValue, inputValue, rangeStart, rangeEnd}` (all strings, ISO dates).
JS: `data-text` with inline `Date`/`toLocaleString` expressions, `data-class` for selection, custom DOM events
`date-select` and `month-change` (`data-on:date-select`, `data-on:month-change`).

### card [reg]
Card, CardHeader, CardTitle, CardDescription, CardAction, CardContent, CardFooter. Class, Attributes only. Signals none.

### checkbox [reg]
Checkbox{ID, Name, Value, Checked, Disabled, Required, ClassName, AriaLabel, AriaLabelledBy, AriaDescribedBy, AriaInvalid,
Attributes}; CheckboxIndicator{ClassName, Attributes}. Signals `{<id>: {checked: bool, disabled: bool}}`.
Renders a `<button role="checkbox">` plus an `sr-only` `<input type="checkbox">`, driven by `data-attr:*`, `data-class`, `data-on:click` toggle.

### dateinput (not in renderer)
DateInput{ID, Name, Class, Placeholder, Disabled, Required, MinDate, MaxDate, Postfix templ.Component, Attributes,
CalendarID, Value, Mode, StartValue, EndValue, StartName, EndName, StartPlaceholder, EndPlaceholder, EndDateOptional,
Separator, Orientation}. Signals `{inputValue, dateValue, startInputValue, startDateValue, endInputValue, endDateValue}`.
JS: `data-bind`, `data-on:input__debounce_*`, `data-on:blur`, `document.getElementById(..).focus()`.

### datepicker (not in renderer)
DatePicker{ID, Name, Mode, Placeholder, DefaultDate, SelectedDate, RangeStart, RangeEnd, NumberOfMonths, HideOutsideDays,
DisabledDates, MinDate, MaxDate, Required, Disabled, OpenOnFocus, DisablePopoverOpenOnFocus, PopoverPosition, Class,
InputClass, CalendarClass, Attributes}; DatePickerPopover. Composes dateinput + calendar + popover.
Signals `{open, inputValue, rangeStart, rangeEnd, displayMonth, isValid, errorMessage, isTyping, focusedDate, highlightedDate, ...}`.
JS: popover API, `document.querySelector('[data-datepicker-id=...]')`, `data-on:click__outside`.

### dialog [reg]
Dialog{ID, DefaultOpen, Class, Attributes}; DialogTrigger{DialogID, AsChild, Class, Attributes}; DialogContent, DialogOverlay{ID},
DialogHeader, DialogFooter, DialogTitle, DialogDescription (Class, Attributes); DialogClose{DialogID, ReturnValue, Variant, Class, Attributes}.
Close variants: default, destructive, outline, secondary, ghost, link (own class set, not the Button one).
Signals `{<id>: {open: bool}}`; Close with ReturnValue also sets `$<id>.returnValue = '<v>'` (undeclared signal).
JS: `data-show`, `data-on:click` (backdrop, `evt.target === evt.currentTarget`), `data-on:keydown__window` (Escape),
`style="display: none;"` when not DefaultOpen. Render set: `cases/dialog.json`.

### dropdown [reg, partial]
DropdownMenu{ID, Open, DefaultOpen}; DropdownMenuTrigger{ID, AsChild, Disabled}; DropdownMenuContent{ID, Align, Side, SideOffset};
DropdownMenuItem{ID, Inset, Variant (default|destructive), Disabled, AsChild, OnClick}; DropdownMenuLinkItem{Href, Target, Rel, ...};
DropdownMenuFormItem{Action, Method, ButtonAttributes}; DropdownMenuCustomItem{CloseOnClick}; Label{Inset}; Separator; Shortcut; Group.
Signals `{<id>: {open: bool}}`. JS: `data-on:click__outside`, trigger click handler measures the trigger with
`getBoundingClientRect()` and sets CSS vars `--dui-dropdown-trigger-*` on the content element.

### form [reg, partial]
Form{ID, Action, ContentType (form|json), FormDataFields []string, Class, Attributes}; FormItem; FormLabel{For, HasError};
FormControl{ID, AriaDescribedBy, AriaInvalid}; FormDescription{ID}; FormMessage{ID, Message}.
Form emits `data-on:submit="@post('<action>', {contentType: '...'})"` and `data-indicator-fetching` (hyphen form, likely
inert in v1). With FormDataFields it adds `filterSignals: {include: /^(formId|<id>\.)/}`. Inputs bind to `<formId>.<name>`.
`form.SignalsWithFormId` puts `formId` at the signal root next to `<id>`.

### infinitescroll (not in renderer, needs Datastar Pro)
InfiniteScroll{ID, PatchAboveExpr, PatchBelowExpr, HostID, ItemsID, SentinelAboveID, SentinelBelowID, LoadingAboveID,
LoadingBelowID}; Host, Items, Sentinel{Direction above|below, PatchExpr}, Loading, LoadingSentinel. Signals none (SSE patches).
JS: `data-on:intersect` (Pro). Skip unless we buy Pro; use a "Load more" button with `@get` instead.

### input [reg]
Input{Type, Class, Placeholder, Value, Name, ID, FormID, Disabled, Required, Attributes}. `data-slot="input"`.
With FormID and Name: `data-bind="<form_id>.<name>"` (hyphens in FormID become underscores). Empty fields are omitted.
Attribute order is irrelevant to the normaliser. Signals created implicitly by `data-bind`. Render set: `cases/input.json`.

### label [reg]
Label{Class, For, Attributes}. Signals none.

### popover [reg]
PopoverTrigger{ID, PopoverID}; PopoverContent{ID, UseAnchor, Side, Align, SideOffset}. Native Popover API:
trigger click runs `document.getElementById('<id>').togglePopover()`; content has `popover="auto"` and inline
`anchor-name` / `position-anchor` styles. Signals none. Needs the `[popover]` and `.anchor-positioned` CSS (see SKILL.md).

### select (package `selectcomponent`) [reg]
Select{ID, Open, DefaultOpen, Value, DefaultValue, Options []SelectOptionArgs{Value, Label, Disabled, Group}, Name, Disabled,
Required, Placeholder, OnChange, Class, Attributes}; SelectTrigger, SelectValue, SelectContent{Position}, SelectItem{Value,
Index, Disabled}, SelectLabel, SelectSeparator, SelectGroup.
Signals `{<id>: {open: bool, value: string, label: string, highlighted: int (-1 none), _lastValue: string}}`.
JS: `data-bind`, `data-effect`, `data-text`, `data-class`, `data-attr:aria-expanded`, `data-on:click__outside`,
`data-on:keydown__window` (arrows, Enter, Escape, Tab), `document.querySelector('[data-select-id="<id>"]')`.

### sheet [reg]
Sheet{ID, DefaultOpen, Modal, Side (top|right|bottom|left), Class, Attributes}; SheetTrigger{SheetID, AsChild};
SheetContent{SheetID}; SheetHeader; SheetFooter; SheetTitle; SheetDescription; SheetClose{SheetID, ReturnValue, AsChild}.
Signals `{<id>: {open: bool, modal: bool, returnValue: null}}`. JS like dialog plus `data-class` open/closed transforms.

### sidebar (not in renderer)
SidebarMobile{ID, DefaultOpen}, SidebarDesktop, SidebarTrigger, SidebarCloseButton, SidebarContent, SidebarHeader,
SidebarFooter, SidebarNavMobile/Desktop{SidebarID, Sections []SidebarSection{Title, Label, Items []SidebarItem{Title, Href, Label}},
CurrentPath}, SidebarNavLinkMobile/Desktop, HamburgerIcon. Signals `{<id>: {mobileOpen: bool}}` (the demo layout puts it
in the body `data-signals` next to `theme`). JS: `data-class`, `data-on:click`. Custom CSS: `.bg-sidebar-*` utilities in index.css.

### tabs [reg]
Tabs{ID, DefaultValue, Value}; TabsList; TabsTrigger{ID, Value, Disabled}; TabsContent{ID, Value}. Trigger and content
take the tabs id. Signals `{<id>: {active: string}}`. JS: `data-class` (active styles), `data-attr:data-state`,
`data-attr:aria-selected`, `data-attr:tabindex`, `data-attr:aria-hidden`, `data-show`.

### textarea [reg]
Textarea{Class, Placeholder, Value, Name, ID, FormID, Rows (default 4), Disabled, Required, Borderless, Attributes}. Same
`data-bind` rule as input.

### themetoggle [reg]
ThemeToggle{Class, Attributes}. Uses a GLOBAL `$theme` signal, not a namespaced one: the layout body needs
`data-signals="{theme: initTheme()}"`. Click runs `$theme = ...; document.documentElement.classList.toggle('dark', ...);
localStorage.setItem('theme', $theme)`. Needs the `initTheme()` head script from `layouts/root.templ`.

### toast [reg]
ToastContainer{ID, Position (top-left|top-center|top-right|bottom-left|bottom-center|bottom-right), Class};
ToastItem{ID, Title, Description, Variant (default|success|destructive|info), Duration (unused by the markup), Class, Attributes};
ToastTrigger(toastID string, duration int) positional; `ShowToastExpr(id, ms)` helper returns
`$<id>.open = true; setTimeout(() => { $<id>.open = false }, <ms>)`.
Signals one per item `{<id>: {open: bool}}`. JS: `data-show`, `data-on:click`, `setTimeout`, `data-state` as a plain
attribute holding the expression text. Render set: `cases/toast.json`.

### tooltip [reg]
TooltipTrigger{ID, TooltipID, DelayDuration (default 700)}; TooltipContent{ID, UseAnchor, Side, Align, SideOffset}.
Signals `{<tooltipId>: {open, showTimeout: "", hideTimeout: "", touchHeld: false, touchTimer: ""}}`.
JS: popover API (`showPopover()` after `setTimeout`), mouseenter/leave/focus/blur/touchstart/touchend handlers.

## Not in DatastarUI

table, badge, skeleton, combobox, alert, separator, progress, switch, radio group, pagination, command.
See `missing.md` for class strings and recipes.

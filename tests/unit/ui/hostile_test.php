<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\AvatarProps;
use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\CheckboxProps;
use App\View\ui\ComboboxProps;
use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\DialogTriggerProps;
use App\View\ui\DropdownContentProps;
use App\View\ui\DropdownItemProps;
use App\View\ui\DropdownLabelProps;
use App\View\ui\DropdownLinkItemProps;
use App\View\ui\DropdownProps;
use App\View\ui\DropdownTriggerProps;
use App\View\ui\InputProps;
use App\View\ui\LabelProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\ui\SelectOption;
use App\View\ui\SelectProps;
use App\View\ui\SheetCloseProps;
use App\View\ui\SheetContentProps;
use App\View\ui\SheetProps;
use App\View\ui\SheetTriggerProps;
use App\View\ui\SkeletonProps;
use App\View\ui\TableHeaderProps;
use App\View\ui\TableProps;
use App\View\ui\TableRowProps;
use App\View\ui\TabsContentProps;
use App\View\ui\TabsProps;
use App\View\ui\TabsTriggerProps;
use App\View\ui\TextareaProps;
use App\View\ui\ToastProps;
use App\View\ui\ToastRegionProps;
use App\View\ui\TooltipContentProps;
use App\View\ui\TooltipTriggerProps;

/**
 * One hostile string goes through every text and attribute prop of every component. For each render the string must
 * never appear verbatim and no <script tag may appear. Props that sit inside JS strings or signal paths (ids, tab values,
 * colours) are validated instead: throwing InvalidArgumentException counts as safe.
 */
const HOSTILE = '<script>"\' & {{x}}';

/** @param callable(string):string $render */
function ui_hostile_check(callable $render): void
{
    try {
        $html = $render(HOSTILE);
    } catch (InvalidArgumentException) {
        return;
    }
    t_not_contains(HOSTILE, $html, 'hostile string appeared verbatim');
    t_not_contains('<script', strtolower($html), 'script tag appeared');
    // The hostile quote characters must only ever appear entity-encoded or JSON-escaped, never raw next to a tag open.
    t_true(preg_match('/<[^a-zA-Z\/!]/', $html) !== 1, 'stray angle bracket in output');
}

$p = static fn (string $class = '', array $attrs = []): PartProps => new PartProps($class, $attrs);
$opts = static fn (string $x): array => [new SelectOption($x, $x, false, $x), new SelectOption($x . '2', $x)];

$cases = [
    'button' => static fn (string $x): string => ui_button(new ButtonProps(variant: $x, size: $x, type: $x, class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_button(new ButtonProps(href: $x, target: $x, rel: $x, class: $x), e($x)) . ui_button(new ButtonProps(asChild: true), e($x)),
    'input' => static fn (string $x): string => ui_input(new InputProps(type: $x, class: $x, placeholder: $x, value: $x, name: $x, id: $x, formId: $x, attrs: ['data-x' => $x])),
    'textarea' => static fn (string $x): string => ui_textarea(new TextareaProps(class: $x, placeholder: $x, value: $x, name: $x, id: $x, formId: $x, attrs: ['data-x' => $x])),
    'label' => static fn (string $x): string => ui_label(new LabelProps(class: $x, for: $x, attrs: ['data-x' => $x]), e($x)),
    'checkbox' => static fn (string $x): string => ui_checkbox(new CheckboxProps(id: 'c1', name: $x, class: $x, attrs: ['data-x' => $x])),
    'checkbox id' => static fn (string $x): string => ui_checkbox(new CheckboxProps(id: $x)),
    'select' => static fn (string $x): string => ui_select(new SelectProps(id: 's1', value: $x, name: $x, placeholder: $x, class: $x, options: $opts($x), attrs: ['data-x' => $x])),
    'select id' => static fn (string $x): string => ui_select(new SelectProps(id: $x, options: $opts($x))),
    'native select' => static fn (string $x): string => ui_native_select(new NativeSelectProps(id: $x, name: $x, value: $x, placeholder: $x, class: $x, options: $opts($x), attrs: ['data-x' => $x])),
    'combobox' => static fn (string $x): string => ui_combobox(new ComboboxProps(id: 'cb', options: $opts($x), value: $x, name: $x, placeholder: $x, searchPlaceholder: $x, emptyText: $x, class: $x, attrs: ['data-x' => $x])),
    'combobox id' => static fn (string $x): string => ui_combobox(new ComboboxProps(id: $x)),
    'badge' => static fn (string $x): string => ui_badge(new BadgeProps(variant: $x, stage: $x, href: $x, class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_badge(new BadgeProps(variant: $x, class: $x), e($x)),
    'card' => static fn (string $x): string => ui_card($p($x, ['data-x' => $x]), ui_card_header($p($x), ui_card_title($p($x), e($x)) . ui_card_description($p($x), e($x)) . ui_card_action($p($x), e($x))) . ui_card_content($p($x), e($x)) . ui_card_footer($p($x), e($x))),
    'dialog' => static fn (string $x): string => ui_dialog(new DialogProps(id: 'd1', class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_dialog_trigger(new DialogTriggerProps(dialogId: 'd1', class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_dialog_close(new DialogCloseProps(dialogId: 'd1', returnValue: $x, variant: $x, class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_dialog_header($p($x), e($x)) . ui_dialog_footer($p($x), e($x)) . ui_dialog_title($p($x), e($x)) . ui_dialog_description($p($x), e($x)) . ui_dialog_content($p($x), e($x)),
    'dialog id' => static fn (string $x): string => ui_dialog(new DialogProps(id: $x)),
    'sheet' => static fn (string $x): string => ui_sheet(new SheetProps(id: 'sh', side: $x, attrs: ['data-x' => $x]), e($x))
        . ui_sheet_trigger(new SheetTriggerProps(sheetId: 'sh', class: $x), e($x))
        . ui_sheet_close(new SheetCloseProps(sheetId: 'sh', returnValue: $x, class: $x), e($x))
        . ui_sheet_content(new SheetContentProps(sheetId: 'sh', class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_sheet_header($p($x), e($x)) . ui_sheet_footer($p($x), e($x)) . ui_sheet_title($p($x), e($x)) . ui_sheet_description($p($x), e($x)),
    'sheet id' => static fn (string $x): string => ui_sheet(new SheetProps(id: $x)),
    'dropdown' => static fn (string $x): string => ui_dropdown(new DropdownProps(id: 'dd', class: $x, attrs: ['data-x' => $x]),
        ui_dropdown_trigger(new DropdownTriggerProps(id: 'dd', class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_dropdown_content(new DropdownContentProps(id: 'dd', align: $x, side: $x, class: $x, attrs: ['data-x' => $x]),
            ui_dropdown_label(new DropdownLabelProps(class: $x), e($x))
            . ui_dropdown_item(new DropdownItemProps(id: 'dd', variant: $x, class: $x, attrs: ['data-x' => $x]), e($x))
            . ui_dropdown_link_item(new DropdownLinkItemProps(id: 'dd', href: $x, target: $x, rel: $x, variant: $x, class: $x), e($x))
            . ui_dropdown_separator($p($x)) . ui_dropdown_shortcut($p($x), e($x)) . ui_dropdown_group($p($x), e($x)))),
    'dropdown id' => static fn (string $x): string => ui_dropdown_trigger(new DropdownTriggerProps(id: $x)),
    'tabs' => static fn (string $x): string => ui_tabs(new TabsProps(id: 'tb', class: $x, attrs: ['data-x' => $x]), ui_tabs_list($p($x), e($x))),
    'tabs value' => static fn (string $x): string => ui_tabs(new TabsProps(id: 'tb', defaultValue: $x)) . ui_tabs_trigger(new TabsTriggerProps(id: 'tb', value: $x), ''),
    'tabs content value' => static fn (string $x): string => ui_tabs_content(new TabsContentProps(id: 'tb', value: $x), ''),
    'tabs class' => static fn (string $x): string => ui_tabs_trigger(new TabsTriggerProps(id: 'tb', value: 'a', class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_tabs_content(new TabsContentProps(id: 'tb', value: 'a', class: $x, attrs: ['data-x' => $x]), e($x)),
    'tooltip' => static fn (string $x): string => ui_tooltip_trigger(new TooltipTriggerProps(id: 'tt', tooltipId: 'tp', class: $x, attrs: ['data-x' => $x]), e($x))
        . ui_tooltip_content(new TooltipContentProps(id: 'tp', useAnchor: true, side: $x, align: $x, class: $x, attrs: ['data-x' => $x]), e($x)),
    'tooltip id' => static fn (string $x): string => ui_tooltip_trigger(new TooltipTriggerProps(tooltipId: $x)),
    'avatar name' => static fn (string $x): string => ui_avatar(new AvatarProps(name: $x, class: $x, attrs: ['data-x' => $x])),
    'avatar colour' => static fn (string $x): string => ui_avatar(new AvatarProps(backgroundColor: $x, textColor: $x), e($x)),
    'toast' => static fn (string $x): string => ui_toast_region(new ToastRegionProps(id: 'toasts', position: $x, class: $x, attrs: ['data-x' => $x]),
        ui_toast(new ToastProps(id: 't1', title: $x, description: $x, kind: $x, class: $x, attrs: ['data-x' => $x]), e($x))),
    'toast id' => static fn (string $x): string => ui_toast(new ToastProps(id: $x, title: 'x')),
    'toast region id' => static fn (string $x): string => ui_toast_region(new ToastRegionProps(id: $x)),
    'toast trigger' => static fn (string $x): string => ui_toast_trigger($x, 1000, 'x'),
    'table' => static fn (string $x): string => ui_table(new TableProps(density: $x, containerClass: $x, class: $x, attrs: ['data-x' => $x]),
        ui_table_caption($p($x, ['data-x' => $x]), e($x))
        . ui_table_header(new TableHeaderProps(class: $x, attrs: ['data-x' => $x]), ui_table_row(new TableRowProps(class: $x, attrs: ['data-x' => $x]), ui_table_head($p($x, ['data-x' => $x]), e($x))))
        . ui_table_body($p($x, ['data-x' => $x]), ui_table_cell($p($x, ['data-x' => $x]), e($x))) . ui_table_footer($p($x, ['data-x' => $x]), e($x))),
    'skeleton' => static fn (string $x): string => ui_skeleton(new SkeletonProps(class: $x, attrs: ['data-x' => $x])),
];

$tests = [];
foreach ($cases as $name => $render) {
    $tests["hostile string in $name"] = static function () use ($render): void {
        ui_hostile_check($render);
    };
}

return $tests + [
    'ui_attrs rejects bad keys and event handlers, escapes values' => static function (): void {
        foreach (['onclick', 'Data-X', 'a b', 'a"b', 'a=b', '', '<x>'] as $bad) {
            t_throws(static fn () => ui_attrs([$bad => 'v']), InvalidArgumentException::class);
        }
        t_eq(' data-a:b_c.d-e="1" hidden', ui_attrs(['data-a:b_c.d-e' => 1, 'hidden' => true, 'x' => false, 'y' => null]));
        t_eq(' data-x="&lt;script&gt;&quot;&apos; &amp; {{x}}"', ui_attrs(['data-x' => HOSTILE]));
    },
    'ui_js_sq and ui_js_raw escape string breakouts' => static function (): void {
        t_eq("'it\\'s'", ui_js_sq("it's"));
        t_eq("'a\\\\b\\n'", ui_js_sq("a\\b\n"));
        t_not_contains('<', ui_js_raw(HOSTILE));
        t_not_contains("'", ui_js_raw(HOSTILE));
        t_eq(HOSTILE, json_decode(ui_js_raw(HOSTILE), true));
    },
];

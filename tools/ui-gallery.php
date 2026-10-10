<?php
declare(strict_types=1);

/**
 * Writes tests/fixtures/ui/gallery.html: every ui_* component in a light and a dark section, static (no Datastar needed),
 * linking ../../../public/css/app.css so it can be opened from disk and screenshotted.
 *
 *   bash tools/build-css.sh && php tools/ui-gallery.php
 *
 * Open-state previews: fixed/popover elements are wrapped in a transformed box (fixed children then stay inside it) and
 * their display:none / popover attributes are stripped so the open look is visible without JavaScript.
 */

require_once dirname(__DIR__) . '/app/autoload.php';
require_once dirname(__DIR__) . '/app/View/ui/_all.php';

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
use App\View\ui\SheetContentProps;
use App\View\ui\SheetProps;
use App\View\ui\SkeletonProps;
use App\View\ui\TableHeaderProps;
use App\View\ui\TableProps;
use App\View\ui\TableRowProps;
use App\View\ui\TabsContentProps;
use App\View\ui\TabsProps;
use App\View\ui\TabsTriggerProps;
use App\View\ui\TextareaProps;
use App\View\ui\ToastProps;
use App\View\ui\TooltipContentProps;
use App\View\ui\TooltipTriggerProps;

function g_part(string $class = ''): PartProps
{
    return new PartProps($class);
}

function g_block(string $title, string $body, string $class = ''): string
{
    return '<section class="space-y-3"><h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">' . e($title) . '</h3>'
        . '<div class="' . attr($class !== '' ? $class : 'flex flex-wrap items-center gap-3') . '">' . $body . '</div></section>';
}

/** Box that contains fixed-position previews. */
function g_stage(string $inner, string $class = 'h-72'): string
{
    return '<div class="relative w-full overflow-hidden rounded-lg border bg-muted/30 [transform:translateZ(0)] ' . attr($class) . '">' . $inner . '</div>';
}

function g_open(string $html): string
{
    $html = preg_replace('~<div data-slot="command-empty".*?</div>~s', '', $html) ?? $html;
    return str_replace(['style="display: none; ', 'display: none;"', ' popover="auto"'], ['style="', '"', ''], $html);
}

function g_section(string $id, string $theme): string
{
    $stages = ['draft', 'briefed', 'in_progress', 'waiting', 'on_hold', 'in_review', 'approved_internal', 'approved_client', 'done', 'archived', 'cancelled', 'overdue', 'due_soon'];
    $stageLabel = static fn (string $s): string => ucfirst(str_replace('_', ' ', $s));
    $out = '<section id="' . $id . '" class="' . ($theme === 'dark' ? 'dark ' : '') . 'bg-background text-foreground'
        . ' p-6 sm:p-8 space-y-8"><h2 class="text-xl font-semibold">UI kit, ' . $theme . '</h2>';

    $btn = '';
    foreach (['default', 'secondary', 'outline', 'ghost', 'link', 'destructive'] as $v) {
        $btn .= ui_button(new ButtonProps(variant: $v), ucfirst($v));
    }
    $out .= g_block('Button variants', $btn);
    $out .= g_block('Button sizes and states',
        ui_button(new ButtonProps(size: 'sm'), 'Small') . ui_button(new ButtonProps(), 'Default') . ui_button(new ButtonProps(size: 'lg'), 'Large')
        . ui_button(new ButtonProps(size: 'icon', variant: 'outline'), '+') . ui_button(new ButtonProps(disabled: true), 'Disabled')
        . ui_button(new ButtonProps(href: '/projects', variant: 'outline'), 'Link button'));

    $out .= g_block('Badge: variants', implode('', array_map(
        static fn (string $v): string => ui_badge(new BadgeProps(variant: $v), ucfirst($v)),
        ['default', 'secondary', 'destructive', 'outline', 'ghost', 'link']
    )));
    $out .= g_block('Badge: stages and due state', implode('', array_map(
        static fn (string $s): string => ui_badge(new BadgeProps(stage: $s), $stageLabel($s)),
        $stages
    )));

    $out .= g_block('Input, textarea, label, checkbox',
        '<div class="grid w-full max-w-xl gap-4">'
        . '<div class="grid gap-1.5">' . ui_label(new LabelProps(for: 'g-title-' . $id), 'Title') . ui_input(new InputProps(type: 'text', id: 'g-title-' . $id, name: 'title', placeholder: 'Task title')) . '</div>'
        . '<div class="grid gap-1.5">' . ui_label(new LabelProps(for: 'g-dis-' . $id), 'Disabled') . ui_input(new InputProps(id: 'g-dis-' . $id, value: 'Locked', disabled: true)) . '</div>'
        . '<div class="grid gap-1.5">' . ui_label(new LabelProps(for: 'g-notes-' . $id), 'Notes') . ui_textarea(new TextareaProps(id: 'g-notes-' . $id, placeholder: 'Write something', rows: 3)) . '</div>'
        . '<div class="flex items-center gap-2">' . ui_checkbox(new CheckboxProps(id: 'g-chk-' . $id, name: 'a', checked: true)) . ui_label(new LabelProps(), 'Checked')
        . ui_checkbox(new CheckboxProps(id: 'g-chk2-' . $id, name: 'b')) . ui_label(new LabelProps(), 'Unchecked') . '</div>'
        . '</div>', 'w-full');

    $opts = [new SelectOption('draft', 'Draft'), new SelectOption('in_progress', 'In progress'), new SelectOption('done', 'Done'), new SelectOption('hold', 'On hold', disabled: true)];
    $out .= g_block('Select: native, DatastarUI (closed and open), combobox',
        '<div class="w-56">' . ui_native_select(new NativeSelectProps(id: 'g-ns-' . $id, name: 'stage', placeholder: 'Pick a stage', options: $opts)) . '</div>'
        . '<div class="w-56">' . ui_select(new SelectProps(id: 'g-sel-' . $id, placeholder: 'Pick a stage', options: $opts, value: 'done')) . '</div>'
        . g_stage('<div class="w-56 p-3">' . g_open(ui_select(new SelectProps(id: 'g-selo-' . $id, defaultOpen: true, placeholder: 'Pick', options: $opts, value: 'in_progress'))) . '</div>', 'h-56 w-72')
        . g_stage('<div class="w-56 p-3">' . g_open(ui_combobox(new ComboboxProps(id: 'g-cb-' . $id, placeholder: 'Owner', options: [new SelectOption('1', 'Ann Lee'), new SelectOption('2', 'Bo Chen'), new SelectOption('3', 'Cy Park')], value: '2'))) . '</div>', 'h-72 w-72'));

    $out .= g_block('Card',
        ui_card(g_part('w-full max-w-sm'),
            ui_card_header(g_part(), ui_card_title(g_part(), 'Spring campaign') . ui_card_description(g_part(), 'Brief approved by the client') . ui_card_action(g_part(), ui_badge(new BadgeProps(stage: 'approved_client'), 'Approved')))
            . ui_card_content(g_part(), 'Delivery on 14 Nov. Two rounds of changes left.')
            . ui_card_footer(g_part('gap-2'), ui_button(new ButtonProps(size: 'sm'), 'Open') . ui_button(new ButtonProps(size: 'sm', variant: 'outline'), 'Archive'))));

    $out .= g_block('Tabs',
        ui_tabs(new TabsProps(id: 'g-tabs-' . $id, defaultValue: 'list', class: 'w-full max-w-md'),
            ui_tabs_list(g_part(), ui_tabs_trigger(new TabsTriggerProps(id: 'g-tabs-' . $id, value: 'list'), 'List') . ui_tabs_trigger(new TabsTriggerProps(id: 'g-tabs-' . $id, value: 'board'), 'Board'))
            . ui_tabs_content(new TabsContentProps(id: 'g-tabs-' . $id, value: 'list', class: 'pt-2 text-sm'), 'List view content')
            . ui_tabs_content(new TabsContentProps(id: 'g-tabs-' . $id, value: 'board', class: 'pt-2 text-sm'), 'Board view content')),
        'w-full');

    $out .= g_block('Avatar', ui_avatar(new AvatarProps(name: 'Nick Jackson')) . ui_avatar(new AvatarProps(name: 'Ann Lee')) . ui_avatar(new AvatarProps(name: 'Bo Chen', class: 'size-10'))
        . ui_avatar(new AvatarProps(name: 'Cy')) . ui_avatar(new AvatarProps(name: 'Dee Park')) . ui_avatar(new AvatarProps(name: 'Eli Ng')));

    $out .= g_block('Skeleton', ui_skeleton(new SkeletonProps(class: 'size-10 rounded-full')) . '<div class="space-y-2">' . ui_skeleton(new SkeletonProps(class: 'h-4 w-[250px]')) . ui_skeleton(new SkeletonProps(class: 'h-4 w-[200px]')) . '</div>');

    $menu = ui_dropdown(new DropdownProps(id: 'g-dd-' . $id, defaultOpen: true),
        ui_dropdown_trigger(new DropdownTriggerProps(id: 'g-dd-' . $id, class: 'rounded-md border px-3 py-1.5 text-sm'), 'Row actions')
        . ui_dropdown_content(new DropdownContentProps(id: 'g-dd-' . $id, class: 'w-48'),
            ui_dropdown_label(new DropdownLabelProps(), 'Job 0412') . ui_dropdown_separator(g_part())
            . ui_dropdown_item(new DropdownItemProps(id: 'g-dd-' . $id), 'Edit' . ui_dropdown_shortcut(g_part(), 'E'))
            . ui_dropdown_link_item(new DropdownLinkItemProps(id: 'g-dd-' . $id, href: '/jobs/412'), 'Open')
            . ui_dropdown_item(new DropdownItemProps(id: 'g-dd-' . $id, disabled: true), 'Duplicate')
            . ui_dropdown_item(new DropdownItemProps(id: 'g-dd-' . $id, variant: 'destructive'), 'Delete')));
    $menu = str_replace('top: calc(var(--dui-dropdown-trigger-bottom, 0px) + 1.00rem); left: var(--dui-dropdown-trigger-left, 0px);', 'top: 3rem; left: 0.75rem;', g_open($menu));
    $out .= g_block('Dropdown menu (open preview)', g_stage('<div class="p-3">' . $menu . '</div>', 'h-64 w-72'));

    $tip = ui_tooltip_trigger(new TooltipTriggerProps(tooltipId: 'g-tt-' . $id, class: 'inline-block rounded-md border px-3 py-1.5 text-sm'), 'Hover target')
        . preg_replace('/ style="[^"]*"/', '', g_open(ui_tooltip_content(new TooltipContentProps(id: 'g-tt-' . $id, class: 'inline-block'), 'Tooltip text')));
    $out .= g_block('Tooltip (static preview)', $tip);

    $toasts = '';
    foreach (['ok' => 'Saved', 'info' => 'Heads up', 'warn' => 'Due in two days', 'error' => 'Could not save'] as $kind => $title) {
        $toasts .= ui_toast(new ToastProps(id: 'g-t-' . $kind . '-' . $id, title: $title, description: 'Kind: ' . $kind, kind: $kind, open: true));
    }
    $out .= g_block('Toast (ok, info, warn, error)', '<div class="grid w-full max-w-md gap-2">' . g_open($toasts) . '</div>', 'w-full');

    $rowp = static fn (string $t, string $stage, string $owner, string $due, string $dueClass = ''): string => ui_table_row(new TableRowProps(),
        ui_table_cell(g_part('font-medium'), e($t)) . ui_table_cell(g_part(), ui_badge(new BadgeProps(stage: $stage), e(ucfirst(str_replace('_', ' ', $stage)))))
        . ui_table_cell(g_part(), '<span class="inline-flex items-center gap-2">' . ui_avatar(new AvatarProps(name: $owner, class: 'size-5 text-[10px]')) . e($owner) . '</span>')
        . ui_table_cell(g_part($dueClass), e($due)));
    $rows = $rowp('Spring campaign', 'in_progress', 'Ann Lee', '14 Nov') . $rowp('Packaging refresh', 'waiting', 'Bo Chen', '20 Nov')
        . $rowp('Annual report', 'in_review', 'Cy Park', '2 Nov', 'text-destructive font-medium') . $rowp('Event signage', 'done', 'Ann Lee', '28 Oct')
        . $rowp('Website audit', 'on_hold', 'Dee Park', '30 Nov') . $rowp('Brochure', 'draft', 'Eli Ng', '4 Dec');
    $thead = ui_table_header(new TableHeaderProps(sticky: true), ui_table_row(new TableRowProps(), ui_table_head(g_part(), 'Job') . ui_table_head(g_part(), 'Stage') . ui_table_head(g_part(), 'Owner') . ui_table_head(g_part(), 'Due')));
    $out .= g_block('Table: compact, sticky header, scroll container', ui_table(new TableProps(density: 'compact', containerClass: 'max-h-40 overflow-y-auto rounded-lg border'), $thead . ui_table_body(g_part(), $rows)), 'w-full');
    $out .= g_block('Table: default density', ui_table(new TableProps(containerClass: 'rounded-lg border'), ui_table_header(new TableHeaderProps(), ui_table_row(new TableRowProps(), ui_table_head(g_part(), 'Job') . ui_table_head(g_part(), 'Stage') . ui_table_head(g_part(), 'Owner') . ui_table_head(g_part(), 'Due'))) . ui_table_body(g_part(), $rowp('Spring campaign', 'in_progress', 'Ann Lee', '14 Nov') . $rowp('Packaging refresh', 'waiting', 'Bo Chen', '20 Nov'))), 'w-full');

    $dlg = ui_dialog(new DialogProps(id: 'g-dlg-' . $id, defaultOpen: true),
        ui_dialog_header(g_part(), ui_dialog_title(g_part(), 'Delete project') . ui_dialog_description(g_part(), 'This cannot be undone.'))
        . ui_dialog_content(g_part(), 'Are you sure you want to delete Spring campaign?')
        . ui_dialog_footer(g_part(), ui_dialog_close(new DialogCloseProps(dialogId: 'g-dlg-' . $id, variant: 'outline'), 'Cancel') . ui_dialog_close(new DialogCloseProps(dialogId: 'g-dlg-' . $id, variant: 'destructive'), 'Delete')));
    $out .= g_block('Dialog (open preview) and trigger', ui_dialog_trigger(new DialogTriggerProps(dialogId: 'g-dlg-' . $id, class: 'rounded-md border px-3 py-1.5 text-sm'), 'Open dialog') . g_stage($dlg, 'h-72'), 'w-full space-y-3');

    $sheet = ui_sheet(new SheetProps(id: 'g-sheet-' . $id, defaultOpen: true, modal: true, side: 'right'),
        ui_sheet_content(new SheetContentProps(sheetId: 'g-sheet-' . $id),
            ui_sheet_header(g_part(), ui_sheet_title(g_part(), 'Job 0412') . ui_sheet_description(g_part(), 'Side panel patched into #sheet.'))
            . '<div class="px-4 text-sm">Details go here.</div>' . ui_sheet_footer(g_part(), ui_button(new ButtonProps(), 'Save'))));
    $out .= g_block('Sheet, right side (open preview)', g_stage($sheet, 'h-80'), 'w-full');

    return $out . '</section>';
}

$html = '<!doctype html>' . "\n" . '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>UI kit gallery</title><link rel="stylesheet" href="../../../public/css/app.css"></head>'
    . '<body class="bg-background text-foreground antialiased">' . g_section('light', 'light') . g_section('dark', 'dark') . '</body></html>' . "\n";

$target = dirname(__DIR__) . '/tests/fixtures/ui/gallery.html';
file_put_contents($target, $html);
echo 'wrote ' . $target . ' (' . strlen($html) . " bytes)\n";

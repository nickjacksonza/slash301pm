<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\DropdownContentProps;
use App\View\ui\DropdownItemProps;
use App\View\ui\DropdownLabelProps;
use App\View\ui\DropdownLinkItemProps;
use App\View\ui\DropdownProps;
use App\View\ui\DropdownTriggerProps;
use App\View\ui\PartProps;
use App\View\ui\TooltipContentProps;
use App\View\ui\TooltipTriggerProps;

$p = static fn (): PartProps => new PartProps();

return ui_fx_cases('dropdown+tooltip', [
    'dropdown' => static fn (): string => ui_dropdown(new DropdownProps(id: 'row-menu'),
        ui_dropdown_trigger(new DropdownTriggerProps(id: 'row-menu'), 'Actions')
        . ui_dropdown_content(new DropdownContentProps(id: 'row-menu'),
            ui_dropdown_label(new DropdownLabelProps(), 'My row')
            . ui_dropdown_separator($p())
            . ui_dropdown_item(new DropdownItemProps(id: 'row-menu', onClick: "@get('/edit')"), 'Edit' . ui_dropdown_shortcut($p(), 'E'))
            . ui_dropdown_item(new DropdownItemProps(id: 'row-menu', variant: 'destructive'), 'Delete')
            . ui_dropdown_item(new DropdownItemProps(id: 'row-menu', disabled: true, inset: true), 'Locked')
            . ui_dropdown_link_item(new DropdownLinkItemProps(id: 'row-menu', href: '/x', target: '_blank', rel: 'noopener'), 'Open')
        )
    ),
    'dropdown.open' => static fn (): string => ui_dropdown(new DropdownProps(id: 'm2', defaultOpen: true, class: 'x')),
    'dropdown.trigger.disabled' => static fn (): string => ui_dropdown_trigger(new DropdownTriggerProps(id: 'm2', disabled: true, class: 'y'), 'A'),
    'dropdown.trigger.aschild' => static fn (): string => ui_dropdown_trigger(new DropdownTriggerProps(id: 'm2', asChild: true, disabled: true), 'A'),
    'dropdown.content.endtop' => static fn (): string => ui_dropdown_content(new DropdownContentProps(id: 'm2', side: 'top', align: 'end', sideOffset: 2), 'x'),
    'dropdown.content.right' => static fn (): string => ui_dropdown_content(new DropdownContentProps(id: 'm2', side: 'right', align: 'center'), 'x'),
    'dropdown.content.left' => static fn (): string => ui_dropdown_content(new DropdownContentProps(id: 'm2', side: 'left', align: 'end'), 'x'),
    'dropdown.content.bottomcenter' => static fn (): string => ui_dropdown_content(new DropdownContentProps(id: 'm2', side: 'bottom', align: 'center'), 'x'),
    'dropdown.group' => static fn (): string => ui_dropdown_group($p(), 'g'),
    'tooltip.trigger' => static fn (): string => ui_tooltip_trigger(new TooltipTriggerProps(id: 'tt-trigger', tooltipId: 'tt1'), 'Hover'),
    'tooltip.trigger.delay' => static fn (): string => ui_tooltip_trigger(new TooltipTriggerProps(tooltipId: 'tt2', delayDuration: 200, class: 'x'), 'Hover'),
    'tooltip.content' => static fn (): string => ui_tooltip_content(new TooltipContentProps(id: 'tt1', useAnchor: true, side: 'top', align: 'center'), 'Tip'),
    'tooltip.content.plain' => static fn (): string => ui_tooltip_content(new TooltipContentProps(id: 'tt2'), 'Tip'),
]) + [
    'dropdown onClick runs after the menu closes' => static function (): void {
        $h = ui_dropdown_item(new DropdownItemProps(id: 'm', onClick: '@post(' . ui_js_raw('/rows/1/delete') . ')'), 'x');
        t_contains('data-on:click="$m.open = false; @post(&quot;/rows/1/delete&quot;)"', $h);
    },
];

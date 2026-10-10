<?php
declare(strict_types=1);

use App\View\ui\DropdownContentProps;
use App\View\ui\DropdownItemProps;
use App\View\ui\DropdownLabelProps;
use App\View\ui\DropdownLinkItemProps;
use App\View\ui\DropdownProps;
use App\View\ui\DropdownTriggerProps;
use App\View\ui\PartProps;

/**
 * Ported from DatastarUI components/dropdown/dropdown.templ + expressions.go + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/dropdown*.html
 * Signal {<id>: {open}}. The content element id is "<id>-content". Items take the menu id and an optional onClick
 * (developer JS, run after the menu closes; embed data only through ui_js_raw()).
 * Deviation: DropdownMenuFormItem and DropdownMenuCustomItem are not ported (use a link item or a button inside an item).
 */
function ui_dropdown(DropdownProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    return '<div data-slot="dropdown-menu"' . ui_signals_attr($p->id, ['open' => $p->defaultOpen || $p->open])
        . ' data-on:click__outside="' . attr("{$sig}.open ? ({$sig}.open = false) : void 0") . '"'
        . ' class="' . attr(cx('relative', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_dropdown_trigger(DropdownTriggerProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    $content = ui_js_raw(ui_id($p->id) . '-content');
    $pos = "(function(){const c=document.getElementById({$content});if(c){const r=el.getBoundingClientRect();"
        . "c.style.setProperty('--dui-dropdown-trigger-top',r.top+'px');c.style.setProperty('--dui-dropdown-trigger-right',r.right+'px');"
        . "c.style.setProperty('--dui-dropdown-trigger-bottom',r.bottom+'px');c.style.setProperty('--dui-dropdown-trigger-left',r.left+'px');"
        . "c.style.setProperty('--dui-dropdown-trigger-center-x',(r.left+r.width/2)+'px');c.style.setProperty('--dui-dropdown-trigger-center-y',(r.top+r.height/2)+'px');}})()";
    $click = ' data-on:click="' . attr("{$pos}; {$sig}.open = !{$sig}.open") . '"';
    if ($p->asChild) {
        return '<span data-slot="dropdown-menu-trigger"' . ui_class_attr($p->class) . $click
            . ($p->disabled ? ' aria-disabled="true"' : '') . ui_attrs($p->attrs) . '>' . $children . '</span>';
    }
    return '<button data-slot="dropdown-menu-trigger" type="button"' . ui_class_attr($p->class) . $click
        . ui_bool('disabled', $p->disabled) . ui_attrs($p->attrs) . '>' . $children . '</button>';
}

function ui_dropdown_content_style(string $side, string $align, int $offset): string
{
    $offset = $offset === 0 ? 4 : $offset;
    $off = sprintf('%.2frem', $offset * 0.25);
    $transforms = [];
    switch ($side) {
        case 'top':
            $top = "calc(var(--dui-dropdown-trigger-top, 0px) - {$off})";
            $transforms[] = 'translateY(-100%)';
            $left = '';
            break;
        case 'left':
            $left = "calc(var(--dui-dropdown-trigger-left, 0px) - {$off})";
            $transforms[] = 'translateX(-100%)';
            $top = '';
            break;
        case 'right':
            $left = "calc(var(--dui-dropdown-trigger-right, 0px) + {$off})";
            $top = '';
            break;
        default:
            $top = "calc(var(--dui-dropdown-trigger-bottom, 0px) + {$off})";
            $left = '';
    }
    if ($side === 'left' || $side === 'right') {
        if ($align === 'end') {
            $top = 'var(--dui-dropdown-trigger-bottom, 0px)';
            $transforms[] = 'translateY(-100%)';
        } elseif ($align === 'center') {
            $top = 'var(--dui-dropdown-trigger-center-y, 0px)';
            $transforms[] = 'translateY(-50%)';
        } else {
            $top = 'var(--dui-dropdown-trigger-top, 0px)';
        }
    } elseif ($align === 'end') {
        $left = 'var(--dui-dropdown-trigger-right, 0px)';
        $transforms[] = 'translateX(-100%)';
    } elseif ($align === 'center') {
        $left = 'var(--dui-dropdown-trigger-center-x, 0px)';
        $transforms[] = 'translateX(-50%)';
    } else {
        $left = 'var(--dui-dropdown-trigger-left, 0px)';
    }
    $style = "display: none; top: {$top}; left: {$left};";
    if ($transforms !== []) {
        $style .= ' transform: ' . implode(' ', $transforms) . ';';
    }
    return $style;
}

function ui_dropdown_content(DropdownContentProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    $classes = cx(
        'bg-popover border data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 fixed min-w-[8rem] overflow-visible p-1 rounded-md shadow-md text-popover-foreground z-50',
        $p->class,
    );
    return '<div id="' . attr(ui_id($p->id) . '-content') . '" data-slot="dropdown-menu-content" data-side="' . attr($p->side) . '" data-align="' . attr($p->align) . '"'
        . ' class="' . attr($classes) . '" data-show="' . attr("{$sig}.open") . '"'
        . ' data-on:keydown__window="' . attr("evt.key === 'Escape' && {$sig}.open ? ({$sig}.open = false) : void 0") . '"'
        . ' role="menu" style="' . attr(ui_dropdown_content_style($p->side, $p->align, $p->sideOffset)) . '"'
        . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_dropdown_item_classes(string $variant, string $class, bool $inset, bool $disabled): string
{
    $base = "[&_svg:not([class*='size-'])]:size-4 [&_svg]:pointer-events-none [&_svg]:shrink-0 cursor-default data-[disabled]:opacity-50 data-[disabled]:pointer-events-none flex focus:bg-accent focus:text-accent-foreground gap-2 items-center outline-hidden px-2 py-1.5 relative rounded-sm select-none text-sm";
    $destructive = 'dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:*:[svg]:!text-destructive data-[variant=destructive]:focus:bg-destructive/10 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:text-destructive';
    $isDestructive = $variant === 'destructive';
    return cx(
        $base,
        $isDestructive ? $destructive : '',
        $inset ? 'data-[inset]:pl-8' : '',
        !$disabled ? "[&_svg:not([class*='text-'])]:text-muted-foreground" : '',
        $class,
    );
}

/** data-inset, data-variant and data-disabled as upstream prints them (data-variant even when empty). */
function ui_dropdown_item_data(string $variant, bool $inset, bool $disabled): string
{
    return ui_bool('data-inset', $inset) . ' data-variant="' . attr($variant) . '"' . ui_bool('data-disabled', $disabled);
}

function ui_dropdown_item_click(string $id, string $onClick): string
{
    $expr = '$' . ui_sig($id) . '.open = false';
    return $onClick === '' ? $expr : $expr . '; ' . $onClick;
}

function ui_dropdown_item(DropdownItemProps $p, string $children = ''): string
{
    $classes = ui_dropdown_item_classes($p->variant, $p->class, $p->inset, $p->disabled);
    $common = ' data-slot="dropdown-menu-item" class="' . attr($classes) . '" role="menuitem"';
    $tail = ($p->disabled ? ' aria-disabled="true"' : '') . ui_dropdown_item_data($p->variant, $p->inset, $p->disabled) . ui_attrs($p->attrs);
    if ($p->asChild) {
        return '<div' . $common . $tail . '>' . $children . '</div>';
    }
    return '<div' . $common . ' tabindex="0" data-on:click="' . attr(ui_dropdown_item_click($p->id, $p->onClick)) . '"' . $tail . '>' . $children . '</div>';
}

function ui_dropdown_link_item(DropdownLinkItemProps $p, string $children = ''): string
{
    $classes = ui_dropdown_item_classes($p->variant, $p->class, $p->inset, $p->disabled);
    return '<a data-slot="dropdown-menu-item" href="' . attr($p->href) . '" class="' . attr($classes) . '" role="menuitem"'
        . ui_attr_if('target', $p->target) . ui_attr_if('rel', $p->rel)
        . ($p->disabled ? ' aria-disabled="true" tabindex="-1"' : ' data-on:click="' . attr(ui_dropdown_item_click($p->id, $p->onClick)) . '"')
        . ui_dropdown_item_data($p->variant, $p->inset, $p->disabled) . ui_attrs($p->attrs) . '>' . $children . '</a>';
}

function ui_dropdown_label(DropdownLabelProps $p, string $children = ''): string
{
    return '<div data-slot="dropdown-menu-label" class="' . attr(cx('font-medium px-2 py-1.5 text-sm', $p->inset ? 'data-[inset]:pl-8' : '', $p->class))
        . '" role="presentation"' . ui_bool('data-inset', $p->inset) . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_dropdown_separator(PartProps $p): string
{
    return '<div data-slot="dropdown-menu-separator" class="' . attr(cx('-mx-1 bg-border h-px my-1', $p->class)) . '" role="separator"' . ui_attrs($p->attrs) . '></div>';
}

function ui_dropdown_shortcut(PartProps $p, string $children = ''): string
{
    return '<span data-slot="dropdown-menu-shortcut" class="' . attr(cx('ml-auto text-muted-foreground text-xs tracking-widest', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</span>';
}

function ui_dropdown_group(PartProps $p, string $children = ''): string
{
    return '<div data-slot="dropdown-menu-group"' . ui_class_attr($p->class) . ' role="group"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

<?php
declare(strict_types=1);

use App\View\ui\PartProps;
use App\View\ui\TabsContentProps;
use App\View\ui\TabsProps;
use App\View\ui\TabsTriggerProps;

/**
 * Ported from DatastarUI components/tabs/tabs.templ + expressions.go + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/tabs*.html
 * Signal {<id>: {active}}. Trigger and content take the tabs id and a value; values are checked with ui_tok()
 * because they sit inside single quoted JS strings. Upstream falls back to 'tab1' when no default is given.
 */
function ui_tabs(TabsProps $p, string $children = ''): string
{
    $active = $p->value !== '' ? $p->value : ($p->defaultValue !== '' ? $p->defaultValue : 'tab1');
    return '<div data-slot="tabs"' . ui_signals_attr($p->id, ['active' => $active])
        . ' class="' . attr(cx('flex flex-col gap-2', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_tabs_list(PartProps $p, string $children = ''): string
{
    return '<div class="' . attr(cx('bg-muted h-9 inline-flex items-center justify-center p-[3px] rounded-lg text-muted-foreground w-fit', $p->class))
        . '" role="tablist"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_tabs_trigger(TabsTriggerProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    $v = ui_tok($p->value);
    $is = "{$sig}.active === '{$v}'";
    $classes = cx(
        "[&_svg:not([class*='size-'])]:size-4 [&_svg]:pointer-events-none [&_svg]:shrink-0 border border-transparent dark:text-muted-foreground disabled:opacity-50 disabled:pointer-events-none flex-1 focus-visible:border-ring focus-visible:outline-1 focus-visible:outline-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 font-medium gap-1.5 h-[calc(100%-1px)] inline-flex items-center justify-center px-2 py-1 rounded-md text-foreground text-sm transition-[color,box-shadow] whitespace-nowrap",
        $p->class,
    );
    return '<button type="button" data-slot="tabs-trigger" class="' . attr($classes) . '"'
        . ' data-class="' . attr("{'bg-background': {$is}, 'text-foreground': {$is}, 'shadow-sm': {$is}}") . '"'
        . ' role="tab" data-value="' . attr($v) . '"'
        . ' data-on:click="' . attr("{$sig}.active = '{$v}'") . '"'
        . ' data-attr:data-state="' . attr("{$is} ? 'active' : 'inactive'") . '"'
        . ' data-attr:aria-selected="' . attr("{$is} ? 'true' : 'false'") . '"'
        . ' data-attr:tabindex="' . attr("{$is} ? '0' : '-1'") . '"'
        . ui_bool('disabled', $p->disabled) . ui_attrs($p->attrs) . '>' . $children . '</button>';
}

function ui_tabs_content(TabsContentProps $p, string $children = ''): string
{
    $sig = '$' . ui_sig($p->id);
    $v = ui_tok($p->value);
    $is = "{$sig}.active === '{$v}'";
    return '<div data-slot="tabs-content" class="' . attr(cx('flex-1 outline-none', $p->class)) . '" role="tabpanel" data-value="' . attr($v) . '"'
        . ' data-show="' . attr($is) . '" data-attr:aria-hidden="' . attr("{$is} ? 'false' : 'true'") . '" tabindex="0"'
        . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

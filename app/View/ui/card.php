<?php
declare(strict_types=1);

use App\View\ui\PartProps;

/**
 * Ported from DatastarUI components/card/card.templ + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/card*.html. Every part takes PartProps (class, attrs) and an escaped HTML $children.
 */
function ui_card_part(string $slot, string $classes, PartProps $p, string $children): string
{
    return '<div data-slot="' . $slot . '" class="' . attr(cx($classes, $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_card(PartProps $p, string $children = ''): string
{
    return ui_card_part('card', 'bg-card border flex flex-col gap-6 py-6 rounded-xl shadow-sm text-card-foreground', $p, $children);
}

function ui_card_header(PartProps $p, string $children = ''): string
{
    return ui_card_part('card-header', '@container/card-header [.border-b]:pb-6 auto-rows-min gap-1.5 grid grid-rows-[auto_auto] has-data-[slot=card-action]:grid-cols-[1fr_auto] items-start px-6', $p, $children);
}

function ui_card_title(PartProps $p, string $children = ''): string
{
    return ui_card_part('card-title', 'font-semibold leading-none', $p, $children);
}

function ui_card_description(PartProps $p, string $children = ''): string
{
    return ui_card_part('card-description', 'text-muted-foreground text-sm', $p, $children);
}

function ui_card_action(PartProps $p, string $children = ''): string
{
    return ui_card_part('card-action', 'col-start-2 justify-self-end row-span-2 row-start-1 self-start', $p, $children);
}

function ui_card_content(PartProps $p, string $children = ''): string
{
    return ui_card_part('card-content', 'px-6', $p, $children);
}

function ui_card_footer(PartProps $p, string $children = ''): string
{
    return ui_card_part('card-footer', '[.border-t]:pt-6 flex items-center px-6', $p, $children);
}

<?php
declare(strict_types=1);

use App\View\ui\PartProps;
use App\View\ui\TableHeaderProps;
use App\View\ui\TableProps;
use App\View\ui\TableRowProps;

/**
 * Source: shadcn/ui new-york-v4 table.tsx (no DatastarUI equivalent). Fixtures are hand written.
 * One function per part, each takes escaped HTML $children. Added for the Airtable-like grid:
 *   TableProps density 'compact' (smaller text, tight cells) and TableHeaderProps sticky (header stays while the container scrolls;
 *   give TableProps containerClass a height limit such as 'max-h-[70vh] overflow-y-auto').
 * Rows with a signal driven selection can add data-attr:data-state through attrs.
 */
function ui_table(TableProps $p, string $children = ''): string
{
    $density = $p->density === 'compact'
        ? 'text-[13px] [&_td]:px-2 [&_td]:py-1 [&_th]:h-8 [&_th]:px-2'
        : 'text-sm [&_td]:p-2 [&_th]:h-10 [&_th]:px-2';
    return '<div data-slot="table-container" class="' . attr(cx('relative w-full overflow-x-auto', $p->containerClass)) . '">'
        . '<table data-slot="table" data-density="' . attr($p->density === 'compact' ? 'compact' : 'default') . '" class="'
        . attr(cx('w-full caption-bottom', $density, $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</table></div>';
}

function ui_table_header(TableHeaderProps $p, string $children = ''): string
{
    $sticky = $p->sticky ? 'sticky top-0 z-10 bg-muted shadow-[0_1px_0_0_var(--color-border)]' : '';
    return '<thead data-slot="table-header" class="' . attr(cx('[&_tr]:border-b', $sticky, $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</thead>';
}

function ui_table_body(PartProps $p, string $children = ''): string
{
    return '<tbody data-slot="table-body" class="' . attr(cx('[&_tr:last-child]:border-0', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</tbody>';
}

function ui_table_footer(PartProps $p, string $children = ''): string
{
    return '<tfoot data-slot="table-footer" class="' . attr(cx('border-t bg-muted/50 font-medium [&>tr]:last:border-b-0', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</tfoot>';
}

function ui_table_row(TableRowProps $p, string $children = ''): string
{
    return '<tr data-slot="table-row" class="' . attr(cx('border-b transition-colors hover:bg-muted/50 has-aria-expanded:bg-muted/50 data-[state=selected]:bg-muted', $p->class)) . '"'
        . ($p->selected ? ' data-state="selected"' : '') . ui_attrs($p->attrs) . '>' . $children . '</tr>';
}

function ui_table_head(PartProps $p, string $children = ''): string
{
    return '<th data-slot="table-head" class="' . attr(cx('text-left align-middle font-medium whitespace-nowrap text-foreground [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</th>';
}

function ui_table_cell(PartProps $p, string $children = ''): string
{
    return '<td data-slot="table-cell" class="' . attr(cx('align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</td>';
}

function ui_table_caption(PartProps $p, string $children = ''): string
{
    return '<caption data-slot="table-caption" class="' . attr(cx('mt-4 text-sm text-muted-foreground', $p->class)) . '"' . ui_attrs($p->attrs) . '>' . $children . '</caption>';
}

<?php
declare(strict_types=1);

use App\View\ui\SegmentItem;

/*
 * Composite parts shared by every page, so the look stays the same everywhere:
 *   partial_segmented     pill group (Grid/Board, review filters, status tabs)
 *   partial_empty_state   dashed muted box for "nothing here"
 *   partial_metric_card   number and label tile
 *   partial_page_toolbar  title block on the left, actions on the right
 * Text arguments are plain and escaped here; arguments named *Html are already escaped HTML.
 */

/**
 * A pill group. Items with an href are links (a nav when $nav is true), the others are buttons.
 * An item is active when current is true (server side) or when its activeExpr is true (Datastar).
 *
 * @param list<SegmentItem> $items
 * @param array<string,string|int|bool> $attrs extra attributes on the group
 */
function partial_segmented(array $items, string $label, bool $nav = false, bool $compact = false, string $class = '', array $attrs = []): string
{
    $size = $compact ? 'px-3 py-1 text-xs' : 'px-4 py-1.5 text-sm';
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-full font-medium whitespace-nowrap transition-colors disabled:pointer-events-none disabled:opacity-40';
    $on = 'bg-accent text-accent-foreground';
    $off = 'text-muted-foreground hover:bg-background hover:text-foreground';
    $html = '';
    foreach ($items as $it) {
        $count = $it->count !== null ? ' <span class="text-xs tabular-nums opacity-80">' . $it->count . '</span>' : '';
        $inner = e($it->label) . $count;
        if ($it->activeExpr !== '') {
            $dataClass = ' data-class="' . attr("{'" . $on . "': " . $it->activeExpr . ", '" . $off . "': !(" . $it->activeExpr . ')}') . '"'
                . ' data-attr:aria-pressed="' . attr('(' . $it->activeExpr . ") ? 'true' : 'false'") . '"';
            $state = '';
            $classes = cx($base, $size);
        } else {
            $dataClass = '';
            $state = '';
            $classes = cx($base, $size, $it->current ? $on : $off);
        }
        if ($it->href !== '') {
            $state = $it->current ? ' aria-current="page"' : '';
            $html .= '<a href="' . attr($it->href) . '" class="' . attr($classes) . '"' . $state . $dataClass . ui_attrs($it->attrs) . '>' . $inner . '</a>';
        } else {
            $html .= '<button type="button" class="' . attr($classes) . '"' . ui_bool('disabled', $it->disabled) . $dataClass . ui_attrs($it->attrs) . '>' . $inner . '</button>';
        }
    }
    $group = cx('inline-flex w-fit max-w-full flex-wrap items-center gap-1 rounded-full bg-muted p-1', $class);
    if ($nav) {
        return '<nav aria-label="' . attr($label) . '" class="' . attr($group) . '"' . ui_attrs($attrs) . '>' . $html . '</nav>';
    }
    return '<div role="group" aria-label="' . attr($label) . '" class="' . attr($group) . '"' . ui_attrs($attrs) . '>' . $html . '</div>';
}

/** Dashed muted box. $size is sm, md or lg (lg is centred). Text is escaped. */
function partial_empty_state(string $text, string $size = 'md', array $attrs = []): string
{
    return partial_empty_state_html(e($text), $size, $attrs);
}

/** The same box with already escaped HTML inside (for a link in the message). @param array<string,string|int|bool> $attrs */
function partial_empty_state_html(string $html, string $size = 'md', array $attrs = []): string
{
    $pad = match ($size) {
        'sm' => 'px-3 py-3',
        'lg' => 'px-3 py-6 text-center',
        default => 'px-3 py-4',
    };
    return '<p class="' . attr(cx('rounded-xl border border-dashed border-border text-sm text-muted-foreground', $pad)) . '"' . ui_attrs($attrs) . '>' . $html . '</p>';
}

/** A big number with a small label under it. $hint becomes the title attribute. */
function partial_metric_card(int|string $value, string $label, string $hint = ''): string
{
    return '<div class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm"' . ui_attr_if('title', $hint) . '>'
        . '<p class="text-2xl font-semibold leading-none">' . e($value) . '</p>'
        . '<p class="mt-1 text-xs text-muted-foreground">' . e($label) . '</p></div>';
}

/**
 * Title block on the left (eyebrow line, title, optional subtitle), actions on the right.
 * $eyebrowHtml and $actionsHtml are escaped HTML; $title and $subtitle are plain text.
 * $large gives the title the page-level size (Social); $extraHtml sits under the title (badges).
 */
function partial_page_toolbar(string $title, string $actionsHtml = '', string $eyebrowHtml = '', string $subtitle = '', string $extraHtml = '', bool $large = false, string $class = ''): string
{
    $h = '<div class="min-w-0">';
    if ($eyebrowHtml !== '') {
        $h .= '<p class="text-xs text-muted-foreground">' . $eyebrowHtml . '</p>';
    }
    $h .= '<h2 class="' . ($large ? 'text-2xl' : 'text-xl') . ' font-semibold leading-tight break-words">' . e($title) . '</h2>';
    if ($subtitle !== '') {
        $h .= '<p class="text-sm text-muted-foreground">' . e($subtitle) . '</p>';
    }
    $h .= $extraHtml . '</div>';
    if ($actionsHtml !== '') {
        $h .= '<div class="flex flex-wrap items-center gap-2 text-sm">' . $actionsHtml . '</div>';
    }
    return '<header class="' . attr(cx('flex flex-wrap items-start justify-between gap-3', $class)) . '">' . $h . '</header>';
}

<?php
declare(strict_types=1);

use App\View\ui\TooltipContentProps;
use App\View\ui\TooltipTriggerProps;

/**
 * Ported from DatastarUI components/tooltip/tooltip.templ + expressions.go + variants.go and utils/anchor.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/tooltip*.html
 * Native Popover API + CSS anchor positioning (styles/popover.css). Trigger tooltipId and content id must match.
 * Signals {<tooltipId>: {open, showTimeout, hideTimeout, touchHeld, touchTimer}}.
 */
function ui_tooltip_anchor_css(string $side, string $align, int $offset): string
{
    $o = $offset . 'px';
    return match ($side) {
        'top' => match ($align) {
            'start' => "top: anchor(top); left: anchor(left); translate: 0 calc(-100% - {$o})",
            'end' => "top: anchor(top); left: anchor(right); translate: -100% calc(-100% - {$o})",
            default => "top: anchor(top); left: anchor(center); translate: -50% calc(-100% - {$o})",
        },
        'right' => match ($align) {
            'start' => "top: anchor(top); left: anchor(right); translate: {$o} 0",
            'end' => "top: anchor(bottom); left: anchor(right); translate: {$o} -100%",
            default => "top: anchor(center); left: anchor(right); translate: {$o} -50%",
        },
        'left' => match ($align) {
            'start' => "top: anchor(top); left: anchor(left); translate: calc(-100% - {$o}) 0",
            'end' => "top: anchor(bottom); left: anchor(left); translate: calc(-100% - {$o}) -100%",
            default => "top: anchor(center); left: anchor(left); translate: calc(-100% - {$o}) -50%",
        },
        'bottom' => match ($align) {
            'start' => "top: anchor(bottom); left: anchor(left); translate: 0 {$o}",
            'end' => "top: anchor(bottom); left: anchor(right); translate: -100% {$o}",
            default => "top: anchor(bottom); left: anchor(center); translate: -50% {$o}",
        },
        default => "top: anchor(bottom); left: anchor(center); translate: -50% {$o}",
    };
}

function ui_tooltip_trigger(TooltipTriggerProps $p, string $children = ''): string
{
    $tid = ui_id($p->tooltipId);
    $sig = '$' . ui_sig($tid);
    $delay = $p->delayDuration === 0 ? 700 : $p->delayDuration;
    $get = "document.getElementById('{$tid}')";
    $show = "setTimeout(() => { {$get}.showPopover(); }, {$delay})";
    $hide = "{$get}.hidePopover()";
    $touchStart = "evt.preventDefault(); clearTimeout({$sig}.touchTimer); {$sig}.touchTimer = setTimeout(() => { {$sig}.touchHeld = true; {$get}.showPopover(); }, 500)";
    $touchEnd = "clearTimeout({$sig}.touchTimer); {$sig}.touchTimer = null; !{$sig}.touchHeld ? {$hide} : null";
    return '<div id="' . attr($p->id) . '"'
        . ui_signals_attr($tid, ['open' => false, 'showTimeout' => '', 'hideTimeout' => '', 'touchHeld' => false, 'touchTimer' => ''])
        . ' data-tooltip-id="' . attr($tid) . '"'
        . ' data-on:mouseenter="' . attr($show) . '" data-on:mouseleave="' . attr($hide) . '"'
        . ' data-on:focus="' . attr($show) . '" data-on:blur="' . attr($hide) . '"'
        . ' data-on:touchstart="' . attr($touchStart) . '" data-on:touchend="' . attr($touchEnd) . '"'
        . ' tabindex="0" style="' . attr("cursor: pointer; anchor-name: --{$tid};") . '"'
        . ui_class_attr($p->class) . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

function ui_tooltip_content(TooltipContentProps $p, string $children = ''): string
{
    $id = ui_id($p->id);
    $sig = '$' . ui_sig($id);
    $classes = 'bg-popover border outline-none pointer-events-none px-3 py-1.5 rounded-md shadow-md text-popover-foreground text-sm';
    if ($p->useAnchor) {
        $classes = cx($classes, 'anchor-positioned');
        $style = "position: absolute; position-anchor: --{$id}; z-index: 50; ";
        if ($p->side !== '' || $p->align !== '') {
            $style .= ui_tooltip_anchor_css($p->side, $p->align, $p->sideOffset === 0 ? 4 : $p->sideOffset) . ';';
        }
    } else {
        $style = 'position: absolute; z-index: 50;';
    }
    $outside = "{$sig}.touchHeld && !evt.target.closest('[data-tooltip-id=\"{$id}\"]') ? ({$sig}.touchHeld = false, document.getElementById('{$id}').hidePopover()) : null";
    return '<div id="' . attr($id) . '" popover="auto" class="' . attr(cx($classes, $p->class)) . '" style="' . attr($style) . '"'
        . ' data-on:click__outside="' . attr($outside) . '"' . ui_attrs($p->attrs) . '>' . $children . '</div>';
}

<?php
declare(strict_types=1);

use App\View\ui\ButtonProps;

/**
 * Ported from DatastarUI components/button/button.templ (Button, LinkButton) + variants.go
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Keep markup, classes and signal names identical to the source. Fixtures: tests/fixtures/ui/button.*.html
 * Class strings are the post-TwMerge output, split so variant and size strings never conflict with the base.
 * Deviations: href set renders the LinkButton <a> from the same props (Go has two arg structs).
 */
function ui_button(ButtonProps $p, string $children = ''): string
{
    $base = "[&_svg:not([class*='size-'])]:size-4 [&_svg]:pointer-events-none [&_svg]:shrink-0 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 disabled:opacity-50 disabled:pointer-events-none focus-visible:border-ring focus-visible:ring-[3px] font-medium inline-flex items-center justify-center outline-none rounded-md shrink-0 text-sm transition-all whitespace-nowrap";
    $variants = [
        'default' => 'bg-primary focus-visible:ring-ring/50 hover:bg-primary/90 shadow-xs text-primary-foreground',
        'destructive' => 'bg-destructive dark:focus-visible:ring-destructive/40 focus-visible:ring-destructive/20 hover:bg-destructive/90 shadow-xs text-white',
        'outline' => 'bg-background border dark:bg-input/30 dark:border-input dark:hover:bg-input/50 focus-visible:ring-ring/50 hover:bg-accent hover:text-accent-foreground shadow-xs',
        'secondary' => 'bg-secondary focus-visible:ring-ring/50 hover:bg-secondary/80 shadow-xs text-secondary-foreground',
        'ghost' => 'dark:hover:bg-accent/50 focus-visible:ring-ring/50 hover:bg-accent hover:text-accent-foreground',
        'link' => 'focus-visible:ring-ring/50 hover:underline text-primary underline-offset-4',
    ];
    $sizes = [
        'default' => 'gap-2 h-9 has-[>svg]:px-3 px-4 py-2',
        'sm' => 'gap-1.5 h-8 has-[>svg]:px-2.5 px-3',
        'lg' => 'gap-2 h-10 has-[>svg]:px-4 px-6',
        'icon' => 'gap-2 size-9',
    ];
    $classes = cx($base, ui_pick($variants, $p->variant, 'default'), ui_pick($sizes, $p->size, 'default'), $p->class);
    $extra = ui_attrs($p->attrs);

    if ($p->href !== '') {
        return '<a href="' . attr($p->href) . '" class="' . attr($classes) . '"' . ui_attr_if('target', $p->target)
            . ui_attr_if('rel', $p->rel) . $extra . '>' . $children . '</a>';
    }
    if ($p->asChild) {
        // Wrapper compatibility only: renders a <span>, no attribute transfer.
        return '<span class="' . attr($classes) . '"' . ($p->disabled ? ' aria-disabled="true"' : '') . $extra . '>' . $children . '</span>';
    }
    return '<button type="' . attr($p->type !== '' ? $p->type : 'button') . '" class="' . attr($classes) . '"'
        . ui_bool('disabled', $p->disabled) . $extra . '>' . $children . '</button>';
}

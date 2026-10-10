<?php
declare(strict_types=1);

use App\View\ui\BadgeProps;

/**
 * Source: shadcn/ui new-york-v4 badge.tsx (no DatastarUI equivalent). Fixtures are hand written.
 * Stage chips map a stage key to full literal classes using the status tokens in styles/tokens.css
 * (bg-stage-<stage> text-stage-<stage>-foreground, bg-due-overdue ...). Never build those names from parts.
 */
function ui_badge(BadgeProps $p, string $children = ''): string
{
    $base = 'inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none [&>svg]:size-3';
    $variants = [
        'default' => 'border border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90',
        'secondary' => 'border border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90',
        'destructive' => 'border border-transparent bg-destructive text-white focus-visible:ring-destructive/20 dark:bg-destructive/60 dark:focus-visible:ring-destructive/40 [a&]:hover:bg-destructive/90',
        'outline' => 'border border-border text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground',
        'ghost' => 'border border-transparent [a&]:hover:bg-accent [a&]:hover:text-accent-foreground',
        'link' => 'border border-transparent text-primary underline-offset-4 [a&]:hover:underline',
    ];
    $stages = [
        'draft' => 'border border-transparent bg-stage-draft text-stage-draft-foreground',
        'briefed' => 'border border-transparent bg-stage-briefed text-stage-briefed-foreground',
        'in_progress' => 'border border-transparent bg-stage-in_progress text-stage-in_progress-foreground',
        'waiting' => 'border border-transparent bg-stage-waiting text-stage-waiting-foreground',
        'on_hold' => 'border border-transparent bg-stage-on_hold text-stage-on_hold-foreground',
        'in_review' => 'border border-transparent bg-stage-in_review text-stage-in_review-foreground',
        'approved_internal' => 'border border-transparent bg-stage-approved_internal text-stage-approved_internal-foreground',
        'approved_client' => 'border border-transparent bg-stage-approved_client text-stage-approved_client-foreground',
        // Social publishing
        'ready_to_schedule' => 'border border-transparent bg-stage-ready_to_schedule text-stage-ready_to_schedule-foreground',
        'scheduled' => 'border border-transparent bg-stage-scheduled text-stage-scheduled-foreground',
        'live' => 'border border-transparent bg-stage-live text-stage-live-foreground',
        'done' => 'border border-transparent bg-stage-done text-stage-done-foreground',
        'archived' => 'border border-transparent bg-stage-archived text-stage-archived-foreground',
        'cancelled' => 'border border-transparent bg-stage-cancelled text-stage-cancelled-foreground',
        'overdue' => 'border border-transparent bg-due-overdue text-due-overdue-foreground',
        'due_soon' => 'border border-transparent bg-due-soon text-due-soon-foreground',
    ];
    $variantClasses = $p->stage !== '' ? ($stages[$p->stage] ?? $stages['draft']) : ui_pick($variants, $p->variant, 'default');
    $slotAttrs = ' data-slot="badge" data-variant="' . attr($p->stage !== '' ? 'stage-' . $p->stage : $p->variant) . '"';
    $classes = cx($base, $variantClasses, $p->class);
    if ($p->href !== '') {
        return '<a href="' . attr($p->href) . '"' . $slotAttrs . ' class="' . attr($classes) . '"' . ui_attrs($p->attrs) . '>' . $children . '</a>';
    }
    return '<span' . $slotAttrs . ' class="' . attr($classes) . '"' . ui_attrs($p->attrs) . '>' . $children . '</span>';
}

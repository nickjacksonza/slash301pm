<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\BadgeProps;
use App\View\ui\ComboboxProps;
use App\View\ui\PartProps;
use App\View\ui\SelectOption;
use App\View\ui\SkeletonProps;
use App\View\ui\TableHeaderProps;
use App\View\ui\TableProps;
use App\View\ui\TableRowProps;

// Components with no Go reference: hand written fixtures (badge, skeleton, table) and structural render tests (combobox).
$p = static fn (): PartProps => new PartProps();

return ui_fx_cases('extras', [
    'badge.default' => static fn (): string => ui_badge(new BadgeProps(), 'New'),
    'badge.outline.link' => static fn (): string => ui_badge(new BadgeProps(variant: 'outline', href: '/x'), 'Go'),
    'badge.stage.in_progress' => static fn (): string => ui_badge(new BadgeProps(stage: 'in_progress'), 'In progress'),
    'skeleton' => static fn (): string => ui_skeleton(new SkeletonProps(class: 'h-4 w-[250px]')),
    'table' => static fn (): string => ui_table(
        new TableProps(),
        ui_table_caption($p(), 'Jobs')
        . ui_table_header(new TableHeaderProps(), ui_table_row(new TableRowProps(), ui_table_head($p(), 'Title')))
        . ui_table_body($p(), ui_table_row(new TableRowProps(selected: true), ui_table_cell($p(), 'Row')))
        . ui_table_footer($p(), ui_table_row(new TableRowProps(), ui_table_cell($p(), '1')))
    ),
]) + [
    'every stage and due state has a distinct token class' => static function (): void {
        $stages = ['draft', 'briefed', 'in_progress', 'waiting', 'on_hold', 'in_review', 'approved_internal', 'approved_client', 'done', 'archived', 'cancelled'];
        foreach ($stages as $s) {
            $h = ui_badge(new BadgeProps(stage: $s), 'x');
            t_contains("bg-stage-$s text-stage-$s-foreground", $h);
        }
        t_contains('bg-due-overdue text-due-overdue-foreground', ui_badge(new BadgeProps(stage: 'overdue'), 'x'));
        t_contains('bg-due-soon text-due-soon-foreground', ui_badge(new BadgeProps(stage: 'due_soon'), 'x'));
        t_contains('bg-stage-draft', ui_badge(new BadgeProps(stage: 'unknown'), 'x'), 'unknown stage falls back to draft');
    },
    'every badge variant renders its own classes' => static function (): void {
        $seen = [];
        foreach (['default', 'secondary', 'destructive', 'outline', 'ghost', 'link'] as $v) {
            $seen[ui_badge(new BadgeProps(variant: $v), 'x')] = true;
        }
        t_eq(6, count($seen));
    },
    'table compact density and sticky header' => static function (): void {
        $t = ui_table(new TableProps(density: 'compact', containerClass: 'max-h-[70vh] overflow-y-auto'), '');
        t_contains('data-density="compact"', $t);
        t_contains(e('[&_td]:py-1'), $t);
        t_contains('max-h-[70vh] overflow-y-auto', $t);
        t_contains('sticky top-0 z-10', ui_table_header(new TableHeaderProps(sticky: true), ''));
        t_not_contains('sticky', ui_table_header(new TableHeaderProps(), ''));
    },
    'combobox wires signals, filter, keys and hidden input' => static function (): void {
        $h = ui_combobox(new ComboboxProps(
            id: 'owner', name: 'owner_id', placeholder: 'Pick owner', value: 'u2',
            options: [new SelectOption('u1', 'Ann'), new SelectOption('u2', 'Bo'), new SelectOption('u3', 'Cy', disabled: true)],
        ));
        t_contains('data-combobox-id="owner"', $h);
        t_contains('data-signals="{&quot;owner&quot;:{&quot;open&quot;:false,&quot;value&quot;:&quot;u2&quot;,&quot;label&quot;:&quot;Bo&quot;,&quot;query&quot;:&quot;&quot;,&quot;highlighted&quot;:-1}}"', $h);
        t_contains('name="owner_id"', $h);
        t_contains('data-bind="$owner.query"', $h);
        t_contains('data-show="&quot;Ann&quot;.toLowerCase().includes($owner.query.toLowerCase())"', $h);
        t_contains('data-show="![&quot;Ann&quot;,&quot;Bo&quot;,&quot;Cy&quot;].some(l =&gt; l.toLowerCase().includes($owner.query.toLowerCase()))"', $h);
        t_contains(e("evt.key === 'ArrowDown'"), $h);
        t_contains(e("evt.key === 'ArrowUp'"), $h);
        t_contains(e("evt.key === 'Enter'"), $h);
        t_contains(e("evt.key === 'Escape'"), $h);
        t_not_contains('; $owner.query', substr($h, 0, strpos($h, 'data-slot="combobox-content"')), 'no semicolons inside ternary groups');
        t_contains('data-index="2" data-value="u3"', $h);
        t_eq(2, substr_count($h, 'data-on:click="$owner.value = '), 'disabled option has no click handler');
        t_not_contains('data-on:input__', $h);
    },
];

<?php
declare(strict_types=1);

use App\View\ui\ButtonProps;
use App\View\ui\NativeCheckProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\SegmentItem;
use App\View\ui\SelectOption;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'button has approve, feedback and reject variants' => function (): void {
        t_contains('bg-green-700 hover:bg-green-800', ui_button(new ButtonProps(variant: 'approve'), 'Go'));
        t_contains('bg-orange-700 hover:bg-orange-800', ui_button(new ButtonProps(variant: 'feedback'), 'Go'));
        t_contains('bg-red-700 hover:bg-red-800', ui_button(new ButtonProps(variant: 'reject', class: 'w-full'), 'Go'));
        t_contains('w-full', ui_button(new ButtonProps(variant: 'reject', class: 'w-full'), 'Go'));
    },
    'button attrs are escaped and passed through' => function (): void {
        $h = ui_button(new ButtonProps(attrs: ['data-on:click' => "x = '<b>'", 'aria-keyshortcuts' => 'k ArrowUp']), 'A<B');
        t_contains('data-on:click="x = &apos;&lt;b&gt;&apos;"', $h);
        t_contains('aria-keyshortcuts="k ArrowUp"', $h);
        t_contains('A&lt;B', ui_button(new ButtonProps(), e('A<B')));
    },
    'segmented: links mark the current one with aria-current' => function (): void {
        $h = partial_segmented([
            new SegmentItem('Grid', href: '/g', current: true),
            new SegmentItem('Board <x>', href: '/b?a=1&b=2'),
        ], 'Job views', nav: true);
        t_contains('<nav aria-label="Job views"', $h);
        t_contains('href="/g"', $h);
        t_contains('aria-current="page"', $h);
        t_eq(1, substr_count($h, 'aria-current'));
        t_contains('Board &lt;x&gt;', $h);
        t_contains('href="/b?a=1&amp;b=2"', $h);
        t_contains('bg-accent text-accent-foreground', $h);
    },
    'segmented: buttons carry counts, attrs and the Datastar active expression' => function (): void {
        $h = partial_segmented([
            new SegmentItem('Mine', 3, activeExpr: "\$_rf === 'mine'", attrs: ['data-on:click' => "\$_rf = 'mine'"]),
            new SegmentItem('Copy only', disabled: true),
        ], 'Show', compact: true);
        t_contains('role="group" aria-label="Show"', $h);
        t_contains('<span class="text-xs tabular-nums opacity-80">3</span>', $h);
        t_contains('data-on:click="$_rf = &apos;mine&apos;"', $h);
        t_contains('data-attr:aria-pressed=', $h);
        t_contains('data-class=', $h);
        t_contains('disabled', $h);
        t_contains('px-3 py-1 text-xs', $h);
        t_not_contains('<a ', $h);
    },
    'segmented: hostile label is escaped' => function (): void {
        $h = partial_segmented([new SegmentItem('<script>alert(1)</script>')], '"><img>');
        t_not_contains('<script>', $h);
        t_not_contains('"><img>', $h);
    },
    'empty state escapes text and has the dashed rounded-xl box' => function (): void {
        $h = partial_empty_state('Nothing <here>');
        t_contains('rounded-xl border border-dashed', $h);
        t_contains('Nothing &lt;here&gt;', $h);
        t_contains('px-3 py-6 text-center', partial_empty_state('x', 'lg'));
        t_contains('<a href="/x">go</a>', partial_empty_state_html('<a href="/x">go</a>', 'sm', ['id' => 'e']));
        t_contains('id="e"', partial_empty_state_html('x', 'sm', ['id' => 'e']));
    },
    'metric card escapes and keeps the hint as title' => function (): void {
        $h = partial_metric_card(7, 'Over<due>', 'Open "jobs"');
        t_contains('>7</p>', $h);
        t_contains('Over&lt;due&gt;', $h);
        t_contains('title="Open &quot;jobs&quot;"', $h);
        t_not_contains('title=', partial_metric_card('0', 'x'));
    },
    'page toolbar: title left, actions right, text escaped' => function (): void {
        $h = partial_page_toolbar('A <b>', '<a href="/x">Brief</a>', e('J-1 · Brand'), 'Sub <i>', '<span id="bx">chip</span>', true);
        t_contains('A &lt;b&gt;', $h);
        t_contains('Sub &lt;i&gt;', $h);
        t_contains('text-2xl', $h);
        t_contains('<a href="/x">Brief</a>', $h);
        t_contains('<span id="bx">chip</span>', $h);
        t_true(strpos($h, 'A &lt;b&gt;') < strpos($h, '<a href="/x">'), 'title before actions');
        t_not_contains('text-2xl', partial_page_toolbar('T'));
    },
    'native check passes attrs and checked state' => function (): void {
        $h = ui_native_check(new NativeCheckProps(value: 'a"b', checked: true, attrs: ['data-bind' => 'q.stages', 'data-on:change' => '@get("/x")']));
        t_contains('type="checkbox"', $h);
        t_contains('value="a&quot;b"', $h);
        t_contains(' checked', $h);
        t_contains('data-bind="q.stages"', $h);
        t_contains('data-on:change="@get(&quot;/x&quot;)"', $h);
        t_not_contains(' checked', ui_native_check(new NativeCheckProps()));
    },
    'native select is a pill and passes attrs' => function (): void {
        $h = ui_native_select(new NativeSelectProps(id: 's', options: [new SelectOption('a', 'A')], attrs: ['data-bind' => 'q.x']));
        t_contains('rounded-full', $h);
        t_not_contains('rounded-md', $h);
        t_contains('data-bind="q.x"', $h);
    },
];

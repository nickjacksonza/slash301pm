<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\ButtonProps;

// Every variant x size, plus disabled, submit with attrs, asChild and the anchor form. Fixtures come from the Go renderer.
$cases = [];
foreach (['default', 'destructive', 'outline', 'secondary', 'ghost', 'link'] as $variant) {
    foreach (['default', 'sm', 'lg', 'icon'] as $size) {
        $cases["button.$variant.$size"] = static fn (): string => ui_button(new ButtonProps(variant: $variant, size: $size), 'Save');
    }
}
$cases['button.disabled'] = static fn (): string => ui_button(new ButtonProps(disabled: true), 'Save');
$cases['button.submit'] = static fn (): string => ui_button(
    new ButtonProps(type: 'submit', class: 'w-full', attrs: ['data-on:click' => "@post('/api/save')"]),
    'Save'
);
$cases['button.aschild'] = static fn (): string => ui_button(new ButtonProps(asChild: true), 'Wrapped');
$cases['button.aschild.disabled'] = static fn (): string => ui_button(new ButtonProps(asChild: true, disabled: true), 'Wrapped');
$cases['button.anchor'] = static fn (): string => ui_button(
    new ButtonProps(href: '/projects', variant: 'outline', target: '_blank', rel: 'noopener'),
    'Projects'
);
$cases['button.anchor.plain'] = static fn (): string => ui_button(new ButtonProps(href: '/projects'), 'Projects');

return ui_fx_cases('button', $cases) + [
    'button unknown variant falls back to default' => static function (): void {
        t_eq(ui_button(new ButtonProps(variant: 'nope', size: 'nope'), 'x'), ui_button(new ButtonProps(), 'x'));
    },
    'button rejects bad attr names' => static function (): void {
        t_throws(static fn () => ui_button(new ButtonProps(attrs: ['onclick' => 'x']), ''), InvalidArgumentException::class);
        t_throws(static fn () => ui_button(new ButtonProps(attrs: ['Bad Key' => 'x']), ''), InvalidArgumentException::class);
        t_throws(static fn () => ui_button(new ButtonProps(attrs: ['a"b' => 'x']), ''), InvalidArgumentException::class);
    },
    'button attr values are escaped' => static function (): void {
        $h = ui_button(new ButtonProps(attrs: ['data-x' => '"><b>']), '');
        t_not_contains('"><b>', $h);
        t_contains('data-x="&quot;&gt;&lt;b&gt;"', $h);
    },
];

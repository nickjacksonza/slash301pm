<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\AvatarProps;
use App\View\ui\PartProps;
use App\View\ui\TabsContentProps;
use App\View\ui\TabsProps;
use App\View\ui\TabsTriggerProps;

$p = static fn (string $class = '', array $attrs = []): PartProps => new PartProps($class, $attrs);

return ui_fx_cases('card', [
    'card' => static fn (): string => ui_card($p(), ui_card_header($p(), ui_card_title($p(), 'Project') . ui_card_description($p(), 'Details') . ui_card_action($p(), 'Act'))
        . ui_card_content($p(), 'Body') . ui_card_footer($p(), 'Footer')),
    'card.extra' => static fn (): string => ui_card($p('max-w-sm', ['id' => 'c1', 'data-x' => 'y']), 'Hi'),
    'avatar' => static fn (): string => ui_avatar(new AvatarProps(backgroundColor: '#3b6b5a'), 'NJ'),
    'avatar.plain' => static fn (): string => ui_avatar(new AvatarProps(class: 'size-10'), 'NJ'),
    'tabs' => static fn (): string => ui_tabs(
        new TabsProps(id: 'views', defaultValue: 'list'),
        ui_tabs_list($p(), ui_tabs_trigger(new TabsTriggerProps(id: 'views', value: 'list'), 'List') . ui_tabs_trigger(new TabsTriggerProps(id: 'views', value: 'board', disabled: true), 'Board'))
        . ui_tabs_content(new TabsContentProps(id: 'views', value: 'list'), 'List view')
        . ui_tabs_content(new TabsContentProps(id: 'views', value: 'board'), 'Board view')
    ),
    'tabs.value' => static fn (): string => ui_tabs(new TabsProps(id: 't-two', defaultValue: 'a', value: 'b', class: 'w-full')),
]) + [
    'avatar derives initials and a stable colour from a name' => static function (): void {
        $a = ui_avatar(new AvatarProps(name: 'Nick Jackson'));
        t_contains('>NJ</div>', $a);
        t_eq($a, ui_avatar(new AvatarProps(name: 'nick jackson')), 'same colour regardless of case');
        t_contains('background-color: #', $a);
        t_contains('>M</div>', ui_avatar(new AvatarProps(name: 'madonna')));
        t_contains('>?</div>', ui_avatar(new AvatarProps(name: '  ', backgroundColor: '#112233')));
    },
    'avatar refuses an unsafe colour' => static function (): void {
        t_throws(static fn () => ui_avatar(new AvatarProps(backgroundColor: 'red;background:url(x)'), 'x'), InvalidArgumentException::class);
    },
    'tabs refuse unsafe values and ids' => static function (): void {
        t_throws(static fn () => ui_tabs_trigger(new TabsTriggerProps(id: 'v', value: "a'b"), ''), InvalidArgumentException::class);
        t_throws(static fn () => ui_tabs(new TabsProps(id: 'v"x'), ''), InvalidArgumentException::class);
    },
];

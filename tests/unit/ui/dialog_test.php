<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\DialogTriggerProps;
use App\View\ui\PartProps;

$p = static fn (): PartProps => new PartProps();

$cases = [
    'dialog' => static fn (): string => ui_dialog(
        new DialogProps(id: 'confirm-delete'),
        ui_dialog_header($p(), ui_dialog_title($p(), 'Delete project') . ui_dialog_description($p(), 'This cannot be undone.'))
        . ui_dialog_content($p(), 'Are you sure?')
        . ui_dialog_footer(
            $p(),
            ui_dialog_close(new DialogCloseProps(dialogId: 'confirm-delete', variant: 'outline'), 'Cancel')
            . ui_dialog_close(new DialogCloseProps(dialogId: 'confirm-delete', variant: 'destructive', returnValue: 'confirmed'), 'Delete')
        )
    ),
    'dialog.open' => static fn (): string => ui_dialog(new DialogProps(id: 'welcome', defaultOpen: true), 'Hello'),
    'dialog.trigger' => static fn (): string => ui_dialog_trigger(new DialogTriggerProps(dialogId: 'confirm-delete', class: 'underline'), 'Open'),
    'dialog.trigger.aschild' => static fn (): string => ui_dialog_trigger(new DialogTriggerProps(dialogId: 'confirm-delete', asChild: true), 'Open'),
    'dialog.extra' => static fn (): string => ui_dialog(new DialogProps(id: 'd2', class: 'max-w-xl'), 'Hi'),
];
foreach (['default', 'destructive', 'outline', 'secondary', 'ghost', 'link'] as $variant) {
    $cases["dialog.close.$variant"] = static fn (): string => ui_dialog_close(new DialogCloseProps(dialogId: 'd1', variant: $variant), 'Close');
}

return ui_fx_cases('dialog', $cases) + [
    'dialog return value is a quoted JS string' => static function (): void {
        $h = ui_dialog_close(new DialogCloseProps(dialogId: 'd1', returnValue: "it's <b>"), 'x');
        t_not_contains('<b>', $h);
        t_contains("returnValue = &apos;", $h);
    },
    'dialog attrs land on the panel' => static function (): void {
        $h = ui_dialog(new DialogProps(id: 'd2', attrs: ['aria-labelledby' => 't1']), '');
        t_true(preg_match('/<div id="d2"[^>]* aria-labelledby="t1"/', $h) === 1, 'attrs should sit on the element with the id');
    },
    'dialog refuses an unsafe id' => static function (): void {
        t_throws(static fn () => ui_dialog(new DialogProps(id: "a'];alert(1)//"), ''), InvalidArgumentException::class);
    },
];

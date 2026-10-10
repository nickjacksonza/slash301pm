<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\ToastProps;
use App\View\ui\ToastRegionProps;

$cases = [
    'toast.container' => static fn (): string => ui_toast_region(
        new ToastRegionProps(id: 'toasts', position: 'bottom-right'),
        ui_toast(new ToastProps(id: 'saved', title: 'Saved', description: 'Your changes were saved.', kind: 'success'))
    ),
    'toast.container.default' => static fn (): string => ui_toast_region(new ToastRegionProps(id: 'toasts')),
    'toast.item.titleonly' => static fn (): string => ui_toast(new ToastProps(id: 't-2', title: 'Only title', class: 'x', attrs: ['data-x' => 'y'])),
    'toast.trigger' => static fn (): string => ui_toast_trigger('saved', 2000, 'Show'),
];
foreach (['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'] as $pos) {
    $cases["toast.container.pos.$pos"] = static fn (): string => ui_toast_region(new ToastRegionProps(id: 't', position: $pos, class: 'extra'));
}
foreach (['default', 'success', 'destructive', 'info'] as $variant) {
    $cases["toast.item.$variant"] = static fn (): string => ui_toast(new ToastProps(id: 't1', title: 'Heads up', description: 'Something happened.', kind: $variant));
}

return ui_fx_cases('toast', $cases) + [
    'toast kinds map to upstream variants' => static function (): void {
        $mk = static fn (string $kind): string => ui_toast(new ToastProps(id: 't1', title: 'x', kind: $kind));
        t_eq($mk('success'), $mk('ok'));
        t_eq($mk('destructive'), $mk('error'));
        t_contains('bg-due-soon', $mk('warn'));
        t_contains('bg-blue-50', $mk('info'));
        t_eq($mk('default'), $mk('whatever'));
    },
    'server toast opens at once and auto dismisses with core Datastar' => static function (): void {
        $h = ui_toast(new ToastProps(id: 'toast-abc', title: 'Saved', kind: 'ok', open: true, durationMs: 4000));
        t_contains('data-signals="{&quot;toast_abc&quot;:{&quot;open&quot;:true}}"', $h);
        t_not_contains('display: none', $h);
        t_contains('data-init="setTimeout(() =&gt; { $toast_abc.open = false; setTimeout(() =&gt; el.remove(), 300) }, 4000)"', $h);
    },
    'closed toast has no auto dismiss' => static function (): void {
        t_not_contains('data-init', ui_toast(new ToastProps(id: 't1', title: 'x', durationMs: 4000)));
        t_not_contains('data-init', ui_toast(new ToastProps(id: 't1', title: 'x', open: true)));
    },
];

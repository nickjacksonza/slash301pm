<?php
declare(strict_types=1);

use App\Domain\DemoSwitch;

return [
    'switch allowed only in demo mode with a demo-picker session' => function (): void {
        t_true(DemoSwitch::can(true, true)->allowed);
        t_true(!DemoSwitch::can(false, true)->allowed);
        t_true(!DemoSwitch::can(true, false)->allowed);
        t_true(!DemoSwitch::can(false, false)->allowed);
        t_contains('Demo mode', DemoSwitch::can(false, true)->reason);
    },
];

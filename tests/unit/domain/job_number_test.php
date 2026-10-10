<?php
declare(strict_types=1);

use App\Domain\JobNumber;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'job number: PREFIX-NNN' => function (): void {
        foreach ([['MERC', 1, 'MERC-001'], ['MERC', 42, 'MERC-042'], ['AURU', 999, 'AURU-999'], ['AURU', 1000, 'AURU-1000']] as [$p, $n, $want]) {
            t_eq($want, JobNumber::format($p, $n));
        }
        t_eq(3, JobNumber::numberOf('MERC', 'MERC-003'));
        t_eq(null, JobNumber::numberOf('MERC', 'MERCX-003'));
        t_eq(null, JobNumber::numberOf('MERC', 'MERC-00a'));
        t_eq(null, JobNumber::numberOf('MER', 'MERC-003'));
        t_eq(4, JobNumber::seed('MERC', ['MERC-001', 'MERC-003', 'HARB-009', 'MERC-x']));
        t_eq(1, JobNumber::seed('NEW', []));
    },
];

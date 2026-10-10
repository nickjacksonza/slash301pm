<?php
declare(strict_types=1);

use App\Domain\BriefVersion;
use App\Domain\BumpLevel;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'brief version: parse and format' => function (): void {
        $ok = [['1.0.0', '1.0.0'], ['v1.2.3', '1.2.3'], [' 0.1.0 ', '0.1.0'], ['10.20.300', '10.20.300']];
        foreach ($ok as [$in, $want]) {
            $v = BriefVersion::parse($in);
            t_true($v !== null, $in);
            t_eq($want, $v->format(), $in);
            t_eq('v' . $want, $v->label());
        }
        foreach (['', '1', '1.2', '1.2.3.4', '01.0.0', '1.-1.0', 'a.b.c', '1.0.0-rc1', 'v', '1234567.0.0'] as $bad) {
            t_eq(null, BriefVersion::parse($bad), $bad);
        }
    },
    'brief version: bump resets the parts to the right' => function (): void {
        $cases = [
            ['1.0.0', 'major', '2.0.0'], ['1.0.0', 'minor', '1.1.0'], ['1.0.0', 'patch', '1.0.1'],
            ['3.2.3', 'major', '4.0.0'], ['3.2.3', 'minor', '3.3.0'], ['3.2.3', 'patch', '3.2.4'], ['0.1.0', 'major', '1.0.0'],
        ];
        foreach ($cases as [$from, $level, $want]) {
            t_eq($want, BriefVersion::parse($from)->bump(BumpLevel::from($level))->format(), "$from $level");
        }
        t_eq('0.1.0', BriefVersion::draft()->format());
        t_true(BriefVersion::draft()->isDraft());
        t_eq('1.0.0', BriefVersion::first()->format());
        t_true(!BriefVersion::first()->isDraft());
    },
    'brief version: compare' => function (): void {
        $cases = [['1.0.0', '1.0.0', 0], ['1.0.1', '1.0.0', 1], ['1.0.0', '1.1.0', -1], ['2.0.0', '1.9.9', 1], ['1.10.0', '1.9.0', 1], ['0.1.0', '1.0.0', -1]];
        foreach ($cases as [$a, $b, $want]) {
            t_eq($want, BriefVersion::parse($a)->compare(BriefVersion::parse($b)), "$a vs $b");
        }
        t_true(BriefVersion::parse('1.2.3')->equals(new BriefVersion(1, 2, 3)));
    },
];

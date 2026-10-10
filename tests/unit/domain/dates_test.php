<?php
declare(strict_types=1);

use App\Domain\Dates;
use App\Domain\DueBucket;

require_once dirname(__DIR__, 2) . '/support/app.php';

function dt(string $s): DateTimeImmutable
{
    return new DateTimeImmutable($s);   // offsets in the string are honoured
}

return [
    'today: 23:30 UTC is already tomorrow in SAST, 21:59 UTC is not' => function (): void {
        t_eq('2026-10-10', Dates::today(dt('2026-10-09 23:30:00 UTC')));
        t_eq('2026-10-09', Dates::today(dt('2026-10-09 21:59:59 UTC')));
        t_eq('2026-10-10', Dates::today(dt('2026-10-10 00:00:00 +02:00')));
        t_eq('2027-01-01', Dates::today(dt('2026-12-31 22:00:00 UTC')));
    },
    'localDate converts stored UTC stamps' => function (): void {
        t_eq('2026-10-10', Dates::localDate('2026-10-09 22:00:00'));
        t_eq('2026-10-09', Dates::localDate('2026-10-09 21:59:59'));
        t_eq(null, Dates::localDate('junk'));
    },
    'part of day follows SAST' => function (): void {
        t_eq('morning', Dates::partOfDay(dt('2026-10-09 07:59:00 UTC')));   // 09:59 SAST
        t_eq('afternoon', Dates::partOfDay(dt('2026-10-09 10:00:00 UTC')));  // 12:00 SAST
        t_eq('evening', Dates::partOfDay(dt('2026-10-09 16:00:00 UTC')));    // 18:00 SAST
    },
    'business days: weekends and holidays are not' => function (): void {
        $cases = [
            '2026-10-09' => true, '2026-10-10' => false, '2026-10-11' => false, '2026-10-12' => true,
            '2026-01-01' => false, '2026-04-03' => false, '2026-04-06' => false, '2026-04-27' => false, '2026-05-01' => false,
            '2026-06-16' => false, '2026-09-24' => false, '2026-12-16' => false, '2026-12-25' => false,
            '2026-03-21' => false,   // Saturday
            '2026-08-09' => false,   // Sunday
            '2026-08-10' => false,   // Sunday holiday observed on Monday
            '2026-08-11' => true,
            '2027-03-22' => false,   // Human Rights Day fell on a Sunday
            '2027-03-26' => false, '2027-03-29' => false, '2027-12-27' => false, '2027-12-24' => true,
            '2025-12-25' => true,    // outside the table: weekday counts
        ];
        foreach ($cases as $d => $expected) {
            t_eq($expected, Dates::isBusinessDay($d), $d);
        }
    },
    'addBusinessDays skips weekends and holidays' => function (): void {
        t_eq('2026-10-14', Dates::addBusinessDays('2026-10-09', 3));   // Fri + 3 = Wed
        t_eq('2026-10-14', Dates::addBusinessDays('2026-10-10', 3));   // from a Saturday
        t_eq('2026-10-09', Dates::addBusinessDays('2026-10-09', 0));
        t_eq('2026-08-13', Dates::addBusinessDays('2026-08-07', 3));   // Fri, skips Sat Sun and Mon 10 Aug
        t_eq('2026-04-09', Dates::addBusinessDays('2026-04-02', 3));   // Good Friday and Family Day skipped
        t_eq('2026-12-29', Dates::addBusinessDays('2026-12-23', 3));   // Wed 23: Thu 24, Fri 25 holiday, Mon 28, Tue 29
    },
    'bucket: table of dates around a Friday' => function (): void {
        $now = dt('2026-10-09 09:00:00 +02:00');
        $cases = [
            [null, DueBucket::None], ['', DueBucket::None], ['not a date', DueBucket::None], ['2026-02-30', DueBucket::None],
            ['2026-10-08', DueBucket::Overdue], ['2026-09-01', DueBucket::Overdue],
            ['2026-10-09', DueBucket::Today], ['2026-10-09T00:00:00Z', DueBucket::Today],
            ['2026-10-10', DueBucket::Next3BusinessDays], ['2026-10-12', DueBucket::Next3BusinessDays], ['2026-10-14', DueBucket::Next3BusinessDays],
            ['2026-10-15', DueBucket::Later], ['2027-01-01', DueBucket::Later],
        ];
        foreach ($cases as [$due, $expected]) {
            t_eq($expected, Dates::bucket($due, $now), (string) $due);
        }
    },
    'bucket: timezone boundary, 23:30 UTC on the 9th is the 10th in SAST' => function (): void {
        $now = dt('2026-10-09 23:30:00 UTC');
        t_eq(DueBucket::Overdue, Dates::bucket('2026-10-09', $now));
        t_eq(DueBucket::Today, Dates::bucket('2026-10-10', $now));
        t_eq(DueBucket::Next3BusinessDays, Dates::bucket('2026-10-14', $now));
        t_eq(DueBucket::Later, Dates::bucket('2026-10-15', $now));
        $early = dt('2026-10-09 21:59:00 UTC');   // still the 9th in SAST
        t_eq(DueBucket::Today, Dates::bucket('2026-10-09', $early));
    },
    'bucket: a holiday pushes the three-day window out' => function (): void {
        $now = dt('2026-08-07 08:00:00 +02:00');   // Friday before the 10 Aug holiday
        t_eq(DueBucket::Next3BusinessDays, Dates::bucket('2026-08-13', $now));
        t_eq(DueBucket::Later, Dates::bucket('2026-08-14', $now));
    },
    'week bounds and day counts' => function (): void {
        t_eq('2026-10-05', Dates::weekStart('2026-10-09'));
        t_eq('2026-10-11', Dates::weekEnd('2026-10-09'));
        t_eq('2026-10-05', Dates::weekStart('2026-10-11'));
        t_eq(-4, Dates::daysBetween('2026-10-10', '2026-10-06'));
        t_eq(3, Dates::daysBetween('2026-10-10', '2026-10-13'));
        t_eq('Saturday 10 October 2026', Dates::longDate(dt('2026-10-09 23:30:00 UTC')));
    },
];

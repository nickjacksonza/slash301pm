<?php
declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Business-day and "today" rules for Africa/Johannesburg (SAST, UTC+2, no DST).
 * Pure: the caller passes $now. Dates are 'Y-m-d' strings; stored timestamps
 * are UTC 'Y-m-d H:i:s'. Business days are Monday to Friday minus South African
 * public holidays; a holiday on a Sunday moves to the Monday (Public Holidays
 * Act). Years outside the table below count weekends only: add the next year
 * before it starts. Go: package domain, funcs on time.Time with a fixed location.
 */
final class Dates
{
    public const TZ = 'Africa/Johannesburg';

    /**
     * Gazetted dates before the Sunday rule (Easter-based ones included).
     * Add 2028 and later here. A one-off holiday (an election day) is added by hand.
     * @var array<string,string>
     */
    private const BASE_HOLIDAYS = [
        '2026-01-01' => "New Year's Day",
        '2026-03-21' => 'Human Rights Day',
        '2026-04-03' => 'Good Friday',
        '2026-04-06' => 'Family Day',
        '2026-04-27' => 'Freedom Day',
        '2026-05-01' => "Workers' Day",
        '2026-06-16' => 'Youth Day',
        '2026-08-09' => "National Women's Day",
        '2026-09-24' => 'Heritage Day',
        '2026-12-16' => 'Day of Reconciliation',
        '2026-12-25' => 'Christmas Day',
        '2026-12-26' => 'Day of Goodwill',
        '2027-01-01' => "New Year's Day",
        '2027-03-21' => 'Human Rights Day',
        '2027-03-26' => 'Good Friday',
        '2027-03-29' => 'Family Day',
        '2027-04-27' => 'Freedom Day',
        '2027-05-01' => "Workers' Day",
        '2027-06-16' => 'Youth Day',
        '2027-08-09' => "National Women's Day",
        '2027-09-24' => 'Heritage Day',
        '2027-12-16' => 'Day of Reconciliation',
        '2027-12-25' => 'Christmas Day',
        '2027-12-26' => 'Day of Goodwill',
    ];

    /** @return array<string,string> every observed holiday date => name, the Sunday-to-Monday days included */
    public static function holidays(): array
    {
        $out = [];
        foreach (self::BASE_HOLIDAYS as $ymd => $name) {
            $out[$ymd] = $name;
        }
        foreach (self::BASE_HOLIDAYS as $ymd => $name) {
            if (self::weekday($ymd) === 7) {
                $monday = self::shift($ymd, 1);
                $out[$monday] = $name . ' (observed)';
            }
        }
        ksort($out);
        return $out;
    }

    public static function holidayName(string $ymd): ?string
    {
        return self::holidays()[$ymd] ?? null;
    }

    /** Today's date in SAST. */
    public static function today(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone(self::TZ))->format('Y-m-d');
    }

    /** A stored UTC timestamp as an SAST date; null when it is not one. */
    public static function localDate(string $utcStamp): ?string
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utcStamp, new DateTimeZone('UTC'));
        return $d === false ? null : $d->setTimezone(new DateTimeZone(self::TZ))->format('Y-m-d');
    }

    /** 'Saturday 10 October 2026' in SAST. */
    public static function longDate(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone(self::TZ))->format('l j F Y');
    }

    /** 'morning', 'afternoon' or 'evening' at SAST. */
    public static function partOfDay(DateTimeImmutable $now): string
    {
        $h = (int) $now->setTimezone(new DateTimeZone(self::TZ))->format('G');
        return $h < 12 ? 'morning' : ($h < 18 ? 'afternoon' : 'evening');
    }

    /** The date part of a stored value when it is a real 'Y-m-d' (also from 'Y-m-dTHH...'), else null. */
    public static function normalize(?string $v): ?string
    {
        if ($v === null || strlen($v) < 10) {
            return null;
        }
        $ymd = substr($v, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('UTC'));
        return $d !== false && $d->format('Y-m-d') === $ymd ? $ymd : null;
    }

    /** ISO weekday: 1 Monday to 7 Sunday. */
    public static function weekday(string $ymd): int
    {
        return (int) self::at($ymd)->format('N');
    }

    public static function isBusinessDay(string $ymd): bool
    {
        return self::weekday($ymd) <= 5 && !isset(self::holidays()[$ymd]);
    }

    /** The date n business days after $ymd (n >= 0). $ymd itself need not be a business day. */
    public static function addBusinessDays(string $ymd, int $n): string
    {
        $d = $ymd;
        $left = max(0, $n);
        while ($left > 0) {
            $d = self::shift($d, 1);
            if (self::isBusinessDay($d)) {
                $left--;
            }
        }
        return $d;
    }

    /** Whole calendar days from $from to $to (negative when $to is earlier). */
    public static function daysBetween(string $from, string $to): int
    {
        return (int) round((self::at($to)->getTimestamp() - self::at($from)->getTimestamp()) / 86400);
    }

    public static function weekStart(string $ymd): string
    {
        return self::shift($ymd, 1 - self::weekday($ymd));
    }

    public static function weekEnd(string $ymd): string
    {
        return self::shift($ymd, 7 - self::weekday($ymd));
    }

    /** Overdue before today, today, up to 3 business days ahead, later; none for no or junk date. */
    public static function bucket(?string $due, DateTimeImmutable $now): DueBucket
    {
        $due = self::normalize($due);
        if ($due === null) {
            return DueBucket::None;
        }
        $today = self::today($now);
        if ($due < $today) {
            return DueBucket::Overdue;
        }
        if ($due === $today) {
            return DueBucket::Today;
        }
        return $due <= self::addBusinessDays($today, 3) ? DueBucket::Next3BusinessDays : DueBucket::Later;
    }

    private static function at(string $ymd): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('UTC'));
        if ($d === false) {
            throw new \InvalidArgumentException('Not a Y-m-d date: ' . $ymd);
        }
        return $d;
    }

    private static function shift(string $ymd, int $days): string
    {
        return self::at($ymd)->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}

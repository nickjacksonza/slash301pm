<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Due date filter. Dates are SAST 'Y-m-d' (Dates::today). Overdue only
 * counts open jobs. this_week runs from today to Sunday, next_14 from today
 * to 14 days ahead. Go: type DueWindow string.
 */
enum DueWindow: string
{
    case Overdue = 'overdue';
    case Today = 'today';
    case ThisWeek = 'this_week';
    case Next14 = 'next_14';
    case NoDate = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Overdue => 'Overdue',
            self::Today => 'Due today',
            self::ThisWeek => 'Due this week',
            self::Next14 => 'Next 14 days',
            self::NoDate => 'No due date',
        };
    }

    /**
     * The inclusive date range for $today: [from, to]; null ends are open.
     * NoDate has no range (the Store tests for a missing date instead).
     * @return array{0:?string,1:?string}
     */
    public function range(string $today): array
    {
        return match ($this) {
            self::Overdue => [null, self::shift($today, -1)],
            self::Today => [$today, $today],
            self::ThisWeek => [$today, Dates::weekEnd($today)],
            self::Next14 => [$today, self::shift($today, 14)],
            self::NoDate => [null, null],
        };
    }

    /** $ymd moved by whole days (UTC arithmetic on a date, so no DST surprises). */
    public static function shift(string $ymd, int $days): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new \DateTimeZone('UTC'));
        if ($d === false) {
            throw new \InvalidArgumentException('Not a Y-m-d date: ' . $ymd);
        }
        return $d->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}

<?php
declare(strict_types=1);

namespace App\Domain;

/** Where a due date falls relative to today in SAST. Go: type DueBucket string. */
enum DueBucket: string
{
    case Overdue = 'overdue';
    case Today = 'today';
    case Next3BusinessDays = 'next_3_business_days';
    case Later = 'later';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Overdue => 'Overdue',
            self::Today => 'Due today',
            self::Next3BusinessDays => 'Due soon',
            self::Later => 'Later',
            self::None => 'No due date',
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Domain;

/** How the grid groups rows (board columns are always stages). Go: type JobGroupBy string. */
enum JobGroupBy: string
{
    case None = 'none';
    case Stage = 'stage';
    case Brand = 'brand';
    case Campaign = 'campaign';
    case Am = 'am';
    case DueBucket = 'due_bucket';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No grouping',
            self::Stage => 'Stage',
            self::Brand => 'Brand',
            self::Campaign => 'Campaign',
            self::Am => 'AM',
            self::DueBucket => 'Due',
        };
    }
}

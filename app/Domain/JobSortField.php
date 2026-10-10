<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * The whitelist of grid sort fields. The Store maps each case to a fixed SQL
 * expression; nothing from the request reaches ORDER BY. Budget is not
 * sortable (the order would leak budgets the viewer may not see).
 * Go: type JobSortField string.
 */
enum JobSortField: string
{
    case JobNumber = 'job_number';
    case Title = 'title';
    case Brand = 'brand';
    case Campaign = 'campaign';
    case Stage = 'stage';
    case Am = 'am';
    case Due = 'due';
    case Hours = 'hours';
    case Updated = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::JobNumber => 'Job number',
            self::Title => 'Title',
            self::Brand => 'Brand',
            self::Campaign => 'Campaign',
            self::Stage => 'Stage',
            self::Am => 'AM',
            self::Due => 'Due date',
            self::Hours => 'Hours',
            self::Updated => 'Last updated',
        };
    }
}

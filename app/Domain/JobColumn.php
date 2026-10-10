<?php
declare(strict_types=1);

namespace App\Domain;

/** A grid column the user can show or hide (JobQuery columns). Go: type JobColumn string. */
enum JobColumn: string
{
    case JobNumber = 'job_number';
    case Title = 'title';
    case Brand = 'brand';
    case Campaign = 'campaign';
    case Stage = 'stage';
    case Am = 'am';
    case Traffic = 'traffic';
    case Due = 'due';
    case Hours = 'hours';
    case Budget = 'budget';
    case Version = 'version';
    case Updated = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::JobNumber => 'Job',
            self::Title => 'Title',
            self::Brand => 'Brand',
            self::Campaign => 'Campaign',
            self::Stage => 'Stage',
            self::Am => 'AM',
            self::Traffic => 'Traffic',
            self::Due => 'Due',
            self::Hours => 'Hours',
            self::Budget => 'Budget',
            self::Version => 'Brief',
            self::Updated => 'Updated',
        };
    }

    /** The sort field behind the header, null when the column is not sortable. */
    public function sortField(): ?JobSortField
    {
        return match ($this) {
            self::JobNumber => JobSortField::JobNumber,
            self::Title => JobSortField::Title,
            self::Brand => JobSortField::Brand,
            self::Campaign => JobSortField::Campaign,
            self::Stage => JobSortField::Stage,
            self::Am => JobSortField::Am,
            self::Due => JobSortField::Due,
            self::Hours => JobSortField::Hours,
            self::Updated => JobSortField::Updated,
            self::Traffic, self::Budget, self::Version => null,
        };
    }

    /** Job number and title are always shown. */
    public function isRequired(): bool
    {
        return $this === self::JobNumber || $this === self::Title;
    }
}

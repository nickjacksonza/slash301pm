<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** A page of the job search: the rows (at most the cap), the total match count, and each row's team. */
final class JobSearchResult
{
    /**
     * @param list<JobGridRow> $rows
     * @param array<string,Team> $teams job id => team (every row has one, possibly empty)
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $total,
        public readonly int $cap,
        public readonly array $teams,
    ) {}

    public function capped(): bool
    {
        return $this->total > count($this->rows);
    }

    public function team(string $jobId): Team
    {
        return $this->teams[$jobId] ?? new Team([]);
    }
}

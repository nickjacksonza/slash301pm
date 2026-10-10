<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\JobColumn;

/** #jobs-body: the table (header, groups, rows) plus the count line. url is the page URL for history.replaceState. */
final class JobsBodyVM
{
    /**
     * @param list<JobColumn> $columns
     * @param list<GridHeaderVM> $headers
     * @param list<GridGroupVM> $groups
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $headers,
        public readonly array $groups,
        public readonly bool $grouped,
        public readonly int $total,
        public readonly int $shown,
        public readonly int $cap,
        public readonly string $url,
        public readonly string $rowsUrl,
    ) {}
}

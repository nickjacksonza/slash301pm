<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefVersion;
use App\Domain\DueBucket;
use App\Domain\Stage;

/** One job row in a "My day" section. Go: type MyDayItem struct. */
final class MyDayItem
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly string $campaignName,
        public readonly Stage $stage,
        public readonly BriefVersion $version,
        public readonly bool $sent,
        public readonly ?string $dueDate,
        public readonly DueBucket $bucket,
        /** Calendar days from today to the due date (negative when overdue); 0 with no due date. */
        public readonly int $daysToDue,
        /** Why the row is in "Waiting on me"; '' elsewhere. */
        public readonly string $reason,
    ) {}
}

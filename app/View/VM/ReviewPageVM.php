<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;

/** GET /jobs/{id}/review. */
final class ReviewPageVM
{
    /** @param list<ReviewCardVM> $cards */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly Stage $stage,
        public readonly string $versionLabel,
        public readonly string $briefUrl,
        public readonly string $workUrl,
        public readonly array $cards,
        public readonly string $defaultFilter,
        public readonly ReviewJobBarVM $bar,
    ) {}

    public function countMine(): int
    {
        $n = 0;
        foreach ($this->cards as $c) {
            $n += $c->waitingForMe ? 1 : 0;
        }
        return $n;
    }

    public function countRejected(): int
    {
        $n = 0;
        foreach ($this->cards as $c) {
            $n += $c->rejected ? 1 : 0;
        }
        return $n;
    }
}

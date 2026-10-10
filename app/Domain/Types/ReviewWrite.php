<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Stage;

/**
 * The result of a ReviewStore write. conflict: the asset or job review row
 * changed since the browser read it (row_version); nothing was written.
 * error: refused inside the transaction. jobTo: the job's stage after, when
 * the write moved it. Go: type ReviewWrite struct.
 */
final class ReviewWrite
{
    public function __construct(
        public readonly bool $ok,
        public readonly bool $conflict,
        public readonly string $error,
        public readonly ?Stage $jobFrom = null,
        public readonly ?Stage $jobTo = null,
        public readonly bool $allApproved = false,
        public readonly bool $roundWarned = false,
        public readonly bool $clearedApprovals = false,
        public readonly int $round = 0,
    ) {}

    public static function stale(): self
    {
        return new self(false, true, '');
    }

    public static function refused(string $why): self
    {
        return new self(false, false, $why);
    }

    public function jobMoved(): bool
    {
        return $this->jobFrom !== null && $this->jobTo !== null && $this->jobFrom !== $this->jobTo;
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Stage;

/**
 * The result of a PublicationStore write. conflict: the row changed since the
 * browser read it (row_version), nothing was written. error: refused inside
 * the transaction (for example a checklist that is no longer complete).
 * jobFrom/jobTo: the job's Social stage before and after, equal when it stayed.
 */
final class SocialWrite
{
    public function __construct(
        public readonly bool $ok,
        public readonly bool $conflict,
        public readonly string $error,
        public readonly ?Stage $jobFrom = null,
        public readonly ?Stage $jobTo = null,
    ) {}

    public static function done(?Stage $from, ?Stage $to): self
    {
        return new self(true, false, '', $from, $to);
    }

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

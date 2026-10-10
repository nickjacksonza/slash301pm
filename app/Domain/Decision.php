<?php
declare(strict_types=1);

namespace App\Domain;

/** Result of a Policy check. Go: type Decision struct{ Allowed bool; Reason string }. */
final class Decision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
    ) {}

    public static function allow(): self
    {
        return new self(true, '');
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}

<?php
declare(strict_types=1);

namespace App\Domain;

/** Go: type RateLimitDecision struct{ Allowed bool; RetryAfter int }. */
final class RateLimitDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $retryAfter,
    ) {}
}

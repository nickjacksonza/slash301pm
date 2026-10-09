<?php
declare(strict_types=1);

namespace App\Clock;

use DateTimeImmutable;

/** Test clock. advance() exists so rate-limit windows can be tested. */
final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $at) {}

    public function now(): DateTimeImmutable
    {
        return $this->at;
    }

    public function advance(int $seconds): void
    {
        $this->at = $this->at->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }
}

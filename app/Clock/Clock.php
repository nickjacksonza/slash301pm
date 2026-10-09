<?php
declare(strict_types=1);

namespace App\Clock;

use DateTimeImmutable;

/** The only source of "now" outside tests. Go: type Clock interface{ Now() time.Time }. */
interface Clock
{
    public function now(): DateTimeImmutable;
}

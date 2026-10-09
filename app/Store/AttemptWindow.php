<?php
declare(strict_types=1);

namespace App\Store;

/** Failed logins for one key inside the rate-limit window. */
final class AttemptWindow
{
    public function __construct(
        public readonly int $count,
        public readonly ?int $oldestAt,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** Active users still on the seed password, and how many were not checked yet. */
final class SeedPasswordReport
{
    /** @param list<string> $usernames */
    public function __construct(
        public readonly array $usernames,
        public readonly int $unchecked,
    ) {}
}

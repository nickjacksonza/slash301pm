<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** A "My day" section: the rows to show and how many there are in all. */
final class MyDaySection
{
    /** @param list<MyDayItem> $items at most MyDay::SHOW rows */
    public function __construct(
        public readonly string $key,
        public readonly array $items,
        public readonly int $total,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\MyDayResult;

/** The "My day" page and its section patches. */
final class MyDayVM
{
    public function __construct(
        public readonly string $greeting,
        public readonly string $dateLabel,
        public readonly MyDayResult $result,
        public readonly bool $canCreate,
        /** @var list<\App\Domain\Types\MyDayBrand> brand filter row (owner decision 2026-10) */
        public readonly array $brands = [],
        /** The applied brand filter ('' = all). */
        public readonly string $brandId = '',
    ) {}
}

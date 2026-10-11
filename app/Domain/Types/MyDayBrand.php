<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One button of My day's brand row: a brand with open jobs the user sees. Go: type MyDayBrand struct. */
final class MyDayBrand
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        /** As stored; the view renders it only when Links::isHttpsUrl still accepts it. */
        public readonly string $logoUrl,
        /** Open jobs of this brand in the user's My day. */
        public readonly int $jobCount,
    ) {}
}

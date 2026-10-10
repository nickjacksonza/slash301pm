<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\SocialTab;
use App\Domain\Types\SocialAsset;
use App\View\ui\SelectOption;

/** GET /social and its #social-list patch. Never carries budget or hours. */
final class SocialQueueVM
{
    /**
     * @param array<string,int> $counts SocialTab value => assets in that tab (after brand, platform and mine)
     * @param list<SocialAsset> $rows the current tab
     * @param list<SelectOption> $brands
     * @param list<SelectOption> $platforms
     */
    public function __construct(
        public readonly SocialTab $tab,
        public readonly string $brand,
        public readonly string $platform,
        public readonly bool $mine,
        public readonly bool $offerMine,
        public readonly bool $wholeQueue,
        public readonly array $counts,
        public readonly array $rows,
        public readonly array $brands,
        public readonly array $platforms,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\BriefListItem;
use App\View\ui\SelectOption;

final class MyBriefsVM
{
    /**
     * @param list<BriefListItem> $drafts
     * @param list<BriefListItem> $unsent
     * @param list<BriefListItem> $sent
     * @param list<BriefListItem> $unowned
     * @param list<SelectOption> $campaignOptions grouped by brand
     */
    public function __construct(
        public readonly array $drafts,
        public readonly array $unsent,
        public readonly array $sent,
        public readonly array $unowned,
        public readonly array $campaignOptions,
        public readonly bool $canCreate,
        public readonly bool $canClaim,
    ) {}
}

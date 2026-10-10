<?php
declare(strict_types=1);

namespace App\View\VM;

use App\View\ui\SelectOption;

final class CampaignsVM
{
    /**
     * @param list<CampaignGroupVM> $groups
     * @param list<SelectOption> $brandOptions
     */
    public function __construct(
        public readonly array $groups,
        public readonly array $brandOptions,
        public readonly bool $canManage,
        public readonly bool $canCreateBrief,
        public readonly string $notice,
        /** set after a create: renders a one-off element that closes the dialog */
        public readonly string $createdId = '',
        /** May set brand logos (Policy::canSetBrandLogo) */
        public readonly bool $canSetLogo = false,
        /** set after a logo save: a one-off element that closes the brand dialog */
        public readonly string $brandSaved = '',
    ) {}
}

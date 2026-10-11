<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;
use App\View\ui\SelectOption;

/** GET /jobs/{id}/assets: every deliverable and asset of a sent job (Traffic, COO, ECD). */
final class JobAssetsVM
{
    /**
     * @param list<AssetGroupVM> $groups
     * @param list<SelectOption> $assetStatuses AssetStatus::OVERRIDE_VALUES
     * @param list<SelectOption> $postStatuses PublicationStatus cases
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly Stage $stage,
        public readonly array $groups,
        public readonly bool $canOverride,
        public readonly array $assetStatuses,
        public readonly array $postStatuses,
        /** set after an override: a one-off element that closes the dialogs */
        public readonly string $savedNonce = '',
    ) {}
}

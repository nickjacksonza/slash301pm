<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\BriefDiff;
use App\Domain\Types\AssetWarning;
use App\Domain\Types\ReadinessItem;

/**
 * Body of the "Send to Traffic" (first send) or "Send update" dialog.
 * bumps: level => resulting version label, for the update choice.
 */
final class SendDialogVM
{
    /**
     * @param list<ReadinessItem> $checklist
     * @param array<string,string> $bumps
     * @param list<AssetWarning> $warnings
     */
    public function __construct(
        public readonly string $jobId,
        public readonly bool $isUpdate,
        public readonly array $checklist,
        public readonly bool $ready,
        public readonly string $fromVersion,
        public readonly array $bumps,
        public readonly string $suggested,
        public readonly string $suggestedReason,
        public readonly ?BriefDiff $diff,
        public readonly int $assetsToCreate,
        public readonly int $assetsToCancel,
        public readonly array $warnings,
        public readonly string $trafficName,
        public readonly bool $showBudget,
        public readonly string $problem,
    ) {}
}

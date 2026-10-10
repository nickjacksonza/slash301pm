<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\JobAction;
use App\Domain\Types\ReadinessItem;

/** The right rail of the brief page (#brief-rail): status, version, checklist, actions, activity. Re-patched after every write. */
final class BriefRailVM
{
    /**
     * @param list<ReadinessItem> $checklist
     * @param list<JobAction> $actions stage moves this user may make now
     * @param list<ActivityItemVM> $activity newest first
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $stage,
        public readonly string $stageLabel,
        public readonly string $stageNote,
        public readonly string $versionLabel,
        public readonly bool $isSent,
        public readonly bool $hasChanges,
        public readonly array $checklist,
        public readonly bool $ready,
        public readonly string $savedAt,
        public readonly bool $canSend,
        public readonly bool $canUpdate,
        public readonly array $actions,
        public readonly bool $canClaimAm,
        public readonly bool $canEdit,
        public readonly array $activity,
        public readonly int $rowVersion,
    ) {}
}

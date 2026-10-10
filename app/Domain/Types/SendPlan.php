<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefDiff;
use App\Domain\BriefVersion;
use App\Domain\Stage;

/**
 * Everything a send (first send or update) writes, computed in Domain and
 * applied by BriefStore::send() in one transaction. The store re-checks the
 * row versions so nothing changed between planning and writing.
 */
final class SendPlan
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $briefId,
        public readonly bool $isFirst,
        public readonly BriefVersion $version,
        public readonly string $bumpLevel,        // initial | major | minor | patch
        public readonly string $note,
        public readonly BriefSnapshot $snapshot,
        public readonly ?BriefDiff $diff,
        public readonly AssetPlanResult $assets,
        public readonly Stage $fromStage,
        public readonly Stage $toStage,
        public readonly string $legacyStatus,
        public readonly int $jobRowVersion,
        public readonly int $briefRowVersion,
        /** @var list<string> */
        public readonly array $recipients,
    ) {}

    /** The same plan with the actor removed from the recipients (the actor never notifies themselves). */
    public function withoutActor(string $actorId): self
    {
        $r = [];
        foreach ($this->recipients as $id) {
            if ($id !== $actorId) {
                $r[] = $id;
            }
        }
        return new self($this->jobId, $this->briefId, $this->isFirst, $this->version, $this->bumpLevel, $this->note, $this->snapshot, $this->diff,
            $this->assets, $this->fromStage, $this->toStage, $this->legacyStatus, $this->jobRowVersion, $this->briefRowVersion, $r);
    }
}

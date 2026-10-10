<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;
use App\Domain\Stage;

/**
 * What Policy needs to know about a job: its stage, who created the brief, who
 * is assigned (job_assignments or assets.assigned_to), the brand, and brief
 * state. Built by JobStore::access(). Go: type JobAccess struct.
 */
final class JobAccess
{
    /**
     * @param list<Assignment> $assignments
     * @param list<string> $assetAssigneeIds
     */
    public function __construct(
        public readonly string $jobId,
        public readonly Stage $stage,
        public readonly ?string $creatorId,
        public readonly ?string $brandId,
        public readonly array $assignments,
        public readonly array $assetAssigneeIds,
        public readonly bool $briefSent,
        public readonly bool $hasUnsentChanges,
        public readonly bool $anyAssetStarted,
    ) {}

    public function isAssigned(string $userId): bool
    {
        foreach ($this->assignments as $a) {
            if ($a->userId === $userId) {
                return true;
            }
        }
        return in_array($userId, $this->assetAssigneeIds, true);
    }

    public function isCreator(string $userId): bool
    {
        return $this->creatorId !== null && $this->creatorId === $userId;
    }

    public function slotHolder(Role $role): ?string
    {
        foreach ($this->assignments as $a) {
            if ($a->role === $role) {
                return $a->userId;
            }
        }
        return null;
    }

    public function hasAm(): bool
    {
        return $this->slotHolder(Role::AM) !== null;
    }
}

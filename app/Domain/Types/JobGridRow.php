<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefVersion;
use App\Domain\Stage;
use App\Domain\WaitingOn;

/**
 * One job as the grid and board show it to one viewer. title, dueDate,
 * campaign and hours are the brief's working copy when the viewer may see it
 * (workingCopy), else the last sent values the legacy jobs columns mirror.
 * The access fields (creator, asset assignees, started) let Policy run
 * without another query. Go: struct.
 */
final class JobGridRow
{
    /** @param list<string> $assetAssigneeIds */
    public function __construct(
        public readonly string $id,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $status,
        public readonly Stage $stage,
        public readonly ?Stage $resumeStage,
        public readonly ?WaitingOn $waitingOn,
        public readonly string $waitingReason,
        public readonly int $rowVersion,
        public readonly int $briefRowVersion,
        public readonly ?string $campaignId,
        public readonly string $campaignName,
        public readonly ?string $brandId,
        public readonly string $brandName,
        public readonly ?string $dueDate,
        public readonly ?float $hoursEstimate,
        public readonly ?float $budget,
        public readonly BriefVersion $briefVersion,
        public readonly bool $briefSent,
        public readonly bool $hasUnsentChanges,
        public readonly bool $workingCopy,
        public readonly string $updatedAt,
        public readonly ?string $creatorId,
        public readonly ?string $amId,
        public readonly string $amName,
        public readonly array $assetAssigneeIds,
        public readonly bool $anyAssetStarted,
    ) {}

    public static function fromRow(array $r): self
    {
        $s = static fn (string $k): ?string => isset($r[$k]) && $r[$k] !== '' ? (string) $r[$k] : null;
        $f = static fn (string $k): ?float => isset($r[$k]) && $r[$k] !== '' && is_numeric($r[$k]) ? (float) $r[$k] : null;
        $assignees = [];
        foreach (explode(',', (string) ($r['asset_assignees'] ?? '')) as $id) {
            if ($id !== '' && !in_array($id, $assignees, true)) {
                $assignees[] = $id;
            }
        }
        $stage = Stage::tryFrom((string) ($r['stage'] ?? '')) ?? (Stage::fromLegacy((string) ($r['status'] ?? '')) ?? Stage::Draft);
        $due = $s('due_date');
        return new self(
            (string) $r['id'], (string) $r['job_number'], (string) ($r['title'] ?? ''), (string) ($r['status'] ?? ''), $stage,
            Stage::tryFrom((string) ($r['resume_stage'] ?? '')), WaitingOn::tryFrom((string) ($r['waiting_on'] ?? '')), (string) ($r['waiting_reason'] ?? ''),
            (int) $r['row_version'], (int) ($r['brief_rv'] ?? 0), $s('campaign_id'), (string) ($r['campaign_name'] ?? ''), $s('brand_id'),
            (string) ($r['brand_name'] ?? ''), $due !== null ? substr($due, 0, 10) : null, $f('hours_estimate'), $f('budget'),
            new BriefVersion((int) ($r['version_major'] ?? 0), (int) ($r['version_minor'] ?? 1), (int) ($r['version_patch'] ?? 0)),
            $s('sent_at') !== null, (int) ($r['has_unsent_changes'] ?? 0) === 1, (int) ($r['wc'] ?? 0) === 1, (string) ($r['updated_at'] ?? ''),
            $s('creator_id'), $s('am_id'), (string) ($r['am_name'] ?? ''), $assignees, (int) ($r['any_started'] ?? 0) === 1,
        );
    }

    /** What Policy needs, from this row and the page's team query. */
    public function access(Team $team): JobAccess
    {
        return new JobAccess($this->id, $this->stage, $this->creatorId, $this->brandId, $team->assignments, $this->assetAssigneeIds,
            $this->briefSent, $this->hasUnsentChanges, $this->anyAssetStarted);
    }
}

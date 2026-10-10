<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;

/**
 * The job-level review state (job_reviews; an empty one when the job has no
 * row yet, rowVersion 0). Approvals and steps keep who and when.
 * Go: type JobReview struct.
 */
final class JobReview
{
    public function __construct(
        public readonly string $jobId,
        public readonly int $jobRound,
        public readonly ?string $allDoneAt,
        public readonly ?string $ecdRequestedBy,
        public readonly ?string $ecdRequestedAt,
        public readonly ?string $cdApprovedBy,
        public readonly string $cdApprovedByName,
        public readonly ?string $cdApprovedAt,
        public readonly ?string $ecdApprovedBy,
        public readonly string $ecdApprovedByName,
        public readonly ?string $ecdApprovedAt,
        public readonly ?string $clientReadyBy,
        public readonly ?string $clientReadyAt,
        public readonly ?string $sentToClientBy,
        public readonly ?string $sentToClientAt,
        public readonly ?string $assigneeId,
        public readonly string $assigneeName,
        public readonly ?Role $assigneeRole,
        public readonly int $rowVersion,
    ) {}

    public static function empty(string $jobId): self
    {
        return new self($jobId, 1, null, null, null, null, '', null, null, '', null, null, null, null, null, null, '', null, 0);
    }

    public static function fromRow(array $r): self
    {
        $opt = static fn (string $k): ?string => isset($r[$k]) && $r[$k] !== null && $r[$k] !== '' ? (string) $r[$k] : null;
        return new self(
            (string) $r['job_id'], max(1, (int) $r['job_round']), $opt('all_done_at'), $opt('ecd_requested_by'), $opt('ecd_requested_at'),
            $opt('cd_approved_by'), (string) ($r['cd_name'] ?? ''), $opt('cd_approved_at'),
            $opt('ecd_approved_by'), (string) ($r['ecd_name'] ?? ''), $opt('ecd_approved_at'),
            $opt('client_ready_by'), $opt('client_ready_at'), $opt('sent_to_client_by'), $opt('sent_to_client_at'),
            $opt('review_assignee_id'), (string) ($r['assignee_name'] ?? ''), Role::tryFrom((string) ($r['assignee_role'] ?? '')),
            (int) $r['row_version'],
        );
    }

    public function cdApproved(): bool
    {
        return $this->cdApprovedAt !== null;
    }

    public function ecdApproved(): bool
    {
        return $this->ecdApprovedAt !== null;
    }

    /** Both internal approvals: the job may be made ready for the client. */
    public function internallyApproved(): bool
    {
        return $this->cdApproved() && $this->ecdApproved();
    }

    public function readyForClient(): bool
    {
        return $this->clientReadyAt !== null;
    }

    public function sentToClient(): bool
    {
        return $this->sentToClientAt !== null;
    }

    /** The CD asked the ECD to review, or approved the job, and the ECD has not approved yet. */
    public function waitingForEcd(): bool
    {
        return !$this->ecdApproved() && ($this->cdApproved() || $this->ecdRequestedAt !== null);
    }
}

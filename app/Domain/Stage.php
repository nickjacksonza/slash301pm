<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Job workflow stage (jobs.stage), the new app's source of truth (ADR 0002).
 * The legacy jobs.status column is mirrored through toLegacy()/fromLegacy().
 * Social stages (ready_to_schedule, scheduled, live) sit between approved_client
 * and done; the legacy CHECK has no such values, so they mirror to
 * 'Approved (External)'. Go: type Stage string.
 */
enum Stage: string
{
    case Draft = 'draft';
    case Briefed = 'briefed';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case OnHold = 'on_hold';
    case InReview = 'in_review';
    case ApprovedInternal = 'approved_internal';
    case ApprovedClient = 'approved_client';
    case ReadyToSchedule = 'ready_to_schedule';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Done = 'done';
    case Archived = 'archived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Briefed => 'Briefed',
            self::InProgress => 'In progress',
            self::Waiting => 'Waiting',
            self::OnHold => 'On hold',
            self::InReview => 'In review',
            self::ApprovedInternal => 'Approved (internal)',
            self::ApprovedClient => 'Approved (client)',
            self::ReadyToSchedule => 'Ready to schedule',
            self::Scheduled => 'Scheduled',
            self::Live => 'Live',
            self::Done => 'Done',
            self::Archived => 'Archived',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Legacy status -> stage; null for a value the legacy CHECK does not allow. */
    public static function fromLegacy(string $status): ?self
    {
        return match ($status) {
            'Inbox', 'Brief' => self::Draft,
            'To Do' => self::Briefed,
            'In Progress', 'Today', 'This Week' => self::InProgress,
            'Waiting' => self::Waiting,
            'On Hold' => self::OnHold,
            'In Review' => self::InReview,
            'Approved (Internal)' => self::ApprovedInternal,
            'Approved (External)' => self::ApprovedClient,
            'Done' => self::Done,
            'Archived' => self::Archived,
            'Cancelled' => self::Cancelled,
            default => null,
        };
    }

    /**
     * Stage -> legacy status to write. When the current status already belongs
     * to this stage it is kept, so Today / This Week survive a no-op write.
     */
    public function toLegacy(?string $currentStatus = null): string
    {
        if ($currentStatus !== null && $this->legacyMatches($currentStatus)) {
            return $currentStatus;
        }
        return match ($this) {
            self::Draft => 'Inbox',
            self::Briefed => 'To Do',
            self::InProgress => 'In Progress',
            self::Waiting => 'Waiting',
            self::OnHold => 'On Hold',
            self::InReview => 'In Review',
            self::ApprovedInternal => 'Approved (Internal)',
            self::ApprovedClient, self::ReadyToSchedule, self::Scheduled, self::Live => 'Approved (External)',
            self::Done => 'Done',
            self::Archived => 'Archived',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Does this legacy status already represent this stage? */
    public function legacyMatches(string $status): bool
    {
        $mapped = self::fromLegacy($status);
        if ($mapped === null) {
            return false;
        }
        if ($mapped === $this) {
            return true;
        }
        // The social stages share the approved_client legacy value.
        return $mapped === self::ApprovedClient && ($this === self::ReadyToSchedule || $this === self::Scheduled || $this === self::Live);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Briefed, self::InProgress, self::InReview, self::ApprovedInternal, self::ApprovedClient, self::ReadyToSchedule, self::Scheduled, self::Live];
    }

    /** Draft plus the active stages. @return list<self> */
    public static function workable(): array
    {
        return array_merge([self::Draft], self::active());
    }

    /** Workable plus waiting and on hold. @return list<self> */
    public static function open(): array
    {
        return array_merge(self::workable(), [self::Waiting, self::OnHold]);
    }

    /** @return list<self> */
    public static function closed(): array
    {
        return [self::Done, self::Archived, self::Cancelled];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function isWorkable(): bool
    {
        return in_array($this, self::workable(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }

    /** Waiting or on hold: paused, resumes to the stored resume stage. */
    /**
     * Whether the job's due date still applies (counts for Overdue and Due soon).
     * It stops applying once the client has approved: from approved_client on,
     * dates belong to Social scheduling, not delivery (roles.md N13).
     */
    public function countsDueDate(): bool
    {
        return $this->isOpen() && !in_array($this, [self::ApprovedClient, self::ReadyToSchedule, self::Scheduled, self::Live], true);
    }

    public function isPaused(): bool
    {
        return $this === self::Waiting || $this === self::OnHold;
    }
}

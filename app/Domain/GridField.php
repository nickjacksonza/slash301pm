<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * The whitelist of grid cells that can be edited inline (PATCH /jobs/{id}/fields/{field}).
 * Brief-owned fields go through the brief (draft: mirrored to the legacy jobs
 * columns at once; sent: into the working copy until "Send update"), waiting
 * fields are job columns (stage waiting only), am and traffic are assignment
 * slots. Go: type GridField string.
 */
enum GridField: string
{
    case Title = 'title';
    case DueDate = 'due_date';
    case HoursEstimate = 'hours_estimate';
    case CampaignId = 'campaign_id';
    case WaitingOn = 'waiting_on';
    case WaitingReason = 'waiting_reason';
    case Am = 'am';
    case Traffic = 'traffic';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Title',
            self::DueDate => 'Due date',
            self::HoursEstimate => 'Hours',
            self::CampaignId => 'Campaign',
            self::WaitingOn => 'Waiting on',
            self::WaitingReason => 'Waiting reason',
            self::Am => 'AM',
            self::Traffic => 'Traffic',
        };
    }

    public function isBriefField(): bool
    {
        return $this === self::Title || $this === self::DueDate || $this === self::HoursEstimate || $this === self::CampaignId;
    }

    public function isWaitingField(): bool
    {
        return $this === self::WaitingOn || $this === self::WaitingReason;
    }

    /** The assignment slot for am / traffic, else null. */
    public function slot(): ?Role
    {
        return match ($this) {
            self::Am => Role::AM,
            self::Traffic => Role::Traffic,
            default => null,
        };
    }

    /** The policy-matrix row (edit_job_field:*), null for slots (canAssign decides). */
    public function matrixId(): ?string
    {
        return $this->slot() !== null ? null : 'edit_job_field:' . $this->value;
    }

    /** The grid column whose cell holds this field's editor (waiting fields live in the stage cell). */
    public function column(): JobColumn
    {
        return match ($this) {
            self::Title => JobColumn::Title,
            self::DueDate => JobColumn::Due,
            self::HoursEstimate => JobColumn::Hours,
            self::CampaignId => JobColumn::Campaign,
            self::WaitingOn, self::WaitingReason => JobColumn::Stage,
            self::Am => JobColumn::Am,
            self::Traffic => JobColumn::Traffic,
        };
    }

    /** The key in brief.* (BriefSignals) for a brief field. */
    public function briefKey(): string
    {
        return $this->value;
    }
}

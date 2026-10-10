<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * A named stage move (there is no free status editing, ADR 0002). Phase 2 exposes
 * send, recall, wait, hold, resume, cancel and archive; the rest are modelled so
 * later phases (reviews, client portal, social) reuse the same table.
 * Go: type JobAction string.
 */
enum JobAction: string
{
    case Send = 'send';
    case Recall = 'recall';
    case Start = 'start';
    case Submit = 'submit';
    case SendBack = 'send_back';
    case ApproveInternal = 'approve_internal';
    case ApproveClient = 'approve_client';
    case ReadyToSchedule = 'ready_to_schedule';
    case Schedule = 'schedule';
    case GoLive = 'go_live';
    case MarkDone = 'done';
    case Wait = 'wait';
    case Hold = 'hold';
    case Resume = 'resume';
    case Cancel = 'cancel';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Send => 'Send to Traffic',
            self::Recall => 'Recall brief',
            self::Start => 'Start work',
            self::Submit => 'Submit for review',
            self::SendBack => 'Send back',
            self::ApproveInternal => 'Approve internally',
            self::ApproveClient => 'Record client approval',
            self::ReadyToSchedule => 'Ready to schedule',
            self::Schedule => 'Mark scheduled',
            self::GoLive => 'Mark live',
            self::MarkDone => 'Mark done',
            self::Wait => 'Put on waiting',
            self::Hold => 'Put on hold',
            self::Resume => 'Resume',
            self::Cancel => 'Cancel job',
            self::Archive => 'Archive',
        };
    }

    /** The actions POST /jobs/{id}/transition accepts in Phase 2. @return list<self> */
    public static function phase2(): array
    {
        return [self::Recall, self::Wait, self::Hold, self::Resume, self::Cancel, self::Archive];
    }
}

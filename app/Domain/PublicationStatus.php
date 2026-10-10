<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * asset_publications.status. checking is the row before the final check is
 * complete; the next three mirror the job's Social stages; archived takes the
 * post out of every count. Go: type PublicationStatus string.
 */
enum PublicationStatus: string
{
    case Checking = 'checking';
    case ReadyToSchedule = 'ready_to_schedule';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'To check',
            self::ReadyToSchedule => 'Ready to schedule',
            self::Scheduled => 'Scheduled',
            self::Live => 'Live',
            self::Archived => 'Archived',
        };
    }

    /** Order along the flow; archived is outside it (-1). */
    public function rank(): int
    {
        return match ($this) {
            self::Checking => 0,
            self::ReadyToSchedule => 1,
            self::Scheduled => 2,
            self::Live => 3,
            self::Archived => -1,
        };
    }

    /** The job stage a job sits in when every publication has reached this status. */
    public function jobStage(): ?Stage
    {
        return match ($this) {
            self::Checking => Stage::ApprovedClient,
            self::ReadyToSchedule => Stage::ReadyToSchedule,
            self::Scheduled => Stage::Scheduled,
            self::Live => Stage::Live,
            self::Archived => null,
        };
    }

    /** One step back (reopen), or null. */
    public function previous(): ?self
    {
        return match ($this) {
            self::ReadyToSchedule => self::Checking,
            self::Scheduled => self::ReadyToSchedule,
            self::Live => self::Scheduled,
            default => null,
        };
    }

    public static function fromRank(int $rank): ?self
    {
        foreach (self::cases() as $c) {
            if ($c->rank() === $rank) {
                return $c;
            }
        }
        return null;
    }
}

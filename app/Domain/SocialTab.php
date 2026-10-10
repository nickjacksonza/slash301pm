<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * The tabs of /social. An asset sits in the tab of its status (the lowest of
 * its non-archived publications, PublicationRules::assetStatus); "To check"
 * only while the job is still in the Social window. Go: type SocialTab string.
 */
enum SocialTab: string
{
    case Check = 'check';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Check => 'To check',
            self::Ready => 'Ready to schedule',
            self::Scheduled => 'Scheduled',
            self::Live => 'Live',
            self::Archived => 'Archived',
        };
    }

    /** @param list<PublicationStatus> $statuses */
    public static function forAsset(array $statuses, Stage $jobStage): ?self
    {
        $s = PublicationRules::assetStatus($statuses);
        if ($s === null) {
            return self::Archived;
        }
        $tab = match ($s) {
            PublicationStatus::Checking => self::Check,
            PublicationStatus::ReadyToSchedule => self::Ready,
            PublicationStatus::Scheduled => self::Scheduled,
            PublicationStatus::Live => self::Live,
            PublicationStatus::Archived => self::Archived,
        };
        // A done job no longer waits for checks; only what was published stays listed.
        if (!PublicationRules::isSocialWindow($jobStage) && ($tab === self::Check || $tab === self::Ready)) {
            return null;
        }
        return $tab;
    }
}

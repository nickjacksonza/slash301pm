<?php
declare(strict_types=1);

namespace App\Domain;

/** A named status move of one publication. Go: type PublicationAction string. */
enum PublicationAction: string
{
    case Ready = 'ready';
    case Schedule = 'schedule';
    case GoLive = 'go_live';
    case Archive = 'archive';
    case Reopen = 'reopen';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready to schedule',
            self::Schedule => 'Mark scheduled',
            self::GoLive => 'Mark live',
            self::Archive => 'Archive',
            self::Reopen => 'Move back a step',
        };
    }

    /** The activity verb (notification catalogue N34 to N39). */
    public function verb(): string
    {
        return match ($this) {
            self::Ready => Notifications::PUBLICATION_READY,
            self::Schedule => Notifications::PUBLICATION_SCHEDULED,
            self::GoLive => Notifications::PUBLICATION_LIVE,
            self::Archive => Notifications::PUBLICATION_ARCHIVED,
            self::Reopen => Notifications::PUBLICATION_REOPENED,
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Legacy assets.status values (no CHECK). Unstarted values may be cancelled by a
 * brief update; every other value (In Progress, Today, This Week, Waiting,
 * In Review, Done, and the later social values Ready to Schedule, Scheduled,
 * Live) counts as started and is never cancelled or renamed.
 */
final class AssetStatus
{
    public const NEW = 'Inbox';
    public const CANCELLED = 'Cancelled';
    /** @var list<string> */
    public const UNSTARTED = ['', 'Inbox', 'Brief', 'To Do', 'Not Started', 'Backlog'];

    /**
     * Every value an override may set: legacy src/constants.js STATUSES (the
     * asset picker of the legacy app) plus Cancelled, in that order.
     * @var list<string>
     */
    public const OVERRIDE_VALUES = [
        'Inbox', 'Backlog', 'To Do', 'Today', 'This Week', 'In Progress', 'Waiting', 'On Hold', 'In Review',
        'Approved (Internal)', 'Approved (External)', 'Scheduled', 'Live', 'Done', 'Archived', 'Cancelled',
    ];

    public static function isOverrideValue(string $status): bool
    {
        return in_array($status, self::OVERRIDE_VALUES, true);
    }

    public static function isCancelled(string $status): bool
    {
        return $status === self::CANCELLED;
    }

    public static function isStarted(string $status): bool
    {
        return !self::isCancelled($status) && !in_array($status, self::UNSTARTED, true);
    }
}

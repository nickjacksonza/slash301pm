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

    public static function isCancelled(string $status): bool
    {
        return $status === self::CANCELLED;
    }

    public static function isStarted(string $status): bool
    {
        return !self::isCancelled($status) && !in_array($status, self::UNSTARTED, true);
    }
}

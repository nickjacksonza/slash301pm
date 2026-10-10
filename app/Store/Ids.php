<?php
declare(strict_types=1);

namespace App\Store;

use DateTimeImmutable;
use DateTimeZone;

/** Ids and timestamps for new rows. IDs are made in PHP (no RETURNING on SQLite 3.34). */
final class Ids
{
    /** 32 lower-case hex characters, like legacy generateId(). */
    public static function new(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Stored timestamps are UTC 'Y-m-d H:i:s', the same as SQLite datetime('now') in the legacy triggers. */
    public static function utc(DateTimeImmutable $t): string
    {
        return $t->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}

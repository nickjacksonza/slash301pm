<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\ReviewRules;
use App\Domain\Types\ActivityEntry;
use DateTimeImmutable;

/** Owner settings (app_settings, migration 0014): key and value as text. */
final class SettingsStore
{
    public const REVIEW_ROUND_LIMIT = 'review_round_limit';

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    /** The review round limit (ReviewRules::DEFAULT_ROUND_LIMIT when unset or unreadable; 0 = no warning). */
    public function reviewRoundLimit(): int
    {
        $v = $this->db->scalar('SELECT value FROM app_settings WHERE key = :k', ['k' => self::REVIEW_ROUND_LIMIT]);
        if ($v === null || preg_match('/^\d{1,2}$/', (string) $v) !== 1) {
            return ReviewRules::DEFAULT_ROUND_LIMIT;
        }
        return (int) $v;
    }

    /** Set the round limit (validated by the caller, ReviewRules::roundLimitProblem). Logged as setting_changed. */
    public function setReviewRoundLimit(int $limit, string $actorId, DateTimeImmutable $now): void
    {
        $this->db->txImmediate(function (Db $tx) use ($limit, $actorId, $now): void {
            $old = $tx->scalar('SELECT value FROM app_settings WHERE key = :k', ['k' => self::REVIEW_ROUND_LIMIT]);
            $tx->exec('INSERT OR REPLACE INTO app_settings (key, value, updated_by, updated_at) VALUES (:k, :v, :u, :at)',
                ['k' => self::REVIEW_ROUND_LIMIT, 'v' => (string) $limit, 'u' => $actorId, 'at' => Ids::utc($now)]);
            $this->activity->append($tx, new ActivityEntry(null, $actorId, 'setting_changed', 'setting', self::REVIEW_ROUND_LIMIT,
                ['key' => self::REVIEW_ROUND_LIMIT, 'from' => $old === null ? '' : (string) $old, 'to' => (string) $limit, 'recipients' => []]), $now);
        });
    }
}

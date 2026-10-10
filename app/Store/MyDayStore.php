<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\MyDayChange;
use App\Domain\Types\MyDayJob;
use DateTimeImmutable;

/**
 * Reads for the "My day" page, plus the per-user last-seen stamp (0010_user_seen).
 * "Owned" matches JobStore::listOwnedBy: the user holds the AM, PM or Producer
 * slot, or created the brief (jobs.created_by for jobs without one).
 */
final class MyDayStore
{
    private const OWNED = "(EXISTS (SELECT 1 FROM job_assignments ja WHERE ja.job_id = j.id AND ja.user_id = :u AND ja.role_on_job IN ('AM', 'PM', 'Producer'))
                   OR COALESCE(br.created_by, j.created_by) = :u)";

    private const OPEN = "j.stage NOT IN ('done', 'archived', 'cancelled')";

    public function __construct(private readonly Db $db) {}

    /** Open jobs the user owns, earliest due first. @return list<MyDayJob> */
    public function ownedOpen(string $userId, int $limit = 300): array
    {
        $rows = $this->db->query(
            "SELECT j.id AS job_id, j.job_number, COALESCE(br.title, j.title) AS title, c.name AS campaign_name, b.name AS brand_name, j.stage,
                    j.waiting_on, j.waiting_reason,
                    COALESCE(br.version_major, 0) AS version_major, COALESCE(br.version_minor, 1) AS version_minor, COALESCE(br.version_patch, 0) AS version_patch,
                    br.sent_at, COALESCE(br.has_unsent_changes, 0) AS has_unsent_changes,
                    COALESCE(NULLIF(br.due_date, ''), j.delivery_date) AS due_date,
                    EXISTS (SELECT 1 FROM job_assignments am WHERE am.job_id = j.id AND am.user_id = :u AND am.role_on_job = 'AM') AS i_am_am
             FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id LEFT JOIN campaigns c ON c.id = j.campaign_id LEFT JOIN brands b ON b.id = c.brand_id
             WHERE " . self::OPEN . ' AND ' . self::OWNED . '
             ORDER BY j.job_number LIMIT :n',
            ['u' => $userId, 'n' => max(1, min(500, $limit))],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = MyDayJob::fromRow($r);
        }
        return $out;
    }

    /**
     * Activity after $since by someone else, on a job the user owns or naming the
     * user in data.recipients. The LIKE is only a prefilter (user ids are hex, so
     * the pattern is safe); Domain\MyDay re-checks recipients exactly.
     * @return list<MyDayChange>
     */
    public function changesSince(string $userId, string $since, int $limit = 100): array
    {
        $rows = $this->db->query(
            "SELECT a.id, a.job_id, a.actor_id, COALESCE(u.name, '') AS actor_name, a.verb, a.entity_type, a.entity_id, a.data_json, a.created_at,
                    COALESCE(j.job_number, '') AS job_number, COALESCE(br.title, j.title, '') AS job_title,
                    CASE WHEN j.id IS NOT NULL AND " . self::OWNED . ' THEN 1 ELSE 0 END AS job_is_mine
             FROM activity a LEFT JOIN users u ON u.id = a.actor_id LEFT JOIN jobs j ON j.id = a.job_id LEFT JOIN briefs br ON br.job_id = j.id
             WHERE a.created_at > :since AND (a.actor_id IS NULL OR a.actor_id != :u)
               AND (a.data_json LIKE :like OR (j.id IS NOT NULL AND ' . self::OWNED . '))
             ORDER BY a.created_at DESC, a.rowid DESC LIMIT :n',
            ['u' => $userId, 'since' => $since, 'like' => '%"' . $userId . '"%', 'n' => max(1, min(300, $limit))],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = MyDayChange::fromRow($r);
        }
        return $out;
    }

    /** UTC 'Y-m-d H:i:s' of the last "Mark all seen", or null. */
    public function seenAt(string $userId): ?string
    {
        $v = $this->db->scalar('SELECT today_seen_at FROM user_seen WHERE user_id = :u', ['u' => $userId]);
        return $v === null || $v === false || $v === '' ? null : (string) $v;
    }

    public function markSeen(string $userId, DateTimeImmutable $now): void
    {
        $this->db->exec('INSERT OR REPLACE INTO user_seen (user_id, today_seen_at) VALUES (:u, :at)', ['u' => $userId, 'at' => Ids::utc($now)]);
    }

    /**
     * Nav badge: distinct open jobs of the user's that are overdue (before $todaySast,
     * 'Y-m-d'), waiting on them as AM, unsent drafts, or briefs with unsent changes.
     * One query; the claimable "no AM" jobs are not counted.
     */
    public function attentionCount(string $userId, string $todaySast): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id
             WHERE " . self::OPEN . ' AND ' . self::OWNED . "
               AND ((COALESCE(NULLIF(br.due_date, ''), j.delivery_date) IS NOT NULL AND SUBSTR(COALESCE(NULLIF(br.due_date, ''), j.delivery_date), 1, 10) < :today)
                 OR (j.stage = 'waiting' AND j.waiting_on = 'am' AND EXISTS (SELECT 1 FROM job_assignments am WHERE am.job_id = j.id AND am.user_id = :u AND am.role_on_job = 'AM'))
                 OR (j.stage = 'draft' AND br.sent_at IS NULL)
                 OR (br.sent_at IS NOT NULL AND br.has_unsent_changes = 1))",
            ['u' => $userId, 'today' => $todaySast],
        );
    }
}

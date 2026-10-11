<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\MyDayMode;
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

    /** Any slot on the job, or an asset assigned to the user (docs/roles.md "assigned"). */
    private const ASSIGNED = "(EXISTS (SELECT 1 FROM job_assignments ja WHERE ja.job_id = j.id AND ja.user_id = :u)
                   OR EXISTS (SELECT 1 FROM assets aa WHERE aa.job_id = j.id AND aa.assigned_to = :u))";

    /** Briefed and nobody to make it yet: no CD or maker slot and no asset assignee. */
    private const NEEDS_TEAM = "(j.stage = 'briefed'
                   AND NOT EXISTS (SELECT 1 FROM job_assignments nt WHERE nt.job_id = j.id AND nt.role_on_job IN ('CD', 'Copywriter', 'Designer', 'Developer', 'SEO', 'Social'))
                   AND NOT EXISTS (SELECT 1 FROM assets na WHERE na.job_id = j.id AND na.assigned_to IS NOT NULL AND na.assigned_to <> ''))";

    public function __construct(private readonly Db $db) {}

    /** Open jobs the user owns, earliest due first. @return list<MyDayJob> */
    public function ownedOpen(string $userId, int $limit = 300): array
    {
        $rows = $this->db->query(
            "SELECT j.id AS job_id, j.job_number, COALESCE(br.title, j.title) AS title, c.name AS campaign_name, b.name AS brand_name, b.id AS brand_id, b.logo_url AS brand_logo, j.stage,
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
     * Open jobs the user is assigned to (Traffic, CD, makers, QA), as the team
     * sees them: the last sent values the legacy jobs columns mirror, never the
     * working copy, and never an unsent draft. @return list<MyDayJob>
     */
    public function assignedOpen(string $userId, int $limit = 300): array
    {
        $rows = $this->db->query(
            "SELECT j.id AS job_id, j.job_number, j.title, c.name AS campaign_name, b.name AS brand_name, b.id AS brand_id, b.logo_url AS brand_logo, j.stage,
                    j.waiting_on, j.waiting_reason,
                    COALESCE(br.version_major, 1) AS version_major, COALESCE(br.version_minor, 0) AS version_minor, COALESCE(br.version_patch, 0) AS version_patch,
                    br.sent_at, 0 AS has_unsent_changes, j.delivery_date AS due_date, 0 AS i_am_am,
                    EXISTS (SELECT 1 FROM job_assignments tr WHERE tr.job_id = j.id AND tr.user_id = :u AND tr.role_on_job = 'Traffic') AS i_am_traffic,
                    CASE WHEN " . self::NEEDS_TEAM . " THEN 1 ELSE 0 END AS needs_team
             FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id LEFT JOIN campaigns c ON c.id = j.campaign_id LEFT JOIN brands b ON b.id = c.brand_id
             WHERE " . self::OPEN . " AND j.stage <> 'draft' AND " . self::ASSIGNED . '
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
    public function changesSince(string $userId, string $since, int $limit = 100, MyDayMode $mode = MyDayMode::Owner): array
    {
        $owner = $mode === MyDayMode::Owner;
        // Assigned users: "mine" is any assignment, titles are the sent ones, unsent drafts are never shown.
        $mine = $owner ? self::OWNED : self::ASSIGNED;
        $title = $owner ? "COALESCE(br.title, j.title, '')" : "COALESCE(j.title, '')";
        $drafts = $owner ? '' : " AND (j.id IS NULL OR j.stage <> 'draft' OR br.sent_at IS NOT NULL)";
        $rows = $this->db->query(
            "SELECT a.id, a.job_id, a.actor_id, COALESCE(u.name, '') AS actor_name, a.verb, a.entity_type, a.entity_id, a.data_json, a.created_at,
                    COALESCE(j.job_number, '') AS job_number, " . $title . " AS job_title,
                    COALESCE((SELECT bc.brand_id FROM campaigns bc WHERE bc.id = j.campaign_id), '') AS brand_id,
                    CASE WHEN j.id IS NOT NULL AND " . $mine . ' THEN 1 ELSE 0 END AS job_is_mine
             FROM activity a LEFT JOIN users u ON u.id = a.actor_id LEFT JOIN jobs j ON j.id = a.job_id LEFT JOIN briefs br ON br.job_id = j.id
             WHERE a.created_at > :since AND (a.actor_id IS NULL OR a.actor_id != :u)
               AND (a.data_json LIKE :like OR (j.id IS NOT NULL AND ' . $mine . '))' . $drafts . '
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
     * Nav badge for Traffic and assigned users: open, sent jobs of theirs that
     * are overdue, plus (Traffic) briefed jobs on which they hold the Traffic
     * slot that still need a team.
     */
    public function assignedAttentionCount(string $userId, string $todaySast, bool $traffic): int
    {
        $team = $traffic
            ? " OR (" . self::NEEDS_TEAM . " AND EXISTS (SELECT 1 FROM job_assignments tr WHERE tr.job_id = j.id AND tr.user_id = :u AND tr.role_on_job = 'Traffic'))"
            : '';
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM jobs j
             WHERE " . self::OPEN . " AND j.stage <> 'draft' AND " . self::ASSIGNED . "
               AND ((j.stage NOT IN ('approved_client', 'ready_to_schedule', 'scheduled', 'live') AND j.delivery_date IS NOT NULL AND j.delivery_date <> '' AND SUBSTR(j.delivery_date, 1, 10) < :today)" . $team . ')',
            ['u' => $userId, 'today' => $todaySast],
        );
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
               AND ((j.stage NOT IN ('approved_client', 'ready_to_schedule', 'scheduled', 'live') AND COALESCE(NULLIF(br.due_date, ''), j.delivery_date) IS NOT NULL AND SUBSTR(COALESCE(NULLIF(br.due_date, ''), j.delivery_date), 1, 10) < :today)
                 OR (j.stage = 'waiting' AND j.waiting_on = 'am' AND EXISTS (SELECT 1 FROM job_assignments am WHERE am.job_id = j.id AND am.user_id = :u AND am.role_on_job = 'AM'))
                 OR (j.stage = 'draft' AND br.sent_at IS NULL)
                 OR (br.sent_at IS NOT NULL AND br.has_unsent_changes = 1))",
            ['u' => $userId, 'today' => $todaySast],
        );
    }
}

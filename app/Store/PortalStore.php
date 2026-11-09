<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\ReviewDecision;
use App\Domain\Types\ClientDecision;
use App\Domain\Types\PortalJob;
use App\Domain\Types\PortalRecent;
use App\Domain\ReviewNotify;

/**
 * Client portal reads. Every query takes the client's brand id and filters on
 * the job's campaign brand in SQL, so a client of brand A never receives a row
 * of brand B even if a handler forgot its check (IDOR defence in depth).
 * Only client-safe columns are selected. Writes live in ReviewStore.
 */
final class PortalStore
{
    public function __construct(private readonly Db $db) {}

    /** Jobs of the brand waiting for the client: approved (internal) and marked ready for client review. @return list<PortalJob> */
    public function awaiting(string $brandId): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT j.id, j.job_number, j.title, b.name AS brand_name, jr.client_ready_at,
                    (SELECT COUNT(*) FROM assets a WHERE a.job_id = j.id AND a.status <> 'Cancelled') AS posts
             FROM jobs j JOIN campaigns c ON c.id = j.campaign_id JOIN brands b ON b.id = c.brand_id JOIN job_reviews jr ON jr.job_id = j.id
             WHERE c.brand_id = :b AND j.stage = 'approved_internal' AND jr.client_ready_at IS NOT NULL
             ORDER BY jr.client_ready_at, j.job_number",
            ['b' => $brandId],
        ) as $r) {
            $id = (string) $r['id'];
            $out[] = new PortalJob($id, (string) $r['job_number'], (string) $r['title'], (string) $r['brand_name'], (string) $r['client_ready_at'],
                (int) $r['posts'], count($this->clientApprovedIds($brandId, $id)));
        }
        return $out;
    }

    /** One job of the brand as on /portal, or null (other brand, or not waiting for the client). */
    public function awaitingJob(string $brandId, string $jobId): ?PortalJob
    {
        foreach ($this->awaiting($brandId) as $j) {
            if ($j->jobId === $jobId) {
                return $j;
            }
        }
        return null;
    }

    /**
     * Jobs of the brand a client contact signed off or sent back since
     * $sinceUtc, newest first, one line per job, without the jobs that are
     * waiting again. @return list<PortalRecent>
     */
    public function recent(string $brandId, string $sinceUtc): array
    {
        $waiting = [];
        foreach ($this->awaiting($brandId) as $j) {
            $waiting[] = $j->jobId;
        }
        $out = [];
        $seen = [];
        foreach ($this->db->query(
            "SELECT a.job_id, a.verb, a.created_at, j.job_number, j.title, COALESCE(u.name, '') AS actor_name
             FROM activity a JOIN jobs j ON j.id = a.job_id JOIN campaigns c ON c.id = j.campaign_id
             JOIN users u ON u.id = a.actor_id AND u.role = 'Client' AND u.brand_id = :b
             WHERE c.brand_id = :b AND a.verb IN (:off, :rej) AND a.created_at >= :since
             ORDER BY a.created_at DESC, a.rowid DESC LIMIT 200",
            ['b' => $brandId, 'off' => ReviewNotify::CLIENT_SIGNED_OFF, 'rej' => ReviewNotify::CLIENT_POST_REJECTED, 'since' => $sinceUtc],
        ) as $r) {
            $id = (string) $r['job_id'];
            if (isset($seen[$id]) || in_array($id, $waiting, true)) {
                continue;
            }
            $seen[$id] = true;
            $out[] = new PortalRecent($id, (string) $r['job_number'], (string) $r['title'], (string) $r['verb'] === ReviewNotify::CLIENT_SIGNED_OFF,
                (string) $r['actor_name'], (string) $r['created_at']);
        }
        return $out;
    }

    /**
     * Posts of the job approved by a client contact of the brand in their
     * current round (the latest hand-in). @return list<string> asset ids
     */
    public function clientApprovedIds(string $brandId, string $jobId): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT DISTINCT r.asset_id FROM asset_reviews r
             JOIN users u ON u.id = r.reviewer_id AND u.role = 'Client' AND u.brand_id = :b
             JOIN jobs j ON j.id = r.job_id JOIN campaigns c ON c.id = j.campaign_id AND c.brand_id = :b
             WHERE r.job_id = :j AND r.decision = 'approved'
               AND r.round = (SELECT MAX(s.round) FROM asset_submissions s WHERE s.asset_id = r.asset_id)
             ORDER BY r.asset_id",
            ['b' => $brandId, 'j' => $jobId],
        ) as $r) {
            $out[] = (string) $r['asset_id'];
        }
        return $out;
    }

    /**
     * Decisions by client contacts of the brand on the job's posts, newest
     * first, one per decision (a decision writes a row per part). Internal
     * decisions are never returned. @return array<string,list<ClientDecision>> by asset id
     */
    public function clientHistory(string $brandId, string $jobId): array
    {
        $out = [];
        $seen = [];
        foreach ($this->db->query(
            "SELECT r.asset_id, r.round, r.decision, r.feedback, r.reviewer_id, r.reviewed_at, u.name AS client_name
             FROM asset_reviews r
             JOIN users u ON u.id = r.reviewer_id AND u.role = 'Client' AND u.brand_id = :b
             JOIN jobs j ON j.id = r.job_id JOIN campaigns c ON c.id = j.campaign_id AND c.brand_id = :b
             WHERE r.job_id = :j ORDER BY r.reviewed_at DESC, r.rowid DESC",
            ['b' => $brandId, 'j' => $jobId],
        ) as $r) {
            $decision = ReviewDecision::tryFrom((string) $r['decision']);
            if ($decision === null) {
                continue;
            }
            $key = $r['asset_id'] . '|' . $r['round'] . '|' . $r['reviewer_id'] . '|' . $r['reviewed_at'] . '|' . $r['decision'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[(string) $r['asset_id']][] = new ClientDecision((string) $r['asset_id'], (int) $r['round'], $decision, (string) ($r['feedback'] ?? ''),
                (string) $r['client_name'], (string) $r['reviewed_at']);
        }
        return $out;
    }
}

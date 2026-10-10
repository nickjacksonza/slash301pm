<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Notifications;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobAsset;
use App\Domain\Types\OverrideRange;
use App\Domain\Types\OverrideRecord;
use App\Domain\Types\SocialWrite;
use App\Domain\Types\Team;
use DateTimeImmutable;

/**
 * Asset status overrides (owner decision 2026-10) and the reads around them:
 * every asset of a job with its deliverable line, the override write for a
 * legacy assets.status (Social posts go through PublicationStore::override),
 * and the COO's report. An override and its activity row are one
 * BEGIN IMMEDIATE transaction; the status the browser saw is re-checked on the
 * row (legacy assets have no row_version), so a concurrent change answers stale.
 */
final class AssetOverrideStore
{
    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    /** @return list<JobAsset> by deliverable order, then asset order */
    public function jobAssets(string $jobId): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT a.id, a.name, a.status, a.template_id, a.assigned_to, COALESCE(u.name, '') AS assignee_name, a.due_date, a.brief_asset_id,
                    COALESCE(ba.label, '') AS line_label, COALESCE(ba.channel, '') AS channel
             FROM assets a LEFT JOIN users u ON u.id = a.assigned_to LEFT JOIN brief_assets ba ON ba.id = a.brief_asset_id
             WHERE a.job_id = :j
             ORDER BY ba.sort_order IS NULL, ba.sort_order, a.sort_order, a.created_at, a.id LIMIT 1000",
            ['j' => $jobId],
        ) as $r) {
            $out[] = JobAsset::fromRow($r);
        }
        return $out;
    }

    /** Set assets.status from $expectedFrom to $to (values validated by AssetOverride), logging the override. */
    public function overrideAsset(string $jobId, string $assetId, string $expectedFrom, string $to, string $reason, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($jobId, $assetId, $expectedFrom, $to, $reason, $actorId, $now): SocialWrite {
            $row = $tx->one('SELECT name, status, assigned_to FROM assets WHERE id = :a AND job_id = :j', ['a' => $assetId, 'j' => $jobId]);
            if ($row === null) {
                return SocialWrite::refused('This asset is not on this job.');
            }
            if ((string) $row['status'] !== $expectedFrom) {
                return SocialWrite::stale();
            }
            $n = $tx->exec('UPDATE assets SET status = :to WHERE id = :a AND job_id = :j AND status = :from',
                ['to' => $to, 'a' => $assetId, 'j' => $jobId, 'from' => $expectedFrom]);
            if ($n === 0) {
                return SocialWrite::stale();
            }
            $assignee = $row['assigned_to'] !== null && $row['assigned_to'] !== '' ? (string) $row['assigned_to'] : null;
            $this->activity->append($tx, new ActivityEntry($jobId, $actorId, Notifications::ASSET_STATUS_OVERRIDDEN, 'asset', $assetId, [
                'kind' => 'asset', 'asset_id' => $assetId, 'asset_name' => (string) $row['name'], 'from' => $expectedFrom, 'to' => $to, 'reason' => $reason,
                'recipients' => Notifications::overrideRecipients(self::teamTx($tx, $jobId), self::creatorTx($tx, $jobId), $assignee, $actorId),
            ]), $now);
            return SocialWrite::done(null, null);
        });
    }

    /** Overrides in the range, newest first, with who did them and on which job. @return list<OverrideRecord> */
    public function report(OverrideRange $range, int $limit = 500): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT a.id, a.job_id, a.actor_id, COALESCE(u.name, '') AS actor_name, COALESCE(u.role, '') AS actor_role, a.verb, a.entity_type, a.entity_id,
                    a.data_json, a.created_at, COALESCE(j.job_number, '') AS job_number, COALESCE(j.title, '') AS job_title
             FROM activity a LEFT JOIN users u ON u.id = a.actor_id LEFT JOIN jobs j ON j.id = a.job_id
             WHERE a.verb = :v AND a.created_at >= :f AND a.created_at < :t
             ORDER BY a.created_at DESC, a.rowid DESC LIMIT :n",
            ['v' => Notifications::ASSET_STATUS_OVERRIDDEN, 'f' => $range->fromUtc, 't' => $range->toUtc, 'n' => max(1, min(2000, $limit))],
        ) as $r) {
            $out[] = OverrideRecord::fromRow($r);
        }
        return $out;
    }

    /** The job's slots inside the caller's transaction (recipients are resolved at the time of the event). */
    public static function teamTx(Db $tx, string $jobId): Team
    {
        $out = [];
        foreach ($tx->query(
            'SELECT ja.role_on_job, ja.user_id, u.name AS user_name, u.role AS user_role FROM job_assignments ja JOIN users u ON u.id = ja.user_id WHERE ja.job_id = :j',
            ['j' => $jobId],
        ) as $r) {
            $out[] = Assignment::fromRow($r);
        }
        return new Team($out);
    }

    public static function creatorTx(Db $tx, string $jobId): ?string
    {
        $v = $tx->scalar('SELECT COALESCE(br.created_by, j.created_by) FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id WHERE j.id = :j', ['j' => $jobId]);
        return $v === null || $v === '' ? null : (string) $v;
    }
}

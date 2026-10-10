<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\AssetStatus;
use App\Domain\Types\Asset;
use App\Domain\Types\AssetPlanResult;
use DateTimeImmutable;

/** Legacy assets rows created and cancelled by brief sends (AssetPlan). */
final class AssetStore
{
    private const COLS = 'id, job_id, brief_asset_id, name, type, template_id, status, assigned_to, due_date, sort_order';

    public function __construct(private readonly Db $db) {}

    /** @return list<Asset> by sort order */
    public function listByJob(string $jobId): array
    {
        $out = [];
        foreach ($this->db->query('SELECT ' . self::COLS . ' FROM assets WHERE job_id = :j ORDER BY sort_order, created_at, id', ['j' => $jobId]) as $r) {
            $out[] = Asset::fromRow($r);
        }
        return $out;
    }

    public function anyStarted(string $jobId): bool
    {
        foreach ($this->db->query('SELECT DISTINCT status FROM assets WHERE job_id = :j', ['j' => $jobId]) as $r) {
            if (AssetStatus::isStarted((string) $r['status'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Inside the caller's transaction. Cancels only rows that are still
     * unstarted at this moment (a legacy user may have started one since the
     * plan was made). Returns [created ids, cancelled ids].
     * @return array{0:list<string>,1:list<string>}
     */
    public function applyPlan(Db $tx, string $jobId, ?string $campaignId, AssetPlanResult $plan, DateTimeImmutable $now): array
    {
        $created = [];
        $at = Ids::utc($now);
        foreach ($plan->create as $p) {
            $id = Ids::new();
            $tx->exec(
                'INSERT INTO assets (id, job_id, campaign_id, name, type, template_id, status, assigned_to, due_date, sort_order, brief_asset_id, created_at, updated_at)
                 VALUES (:id, :job, :camp, :name, :type, :tpl, :status, NULL, :due, :sort, :line, :at, :at)',
                ['id' => $id, 'job' => $jobId, 'camp' => $campaignId, 'name' => $p->name, 'type' => $p->type, 'tpl' => $p->templateId,
                    'status' => AssetStatus::NEW, 'due' => $p->dueDate, 'sort' => $p->sortOrder, 'line' => $p->lineId, 'at' => $at],
            );
            $created[] = $id;
        }
        $cancelled = [];
        $placeholders = implode(', ', array_fill(0, count(AssetStatus::UNSTARTED), '?'));
        foreach ($plan->cancel as $id) {
            $n = $tx->exec(
                'UPDATE assets SET status = ? WHERE id = ? AND job_id = ? AND status IN (' . $placeholders . ')',
                array_merge([AssetStatus::CANCELLED, $id, $jobId], AssetStatus::UNSTARTED),
            );
            if ($n > 0) {
                $cancelled[] = $id;
            }
        }
        return [$created, $cancelled];
    }
}

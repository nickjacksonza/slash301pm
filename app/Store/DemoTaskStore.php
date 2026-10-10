<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\AssetPlan;
use App\Domain\AssetTemplates;
use App\Domain\Policy;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\AssetNaming;
use App\Domain\Types\BriefLine;
use App\Domain\Types\DemoTaskResult;
use DateTimeImmutable;

/**
 * "Add demo role tasks" (/admin/system, COO, demo mode only). For up to
 * MAX_JOBS open, sent jobs (by job number) that do not have them yet: add the
 * three role-task deliverables (AssetTemplates::roleTasks) to the brief,
 * fill the empty Developer, SEO and Producer slots with the first active user
 * of each role, expand the lines into assets (AssetPlan, so the assets go to
 * the slot holders), and log demo_role_tasks_added. One transaction; running
 * it again changes nothing for jobs that already have the tasks.
 */
final class DemoTaskStore
{
    public const MAX_JOBS = 3;

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity, private readonly AssetStore $assets) {}

    public function addRoleTasks(string $actorId, DateTimeImmutable $now): DemoTaskResult
    {
        return $this->db->txImmediate(function (Db $tx) use ($actorId, $now): DemoTaskResult {
            $templates = AssetTemplates::roleTasks();
            $people = [];
            $missing = [];
            foreach (Policy::TASK_ROLES as $role) {
                $id = $tx->scalar('SELECT id FROM users WHERE is_active = 1 AND role = :r ORDER BY name, id LIMIT 1', ['r' => $role->value]);
                if ($id === null) {
                    $missing[] = $role->value;
                } else {
                    $people[$role->value] = (string) $id;
                }
            }
            $tplIds = [];
            $p = [];
            foreach ($templates as $i => $t) {
                $tplIds[] = ':t' . $i;
                $p['t' . $i] = $t->id;
            }
            $jobs = $tx->query(
                "SELECT j.id, j.job_number, j.campaign_id, br.id AS brief_id, COALESCE(NULLIF(br.due_date, ''), j.delivery_date) AS due_date,
                        COALESCE(b.name, '') AS brand_name, COALESCE(c.name, '') AS campaign_name,
                        EXISTS (SELECT 1 FROM brief_assets x WHERE x.brief_id = br.id AND x.template_id IN (" . implode(', ', $tplIds) . ")) AS has_tasks
                 FROM jobs j JOIN briefs br ON br.job_id = j.id LEFT JOIN campaigns c ON c.id = j.campaign_id LEFT JOIN brands b ON b.id = c.brand_id
                 WHERE br.sent_at IS NOT NULL AND j.stage NOT IN ('draft', 'done', 'archived', 'cancelled')
                 ORDER BY j.job_number, j.id LIMIT " . self::MAX_JOBS,
                $p,
            );
            $done = [];
            $skipped = [];
            $at = Ids::utc($now);
            foreach ($jobs as $j) {
                $jobId = (string) $j['id'];
                $briefId = (string) $j['brief_id'];
                if ((int) $j['has_tasks'] === 1) {
                    $skipped[] = (string) $j['job_number'];
                    continue;
                }
                $filled = [];
                foreach ($people as $role => $userId) {
                    if ($tx->scalar('SELECT 1 FROM job_assignments WHERE job_id = :j AND role_on_job = :r', ['j' => $jobId, 'r' => $role]) === null) {
                        $tx->exec('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, :r)', ['id' => Ids::new(), 'j' => $jobId, 'u' => $userId, 'r' => $role]);
                        $filled[$role] = $userId;
                    }
                }
                $sort = (int) $tx->scalar('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM brief_assets WHERE brief_id = :b', ['b' => $briefId]);
                $lines = [];
                foreach ($templates as $t) {
                    $lineId = Ids::new();
                    $tx->exec(
                        "INSERT INTO brief_assets (id, brief_id, job_id, template_id, label, qty, channel, size_format, specs, copy_required, due_date, sort_order, created_at, updated_at)
                         VALUES (:id, :b, :j, :tpl, :label, 1, '', '', 'Demo task', 0, NULL, :sort, :at, :at)",
                        ['id' => $lineId, 'b' => $briefId, 'j' => $jobId, 'tpl' => $t->id, 'label' => $t->name, 'sort' => $sort, 'at' => $at],
                    );
                    $lines[] = new BriefLine($lineId, $briefId, $jobId, $t->id, $t->name, 1, '', '', 'Demo task', false, null, $sort);
                    $sort++;
                }
                $assetSort = (int) $tx->scalar('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM assets WHERE job_id = :j', ['j' => $jobId]);
                $due = $j['due_date'] !== null && $j['due_date'] !== '' ? substr((string) $j['due_date'], 0, 10) : null;
                $plan = AssetPlan::plan($lines, [], new AssetNaming((string) $j['job_number'], (string) $j['brand_name'], (string) $j['campaign_name'], $due, $now), $assetSort);
                [$created] = $this->assets->applyPlan($tx, $jobId, $j['campaign_id'] !== null ? (string) $j['campaign_id'] : null, $plan, $now);
                // The working copy now differs from the sent version: the owner sees "unsent changes".
                $tx->exec('UPDATE briefs SET has_unsent_changes = 1, row_version = row_version + 1 WHERE id = :b', ['b' => $briefId]);
                $recipients = [];
                foreach ($tx->query('SELECT DISTINCT assigned_to FROM assets WHERE job_id = ? AND assigned_to IS NOT NULL AND brief_asset_id IN (' . implode(', ', array_fill(0, count($lines), '?')) . ')',
                    array_merge([$jobId], array_map(static fn (BriefLine $l): string => $l->id, $lines))) as $r) {
                    if ((string) $r['assigned_to'] !== $actorId) {
                        $recipients[] = (string) $r['assigned_to'];
                    }
                }
                $this->activity->append($tx, new ActivityEntry($jobId, $actorId, 'demo_role_tasks_added', 'job', $jobId, [
                    'templates' => array_map(static fn ($t): string => $t->id, $templates), 'assets_created' => count($created), 'slots_filled' => $filled,
                    'recipients' => $recipients,
                ]), $now);
                $done[] = (string) $j['job_number'];
            }
            return new DemoTaskResult($done, $skipped, $missing);
        });
    }
}

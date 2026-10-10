<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\AssetStatus;
use App\Domain\DueWindow;
use App\Domain\JobGroupBy;
use App\Domain\JobQuery;
use App\Domain\JobSortField;
use App\Domain\OwnerFilter;
use App\Domain\PolicyRule;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobGridRow;
use App\Domain\Types\JobSearchResult;
use App\Domain\Types\JobViewer;
use App\Domain\Types\Team;

/**
 * The job grid and board search: one query for the rows (filters, sort and
 * group from a whitelisted JobQuery, every value bound), one for the teams of
 * those rows (IN list). SQL fragments below are fixed strings chosen by enum
 * cases; nothing from the request is concatenated. Go: package store.
 */
final class JobQueryStore
{
    public const CAP = 500;

    public function __construct(private readonly Db $db) {}

    /** $today and $soonEnd are SAST 'Y-m-d' (due buckets: soon = up to 3 business days). */
    public function search(JobQuery $q, JobViewer $v, string $today, string $soonEnd, int $cap = self::CAP): JobSearchResult
    {
        [$sql, $params] = $this->sql($q, $v, $today, $soonEnd, $cap);
        $rows = [];
        $total = 0;
        $ids = [];
        foreach ($this->db->query($sql, $params) as $r) {
            $rows[] = JobGridRow::fromRow($r);
            $ids[] = (string) $r['id'];
            $total = (int) $r['total'];
        }
        return new JobSearchResult($rows, $total, $cap, $this->teams($ids));
    }

    /**
     * The rows statement and its parameters (public for the parameter-binding test).
     * @return array{0:string,1:array<string,mixed>}
     */
    public function sql(JobQuery $q, JobViewer $v, string $today, string $soonEnd, int $cap = self::CAP, ?string $onlyJobId = null): array
    {
        $p = ['me' => $v->userId, 'cap' => max(1, min(self::CAP, $cap))];
        $own = '(EXISTS (SELECT 1 FROM job_assignments o WHERE o.job_id = j.id AND o.user_id = :me)'
            . ' OR EXISTS (SELECT 1 FROM assets oa WHERE oa.job_id = j.id AND oa.assigned_to = :me))';
        $creator = '(COALESCE(br.created_by, j.created_by) = :me)';
        $wc = match ($v->draftView) {
            PolicyRule::Allow => '1',
            PolicyRule::AssignedOrCreator => '(' . $own . ' OR ' . $creator . ')',
            PolicyRule::Assigned => $own,
            PolicyRule::Creator => $creator,
            default => '0',
        };
        $wc = '(br.id IS NOT NULL AND ' . $wc . ')';
        $scope = match ($v->scope) {
            PolicyRule::Allow => '1',
            PolicyRule::Assigned => $own,
            PolicyRule::AssignedOrCreator => '(' . $own . ' OR ' . $creator . ')',
            PolicyRule::Creator => $creator,
            PolicyRule::OwnBrand => 'EXISTS (SELECT 1 FROM campaigns sc WHERE sc.id = j.campaign_id AND sc.brand_id = :vbrand)',
            PolicyRule::Deny => '0',
        };
        if ($v->scope === PolicyRule::OwnBrand) {
            $p['vbrand'] = $v->brandId ?? '';
        }
        // Drafts only for who may read them (Policy::canViewJob): the working copy
        // viewer, or a manager who could claim a draft nobody created.
        $draftOk = $v->draftView === PolicyRule::Deny ? $wc : '(' . $wc . ' OR COALESCE(br.created_by, j.created_by) IS NULL)';
        $scope = '(' . $scope . ") AND (j.stage <> 'draft' OR " . $draftOk . ')';
        $budget = match ($v->budgetView) {
            PolicyRule::Allow => 'br.budget',
            PolicyRule::AssignedOrCreator => 'CASE WHEN (' . $own . ' OR ' . $creator . ') THEN br.budget ELSE NULL END',
            PolicyRule::Assigned => 'CASE WHEN ' . $own . ' THEN br.budget ELSE NULL END',
            PolicyRule::Creator => 'CASE WHEN ' . $creator . ' THEN br.budget ELSE NULL END',
            default => 'NULL',
        };
        $unstarted = [];
        foreach (array_merge(AssetStatus::UNSTARTED, [AssetStatus::CANCELLED]) as $i => $st) {
            $unstarted[] = ':us' . $i;
            $p['us' . $i] = $st;
        }
        $inner = 'SELECT j.id, j.job_number, j.status, j.stage, j.resume_stage, j.waiting_on, j.waiting_reason, j.row_version, j.updated_at,
                br.row_version AS brief_rv, br.version_major, br.version_minor, br.version_patch, br.sent_at, br.has_unsent_changes, ' . $budget . ' AS budget,
                COALESCE(br.created_by, j.created_by) AS creator_id,
                CASE WHEN ' . $wc . ' THEN 1 ELSE 0 END AS wc,
                CASE WHEN ' . $wc . ' THEN br.title ELSE j.title END AS title,
                CASE WHEN ' . $wc . ' THEN br.due_date ELSE j.delivery_date END AS due_date,
                CASE WHEN ' . $wc . ' THEN COALESCE(br.campaign_id, j.campaign_id) ELSE j.campaign_id END AS campaign_id,
                CASE WHEN ' . $wc . ' THEN br.hours_estimate ELSE j.hours_estimate END AS hours_estimate,
                (SELECT GROUP_CONCAT(DISTINCT a.assigned_to) FROM assets a WHERE a.job_id = j.id AND a.assigned_to IS NOT NULL AND a.assigned_to <> \'\') AS asset_assignees,
                EXISTS (SELECT 1 FROM assets s WHERE s.job_id = j.id AND COALESCE(s.status, \'\') NOT IN (' . implode(', ', $unstarted) . ')) AS any_started
            FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id
            WHERE ' . $scope;

        $where = [];
        if ($onlyJobId !== null) {
            $where[] = 'x.id = :one';
            $p['one'] = $onlyJobId;
        }
        $stageIn = [];
        foreach ($q->stages as $i => $s) {
            $stageIn[] = ':st' . $i;
            $p['st' . $i] = $s->value;
        }
        $where[] = 'x.stage IN (' . implode(', ', $stageIn) . ')';
        if ($q->brandId !== '') {
            $where[] = 'c.brand_id = :brand';
            $p['brand'] = $q->brandId;
        }
        if ($q->campaignId !== '') {
            $where[] = 'x.campaign_id = :campaign';
            $p['campaign'] = $q->campaignId;
        }
        if ($q->owner === OwnerFilter::Mine) {
            $where[] = "(EXISTS (SELECT 1 FROM job_assignments m WHERE m.job_id = x.id AND m.user_id = :me AND m.role_on_job IN ('AM', 'PM', 'Producer'))"
                . ' OR (am.user_id IS NULL AND x.creator_id = :me))';
        } elseif ($q->owner === OwnerFilter::Unowned) {
            $where[] = 'am.user_id IS NULL';
        }
        if ($q->assigneeId !== '' || $q->role !== null) {
            $cond = [];
            if ($q->role !== null) {
                $cond[] = 'f.role_on_job = :frole';
                $p['frole'] = $q->role->value;
            }
            if ($q->assigneeId === 'none') {
                $where[] = 'NOT EXISTS (SELECT 1 FROM job_assignments f WHERE f.job_id = x.id' . ($cond !== [] ? ' AND ' . implode(' AND ', $cond) : '') . ')';
            } else {
                if ($q->assigneeId !== '') {
                    $cond[] = 'f.user_id = :fuser';
                    $p['fuser'] = $q->assigneeId === 'me' ? $v->userId : $q->assigneeId;
                }
                $where[] = 'EXISTS (SELECT 1 FROM job_assignments f WHERE f.job_id = x.id AND ' . implode(' AND ', $cond) . ')';
            }
        }
        $due = "substr(COALESCE(x.due_date, ''), 1, 10)";
        if ($q->due !== null) {
            if ($q->due === DueWindow::NoDate) {
                $where[] = $due . " = ''";
            } else {
                [$from, $to] = $q->due->range($today);
                $where[] = $due . " <> ''";
                if ($from !== null) {
                    $where[] = $due . ' >= :dfrom';
                    $p['dfrom'] = $from;
                }
                if ($to !== null) {
                    $where[] = $due . ' <= :dto';
                    $p['dto'] = $to;
                }
                if ($q->due === DueWindow::Overdue) {
                    $where[] = "x.stage NOT IN ('done', 'archived', 'cancelled')";
                }
            }
        }
        if ($q->text !== '') {
            $where[] = "(x.job_number LIKE :text ESCAPE '\\' OR x.title LIKE :text ESCAPE '\\' OR c.name LIKE :text ESCAPE '\\' OR b.name LIKE :text ESCAPE '\\')";
            $p['text'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q->text) . '%';
        }

        $stageOrder = 'CASE x.stage';
        foreach (JobQuery::pipeline() as $i => $s) {
            $stageOrder .= " WHEN '" . $s->value . "' THEN " . $i;
        }
        $stageOrder .= ' ELSE 99 END';
        $order = [];
        if ($q->groupBy === JobGroupBy::DueBucket) {
            $p['today'] = $today;
            $p['soon'] = $soonEnd;
        }
        $order = array_merge($order, match ($q->groupBy) {
            JobGroupBy::None => [],
            JobGroupBy::Stage => [$stageOrder],
            JobGroupBy::Brand => ['b.name IS NULL', 'b.name COLLATE NOCASE', 'c.brand_id'],
            JobGroupBy::Campaign => ['c.name IS NULL', 'b.name COLLATE NOCASE', 'c.name COLLATE NOCASE', 'x.campaign_id'],
            JobGroupBy::Am => ['amu.name IS NULL', 'amu.name COLLATE NOCASE', 'am.user_id'],
            JobGroupBy::DueBucket => ['CASE WHEN ' . $due . " = '' THEN 4 WHEN " . $due . ' < :today THEN 0 WHEN ' . $due . ' = :today THEN 1 WHEN ' . $due . ' <= :soon THEN 2 ELSE 3 END'],
        });
        foreach ($q->sorts as $s) {
            $dir = $s->desc ? ' DESC' : '';
            foreach (match ($s->field) {
                JobSortField::JobNumber => ['x.job_number' . $dir],
                JobSortField::Title => ['x.title COLLATE NOCASE' . $dir],
                JobSortField::Brand => ['b.name IS NULL', 'b.name COLLATE NOCASE' . $dir],
                JobSortField::Campaign => ['c.name IS NULL', 'c.name COLLATE NOCASE' . $dir],
                JobSortField::Stage => [$stageOrder . $dir],
                JobSortField::Am => ['amu.name IS NULL', 'amu.name COLLATE NOCASE' . $dir],
                JobSortField::Due => [$due . " = ''", $due . $dir],
                JobSortField::Hours => ['x.hours_estimate IS NULL', 'x.hours_estimate' . $dir],
                JobSortField::Updated => ['x.updated_at' . $dir],
            } as $expr) {
                $order[] = $expr;
            }
        }
        $order[] = 'x.job_number';
        $order[] = 'x.id';

        $sql = 'SELECT x.*, c.name AS campaign_name, c.brand_id, b.name AS brand_name, am.user_id AS am_id, amu.name AS am_name, COUNT(*) OVER () AS total
            FROM (' . $inner . ') x
            LEFT JOIN campaigns c ON c.id = x.campaign_id
            LEFT JOIN brands b ON b.id = c.brand_id
            LEFT JOIN job_assignments am ON am.job_id = x.id AND am.role_on_job = \'AM\'
            LEFT JOIN users amu ON amu.id = am.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . implode(', ', $order) . '
            LIMIT :cap';
        // PDO refuses parameters the statement does not use (:me is unused for some viewers).
        $bound = [];
        foreach ($p as $k => $val) {
            if (preg_match('/:' . $k . '\\b/', $sql) === 1) {
                $bound[$k] = $val;
            }
        }
        return [$sql, $bound];
    }

    /**
     * Every slot of the given jobs in one query (IN list of bound ids).
     * @param list<string> $jobIds
     * @return array<string,Team>
     */
    public function teams(array $jobIds): array
    {
        $out = [];
        if ($jobIds === []) {
            return $out;
        }
        $in = [];
        $p = [];
        foreach (array_values($jobIds) as $i => $id) {
            $in[] = ':j' . $i;
            $p['j' . $i] = $id;
            $out[$id] = [];
        }
        foreach ($this->db->query(
            'SELECT ja.job_id, ja.role_on_job, ja.user_id, u.name AS user_name, u.role AS user_role
             FROM job_assignments ja JOIN users u ON u.id = ja.user_id WHERE ja.job_id IN (' . implode(', ', $in) . ') ORDER BY ja.job_id, ja.role_on_job',
            $p,
        ) as $r) {
            $out[(string) $r['job_id']][] = Assignment::fromRow($r);
        }
        $teams = [];
        foreach ($out as $id => $assignments) {
            $teams[$id] = new Team($assignments);
        }
        return $teams;
    }

    /** One job as the viewer sees it (after an edit or a move), with its team. */
    public function one(string $jobId, JobViewer $v, string $today, string $soonEnd): ?JobSearchResult
    {
        $q = JobQuery::fromState(['stages' => 'all'], JobQuery::SCREEN_JOBS);
        [$sql, $p] = $this->sql($q, $v, $today, $soonEnd, 1, $jobId);
        $rows = [];
        foreach ($this->db->query($sql, $p) as $r) {
            $rows[] = JobGridRow::fromRow($r);
        }
        if ($rows === []) {
            return null;
        }
        return new JobSearchResult($rows, 1, 1, $this->teams([$jobId]));
    }
}

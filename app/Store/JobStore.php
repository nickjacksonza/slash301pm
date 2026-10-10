<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\AssetStatus;
use App\Domain\JobNumber;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Assignment;
use App\Domain\Types\BriefListItem;
use App\Domain\Types\Campaign;
use App\Domain\Types\Job;
use App\Domain\Types\JobAccess;
use App\Domain\Types\TransitionOutcome;
use App\Domain\Types\User;
use DateTimeImmutable;

/**
 * jobs rows for the new app. Every write bumps row_version itself (so the
 * 0002 trigger does not bump again) and dual-writes the legacy status.
 */
final class JobStore
{
    private const SELECT = 'SELECT j.id, j.job_number, j.campaign_id, j.title, j.status, j.stage, j.stage_changed_at, j.waiting_on, j.waiting_reason,
            j.resume_stage, j.row_version, j.am_user_id, j.created_by, j.updated_by, j.delivery_date, j.updated_at,
            c.name AS campaign_name, b.id AS brand_id, b.name AS brand_name, b.prefix AS brand_prefix
        FROM jobs j LEFT JOIN campaigns c ON c.id = j.campaign_id LEFT JOIN brands b ON b.id = c.brand_id';

    private const LIST = 'SELECT j.id AS job_id, j.job_number, br.title, c.name AS campaign_name, b.name AS brand_name, j.stage,
            br.version_major, br.version_minor, br.version_patch, br.sent_at, br.has_unsent_changes, br.due_date, br.updated_at
        FROM jobs j JOIN briefs br ON br.job_id = j.id LEFT JOIN campaigns c ON c.id = j.campaign_id LEFT JOIN brands b ON b.id = c.brand_id';

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    public function get(string $id): ?Job
    {
        $r = $this->db->one(self::SELECT . ' WHERE j.id = :id', ['id' => $id]);
        return $r === null ? null : Job::fromRow($r);
    }

    /** Everything Policy needs about the job, or null when it does not exist. */
    public function access(string $id): ?JobAccess
    {
        $r = $this->db->one(
            'SELECT j.stage, j.status, COALESCE(br.created_by, j.created_by) AS creator, c.brand_id, br.sent_at, br.has_unsent_changes
             FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id LEFT JOIN campaigns c ON c.id = j.campaign_id WHERE j.id = :id',
            ['id' => $id],
        );
        if ($r === null) {
            return null;
        }
        $assignments = [];
        foreach ($this->db->query(
            'SELECT ja.role_on_job, ja.user_id, u.name AS user_name, u.role AS user_role FROM job_assignments ja JOIN users u ON u.id = ja.user_id WHERE ja.job_id = :j',
            ['j' => $id],
        ) as $a) {
            $assignments[] = Assignment::fromRow($a);
        }
        $assignees = [];
        $started = false;
        foreach ($this->db->query('SELECT assigned_to, status FROM assets WHERE job_id = :j', ['j' => $id]) as $a) {
            if ($a['assigned_to'] !== null && $a['assigned_to'] !== '' && !in_array((string) $a['assigned_to'], $assignees, true)) {
                $assignees[] = (string) $a['assigned_to'];
            }
            if (AssetStatus::isStarted((string) $a['status'])) {
                $started = true;
            }
        }
        $stage = Stage::tryFrom((string) ($r['stage'] ?? '')) ?? (Stage::fromLegacy((string) $r['status']) ?? Stage::Draft);
        return new JobAccess(
            $id, $stage, $r['creator'] !== null && $r['creator'] !== '' ? (string) $r['creator'] : null,
            $r['brand_id'] !== null ? (string) $r['brand_id'] : null, $assignments, $assignees,
            $r['sent_at'] !== null, (int) ($r['has_unsent_changes'] ?? 0) === 1, $started,
        );
    }

    /**
     * A new draft job with its brief, numbered from job_counters, plus the two
     * default tasks legacy add_job creates. The creator takes the slot of their
     * own role when it is AM, PM or Producer. Returns the job id.
     */
    public function createDraft(Campaign $campaign, string $title, User $actor, DateTimeImmutable $now): string
    {
        $id = Ids::new();
        $this->db->txImmediate(function (Db $tx) use ($id, $campaign, $title, $actor, $now): void {
            $number = $this->nextNumber($tx, $campaign->brandPrefix);
            $at = Ids::utc($now);
            $today = $now->format('Y-m-d');
            $tx->exec(
                "INSERT INTO jobs (id, job_number, campaign_id, title, status, stage, stage_changed_at, brief_date, sort_order, created_by, updated_by, row_version, created_at, updated_at)
                 VALUES (:id, :num, :camp, :title, 'Inbox', 'draft', :at, :today, 0, :actor, :actor, 1, :at, :at)",
                ['id' => $id, 'num' => $number, 'camp' => $campaign->id, 'title' => $title, 'at' => $at, 'today' => $today, 'actor' => $actor->id],
            );
            // The 0003 trigger already made the brief; this covers a database without it.
            $tx->exec(
                'INSERT INTO briefs (id, job_id, title, campaign_id, brief_date, created_by, created_at, updated_at)
                 SELECT :bid, :job, :title, :camp, :today, :actor, :at, :at WHERE NOT EXISTS (SELECT 1 FROM briefs WHERE job_id = :job)',
                ['bid' => Ids::new(), 'job' => $id, 'title' => $title, 'camp' => $campaign->id, 'today' => $today, 'actor' => $actor->id, 'at' => $at],
            );
            $tx->exec(
                'UPDATE briefs SET title = :title, campaign_id = :camp, brief_date = :today, created_by = :actor, updated_by = :actor, created_at = :at, updated_at = :at,
                        version_major = 0, version_minor = 1, version_patch = 0, sent_at = NULL WHERE job_id = :job',
                ['title' => $title, 'camp' => $campaign->id, 'today' => $today, 'actor' => $actor->id, 'at' => $at, 'job' => $id],
            );
            foreach (['copy' => 0, 'media' => 1] as $type => $sort) {
                $tx->exec(
                    "INSERT INTO tasks (id, job_id, type, status, sort_order, created_at, updated_at) VALUES (:id, :job, :type, 'Not Started', :sort, :at, :at)",
                    ['id' => Ids::new(), 'job' => $id, 'type' => $type, 'sort' => $sort, 'at' => $at],
                );
            }
            if (in_array($actor->role, [Role::AM, Role::PM, Role::Producer], true)) {
                $tx->exec('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :job, :user, :role)',
                    ['id' => Ids::new(), 'job' => $id, 'user' => $actor->id, 'role' => $actor->role->value]);
                if ($actor->role === Role::AM) {
                    $tx->exec('UPDATE jobs SET am_user_id = :u, row_version = row_version + 1 WHERE id = :id', ['u' => $actor->id, 'id' => $id]);
                }
            }
            $this->activity->append($tx, new ActivityEntry($id, $actor->id, 'job_created', 'job', $id, ['job_number' => $number, 'campaign_id' => $campaign->id]), $now);
        });
        return $id;
    }

    /**
     * Allocate PREFIX-NNN inside a write transaction: seed the counter from the
     * highest existing number the first time a prefix is seen, take next, bump
     * it, and skip numbers legacy already used.
     */
    public function nextNumber(Db $tx, string $prefix): string
    {
        $tx->exec(
            'INSERT OR IGNORE INTO job_counters (prefix, next)
             SELECT :p, COALESCE(MAX(CAST(SUBSTR(job_number, LENGTH(:p) + 2) AS INTEGER)), 0) + 1 FROM jobs WHERE job_number LIKE :like',
            ['p' => $prefix, 'like' => $prefix . '-%'],
        );
        for ($guard = 0; $guard < 1000; $guard++) {
            $n = (int) $tx->scalar('SELECT next FROM job_counters WHERE prefix = :p', ['p' => $prefix]);
            $tx->exec('UPDATE job_counters SET next = next + 1 WHERE prefix = :p', ['p' => $prefix]);
            $number = JobNumber::format($prefix, $n);
            if ($tx->scalar('SELECT 1 FROM jobs WHERE job_number = :n', ['n' => $number]) === null) {
                return $number;
            }
        }
        throw new \RuntimeException('No free job number for ' . $prefix);
    }

    /**
     * Apply a planned stage move. False when the row changed since it was read
     * (row_version), so the caller can show the latest state.
     * @param array<string,mixed> $data extra activity data (recipients, reason)
     */
    public function applyTransition(Job $job, TransitionOutcome $o, string $actorId, DateTimeImmutable $now, string $verb, array $data): bool
    {
        if (!$o->ok() || $o->to === null) {
            throw new \LogicException('applyTransition needs an allowed outcome');
        }
        $to = $o->to;
        return $this->db->txImmediate(function (Db $tx) use ($job, $o, $to, $actorId, $now, $verb, $data): bool {
            $paused = $to === Stage::Waiting || $to === Stage::OnHold;
            $n = $tx->exec(
                'UPDATE jobs SET stage = :stage, status = :status, stage_changed_at = :at, resume_stage = :resume, waiting_on = :won, waiting_reason = :wr,
                        updated_by = :actor, row_version = row_version + 1
                 WHERE id = :id AND row_version = :rv',
                [
                    'stage' => $to->value, 'status' => $to->toLegacy($job->status), 'at' => Ids::utc($now),
                    'resume' => $paused ? ($o->resumeStage !== null ? $o->resumeStage->value : ($job->resumeStage?->value)) : null,
                    'won' => $to === Stage::Waiting && $o->waitingOn !== null ? $o->waitingOn->value : null,
                    'wr' => $to === Stage::Waiting ? $o->reason : ($to === Stage::OnHold ? $o->reason : null),
                    'actor' => $actorId, 'id' => $job->id, 'rv' => $job->rowVersion,
                ],
            );
            if ($n === 0) {
                return false;
            }
            $payload = ['from' => $o->from->value, 'to' => $to->value, 'action' => $o->action->value] + $data;
            if ($o->reason !== '') {
                $payload['reason'] = $o->reason;
            }
            if ($o->waitingOn !== null) {
                $payload['waiting_on'] = $o->waitingOn->value;
            }
            $this->activity->append($tx, new ActivityEntry($job->id, $actorId, $verb, 'job', $job->id, $payload), $now);
            return true;
        });
    }

    /** "Make me AM": take the empty AM slot. False when someone holds it already. */
    public function claimAm(string $jobId, string $userId, DateTimeImmutable $now): bool
    {
        return $this->db->txImmediate(function (Db $tx) use ($jobId, $userId, $now): bool {
            if ($tx->scalar("SELECT 1 FROM job_assignments WHERE job_id = :j AND role_on_job = 'AM'", ['j' => $jobId]) !== null) {
                return false;
            }
            $tx->exec("INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, 'AM')", ['id' => Ids::new(), 'j' => $jobId, 'u' => $userId]);
            $tx->exec('UPDATE jobs SET am_user_id = :u, updated_by = :u, row_version = row_version + 1 WHERE id = :j', ['u' => $userId, 'j' => $jobId]);
            $this->activity->append($tx, new ActivityEntry($jobId, $userId, 'assigned_to_job', 'job', $jobId, ['role' => 'AM', 'user_id' => $userId, 'user_name' => (string) ($tx->scalar('SELECT name FROM users WHERE id = :u', ['u' => $userId]) ?? ''), 'claimed' => true, 'recipients' => []]), $now);
            return true;
        });
    }

    /**
     * Jobs the user owns: holds the AM, PM or Producer slot, or created the brief.
     * Open stages, plus closed ones changed in the last 14 days. Newest first.
     * @return list<BriefListItem>
     */
    public function listOwnedBy(string $userId, DateTimeImmutable $now): array
    {
        $rows = $this->db->query(
            self::LIST . " WHERE (EXISTS (SELECT 1 FROM job_assignments ja WHERE ja.job_id = j.id AND ja.user_id = :u AND ja.role_on_job IN ('AM', 'PM', 'Producer'))
                   OR COALESCE(br.created_by, j.created_by) = :u)
               AND (j.stage NOT IN ('done', 'archived', 'cancelled') OR br.updated_at >= :since)
             ORDER BY br.updated_at DESC, j.job_number LIMIT 200",
            ['u' => $userId, 'since' => Ids::utc($now->modify('-14 days'))],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = BriefListItem::fromRow($r);
        }
        return $out;
    }

    /** Open jobs nobody holds the AM slot on (legacy jobs, no backfill). @return list<BriefListItem> */
    public function listWithoutAm(int $limit = 100): array
    {
        $rows = $this->db->query(
            self::LIST . " WHERE j.stage NOT IN ('done', 'archived', 'cancelled')
               AND NOT EXISTS (SELECT 1 FROM job_assignments ja WHERE ja.job_id = j.id AND ja.role_on_job = 'AM')
             ORDER BY j.job_number LIMIT :n",
            ['n' => max(1, min(500, $limit))],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = BriefListItem::fromRow($r);
        }
        return $out;
    }
}

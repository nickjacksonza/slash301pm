<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\Activity;
use App\Domain\Types\ActivityEntry;
use DateTimeImmutable;

/** The activity table (migration 0006). append() runs inside the caller's transaction. */
final class ActivityStore
{
    public function __construct(private readonly Db $db) {}

    public function append(Db $tx, ActivityEntry $e, DateTimeImmutable $now): string
    {
        $id = Ids::new();
        $tx->exec(
            'INSERT INTO activity (id, job_id, actor_id, verb, entity_type, entity_id, data_json, created_at) VALUES (:id, :job, :actor, :verb, :et, :eid, :data, :at)',
            [
                'id' => $id, 'job' => $e->jobId, 'actor' => $e->actorId, 'verb' => $e->verb, 'et' => $e->entityType, 'eid' => $e->entityId,
                'data' => $e->data === [] ? null : json_encode($e->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'at' => Ids::utc($now),
            ],
        );
        return $id;
    }

    /** Newest first. @return list<Activity> */
    public function listForJob(string $jobId, int $limit = 20): array
    {
        $rows = $this->db->query(
            'SELECT a.id, a.job_id, a.actor_id, COALESCE(u.name, \'\') AS actor_name, a.verb, a.entity_type, a.entity_id, a.data_json, a.created_at
             FROM activity a LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.job_id = :j ORDER BY a.created_at DESC, a.rowid DESC LIMIT :n',
            ['j' => $jobId, 'n' => max(1, min(200, $limit))],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = Activity::fromRow($r);
        }
        return $out;
    }

    /** @return list<Activity> rows with this verb (tests, later the bell). */
    public function listByVerb(string $jobId, string $verb): array
    {
        $out = [];
        foreach ($this->db->query('SELECT a.*, COALESCE(u.name, \'\') AS actor_name FROM activity a LEFT JOIN users u ON u.id = a.actor_id WHERE a.job_id = :j AND a.verb = :v ORDER BY a.created_at, a.rowid', ['j' => $jobId, 'v' => $verb]) as $r) {
            $out[] = Activity::fromRow($r);
        }
        return $out;
    }
}

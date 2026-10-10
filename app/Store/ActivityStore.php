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

    /**
     * For autosave-driven edits: when the newest activity row of this job is
     * the same actor, verb and entity, younger than $windowSeconds, that row is
     * brought up to date (created_at = now, data.fields merged, data.edits
     * counted, data.first_at kept) instead of adding a row per keystroke burst.
     * Anything in between (another person, a send) starts a new row. Runs inside
     * the caller's transaction. Jobless entries are always appended.
     */
    public function appendCoalesced(Db $tx, ActivityEntry $e, DateTimeImmutable $now, int $windowSeconds): string
    {
        if ($e->jobId === null) {
            return $this->append($tx, $e, $now);
        }
        $last = $tx->one(
            'SELECT id, actor_id, verb, entity_id, data_json, created_at FROM activity WHERE job_id = :j ORDER BY created_at DESC, rowid DESC LIMIT 1',
            ['j' => $e->jobId],
        );
        $cutoff = Ids::utc($now->modify('-' . $windowSeconds . ' seconds'));
        $same = $last !== null && (string) $last['verb'] === $e->verb && (string) ($last['actor_id'] ?? '') === (string) $e->actorId
            && (string) $last['entity_id'] === $e->entityId && (string) $last['created_at'] >= $cutoff;
        if (!$same) {
            $data = $e->data + ['edits' => 1, 'first_at' => Ids::utc($now)];
            return $this->append($tx, new ActivityEntry($e->jobId, $e->actorId, $e->verb, $e->entityType, $e->entityId, $data), $now);
        }
        $old = $last['data_json'] === null ? [] : json_decode((string) $last['data_json'], true);
        $old = is_array($old) ? $old : [];
        $data = $e->data;
        $fields = [];
        foreach ([$old['fields'] ?? [], $e->data['fields'] ?? []] as $list) {
            foreach (is_array($list) ? $list : [] as $f) {
                if (is_string($f) && !in_array($f, $fields, true)) {
                    $fields[] = $f;
                }
            }
        }
        if ($fields !== []) {
            $data['fields'] = $fields;
        }
        $data['edits'] = (is_int($old['edits'] ?? null) ? $old['edits'] : 1) + 1;
        $data['first_at'] = is_string($old['first_at'] ?? null) ? $old['first_at'] : (string) $last['created_at'];
        $tx->exec('UPDATE activity SET data_json = :d, created_at = :at WHERE id = :id', [
            'd' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'at' => Ids::utc($now), 'id' => (string) $last['id'],
        ]);
        return (string) $last['id'];
    }

    /** Count of rows (tests and the audit coverage check). */
    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM activity');
    }

    /** The newest row overall, or null. */
    public function latest(): ?Activity
    {
        $r = $this->db->one("SELECT a.*, COALESCE(u.name, '') AS actor_name FROM activity a LEFT JOIN users u ON u.id = a.actor_id ORDER BY a.created_at DESC, a.rowid DESC LIMIT 1");
        return $r === null ? null : Activity::fromRow($r);
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

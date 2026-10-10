<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Role;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Assignment;
use App\Domain\Types\Team;
use DateTimeImmutable;

/**
 * job_assignments slots (UNIQUE per job and role, so one holder per slot).
 * Written the way legacy update_job does it (delete, then insert). Mirrors the
 * AM slot to jobs.am_user_id and the Copywriter / Designer slots to the default
 * copy / media tasks while those are unassigned or held by the previous person.
 */
final class AssignmentStore
{
    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    public function team(string $jobId): Team
    {
        $out = [];
        foreach ($this->db->query(
            'SELECT ja.role_on_job, ja.user_id, u.name AS user_name, u.role AS user_role FROM job_assignments ja JOIN users u ON u.id = ja.user_id WHERE ja.job_id = :j',
            ['j' => $jobId],
        ) as $r) {
            $out[] = Assignment::fromRow($r);
        }
        return new Team($out);
    }

    /**
     * Put $userId in the $slot. The user must be active and hold the same role
     * (a Traffic slot needs a Traffic user). Returns '' or the refusal reason.
     */
    public function set(string $jobId, Role $slot, string $userId, string $actorId, DateTimeImmutable $now): string
    {
        return $this->db->txImmediate(function (Db $tx) use ($jobId, $slot, $userId, $actorId, $now): string {
            $u = $tx->one('SELECT role, is_active, name FROM users WHERE id = :id', ['id' => $userId]);
            if ($u === null || (int) $u['is_active'] !== 1) {
                return 'That person is not an active user.';
            }
            if ((string) $u['role'] !== $slot->value) {
                return 'Only a ' . $slot->value . ' can hold the ' . $slot->value . ' slot.';
            }
            $old = $tx->scalar('SELECT user_id FROM job_assignments WHERE job_id = :j AND role_on_job = :r', ['j' => $jobId, 'r' => $slot->value]);
            $old = $old === null ? null : (string) $old;
            if ($old === $userId) {
                return '';
            }
            $tx->exec('DELETE FROM job_assignments WHERE job_id = :j AND role_on_job = :r', ['j' => $jobId, 'r' => $slot->value]);
            $tx->exec('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, :r)', ['id' => Ids::new(), 'j' => $jobId, 'u' => $userId, 'r' => $slot->value]);
            $this->mirror($tx, $jobId, $slot, $old, $userId, $actorId);
            if ($old !== null) {
                $this->activity->append($tx, new ActivityEntry($jobId, $actorId, 'unassigned_from_job', 'job', $jobId,
                    ['role' => $slot->value, 'user_id' => $old, 'user_name' => $this->name($tx, $old), 'recipients' => $old === $actorId ? [] : [$old]]), $now);
            }
            $this->activity->append($tx, new ActivityEntry($jobId, $actorId, 'assigned_to_job', 'job', $jobId,
                ['role' => $slot->value, 'user_id' => $userId, 'user_name' => (string) $u['name'], 'recipients' => $userId === $actorId ? [] : [$userId]]), $now);
            return '';
        });
    }

    /** Empty the slot (no-op when already empty). */
    public function unset(string $jobId, Role $slot, string $actorId, DateTimeImmutable $now): void
    {
        $this->db->txImmediate(function (Db $tx) use ($jobId, $slot, $actorId, $now): void {
            $old = $tx->scalar('SELECT user_id FROM job_assignments WHERE job_id = :j AND role_on_job = :r', ['j' => $jobId, 'r' => $slot->value]);
            if ($old === null) {
                return;
            }
            $tx->exec('DELETE FROM job_assignments WHERE job_id = :j AND role_on_job = :r', ['j' => $jobId, 'r' => $slot->value]);
            $this->mirror($tx, $jobId, $slot, (string) $old, null, $actorId);
            $this->activity->append($tx, new ActivityEntry($jobId, $actorId, 'unassigned_from_job', 'job', $jobId,
                ['role' => $slot->value, 'user_id' => (string) $old, 'user_name' => $this->name($tx, (string) $old), 'recipients' => (string) $old === $actorId ? [] : [(string) $old]]), $now);
        });
    }

    private function name(Db $tx, string $userId): string
    {
        return (string) ($tx->scalar('SELECT name FROM users WHERE id = :id', ['id' => $userId]) ?? '');
    }

    private function mirror(Db $tx, string $jobId, Role $slot, ?string $old, ?string $new, string $actorId): void
    {
        if ($slot === Role::AM) {
            $tx->exec('UPDATE jobs SET am_user_id = :u, updated_by = :a, row_version = row_version + 1 WHERE id = :j', ['u' => $new, 'a' => $actorId, 'j' => $jobId]);
        }
        $taskType = $slot === Role::Copywriter ? 'copy' : ($slot === Role::Designer ? 'media' : null);
        if ($taskType !== null) {
            $tx->exec(
                'UPDATE tasks SET assigned_to = :new WHERE job_id = :j AND type = :t AND (assigned_to IS NULL OR assigned_to = :old)',
                ['new' => $new, 'j' => $jobId, 't' => $taskType, 'old' => $old ?? ''],
            );
        }
    }
}

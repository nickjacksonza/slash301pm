<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\ActivityEntry;
use App\Domain\WaitingOn;
use DateTimeImmutable;

/**
 * Grid writes to job columns that are not brief-owned (brief fields go through
 * BriefStore::save, slots through AssignmentStore). Every write checks
 * row_version and bumps it itself, and logs activity in the same transaction.
 */
final class JobFieldStore
{
    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    /**
     * Change who or what a waiting job waits on. False when the row changed
     * since $rowVersion was read or the job is no longer waiting.
     */
    public function setWaiting(string $jobId, int $rowVersion, WaitingOn $on, string $reason, string $actorId, DateTimeImmutable $now): bool
    {
        return $this->db->txImmediate(function (Db $tx) use ($jobId, $rowVersion, $on, $reason, $actorId, $now): bool {
            $n = $tx->exec(
                "UPDATE jobs SET waiting_on = :w, waiting_reason = :r, updated_by = :a, row_version = row_version + 1
                 WHERE id = :id AND row_version = :rv AND stage = 'waiting'",
                ['w' => $on->value, 'r' => $reason, 'a' => $actorId, 'id' => $jobId, 'rv' => $rowVersion],
            );
            if ($n === 0) {
                return false;
            }
            $this->activity->append($tx, new ActivityEntry($jobId, $actorId, 'job_waiting_updated', 'job', $jobId,
                ['waiting_on' => $on->value, 'reason' => $reason, 'recipients' => []]), $now);
            return true;
        });
    }
}

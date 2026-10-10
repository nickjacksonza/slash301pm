<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\Team;

/**
 * Recipients of catalogue events (docs/roles.md section 3), resolved at the time
 * of the event and stored in the activity row (data.recipients). The actor never
 * notifies themselves; Client slots never receive agency events.
 */
final class Notifications
{
    /** Event names written by Phase 2. */
    public const BRIEF_SENT = 'brief_sent';
    public const BRIEF_UPDATED = 'brief_updated';
    public const BRIEF_RECALLED = 'brief_recalled';
    public const ASSIGNED_TO_JOB = 'assigned_to_job';
    public const UNASSIGNED_FROM_JOB = 'unassigned_from_job';
    public const DELIVERABLE_CANCELLED = 'deliverable_cancelled';
    public const STARTED_ASSET_CONFLICT = 'started_asset_conflict';
    public const JOB_WAITING_ON_YOU = 'job_waiting_on_you';
    public const JOB_RESUMED = 'job_resumed';
    public const JOB_ON_HOLD = 'job_on_hold';
    public const JOB_CANCELLED = 'job_cancelled';

    /**
     * @param list<string> $assetAssignees assets.assigned_to on the job
     * @return list<string>
     */
    public static function recipients(string $event, Team $team, array $assetAssignees, string $actorId): array
    {
        $traffic = $team->userFor(Role::Traffic);
        $ids = [];
        switch ($event) {
            case self::BRIEF_SENT:
                // Traffic must assign the team; pre-selected CD and creatives get an FYI.
                foreach ([Role::Traffic, Role::CD, Role::Copywriter, Role::Designer, Role::QA, Role::Developer, Role::SEO, Role::Social] as $r) {
                    $u = $team->userFor($r);
                    if ($u !== null) {
                        $ids[] = $u;
                    }
                }
                break;
            case self::BRIEF_UPDATED:
            case self::BRIEF_RECALLED:
            case self::JOB_RESUMED:
            case self::JOB_ON_HOLD:
            case self::JOB_CANCELLED:
                $ids = array_merge($team->agencyUserIds(), $assetAssignees);
                break;
            case self::DELIVERABLE_CANCELLED:
            case self::STARTED_ASSET_CONFLICT:
                if ($traffic !== null) {
                    $ids[] = $traffic;
                }
                $ids = array_merge($ids, $assetAssignees);
                break;
        }
        $out = [];
        foreach ($ids as $id) {
            if ($id !== '' && $id !== $actorId && !in_array($id, $out, true) && !self::isClientSlot($team, $id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /** The activity verb written for a stage move (catalogue names where one exists). */
    public static function verbFor(JobAction $a): string
    {
        return match ($a) {
            JobAction::Send => self::BRIEF_SENT,
            JobAction::Recall => self::BRIEF_RECALLED,
            JobAction::Wait => 'job_waiting',
            JobAction::Hold => self::JOB_ON_HOLD,
            JobAction::Resume => self::JOB_RESUMED,
            JobAction::Cancel => self::JOB_CANCELLED,
            JobAction::Archive => 'job_archived',
            JobAction::MarkDone => 'job_done',
            default => 'job_' . $a->value,
        };
    }

    /** Remove the actor from a list computed before the actor was known. @param list<string> $ids @return list<string> */
    public static function withoutActor(array $ids, string $actorId): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ($id !== $actorId) {
                $out[] = $id;
            }
        }
        return $out;
    }

    private static function isClientSlot(Team $team, string $userId): bool
    {
        foreach ($team->assignments as $a) {
            if ($a->userId === $userId && $a->userRole === Role::Client) {
                return true;
            }
        }
        return false;
    }
}

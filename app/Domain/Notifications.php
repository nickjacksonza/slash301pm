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
    // Social publishing (N34 to N39; N33 is the Social queue itself, see SocialPolicy)
    public const PUBLICATION_READY = 'publication_ready';
    public const PUBLICATION_SCHEDULED = 'publication_scheduled';
    public const PUBLICATION_LIVE = 'publication_live';
    public const PUBLICATION_PROMOTED = 'publication_promoted';
    public const PUBLICATION_ARCHIVED = 'publication_archived';
    public const PUBLICATION_REOPENED = 'publication_reopened';
    public const PUBLICATION_LINK_CHANGED = 'publication_link_changed';
    public const SOCIAL_ASSIGNED = 'social_assigned';
    /** Back to checking (Producer test reports): Social and the AM, like a reopen. */
    public const PUBLICATION_RECHECKED = 'publication_rechecked';
    /** Traffic, the COO or the ECD forced an asset or post status (owner decision 2026-10). */
    public const ASSET_STATUS_OVERRIDDEN = 'asset_status_overridden';

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

    // Social publishing
    /**
     * Recipients of the Social events (docs/roles.md N34 to N39). The AM is the
     * AM slot holder, or the brief creator when nobody holds it.
     * - publication_ready (N34): AM, plus the PM and Producer slot holders
     * - scheduled, live, promoted, archived (N35 to N38): AM
     * - reopened, link changed after Live (N39): Social on the job and AM
     * @return list<string>
     */
    public static function socialRecipients(string $event, Team $team, ?string $creatorId, string $actorId): array
    {
        $am = $team->userFor(Role::AM) ?? $creatorId;
        $ids = [];
        if ($am !== null) {
            $ids[] = $am;
        }
        switch ($event) {
            case self::PUBLICATION_READY:
                foreach ([Role::PM, Role::Producer] as $r) {
                    $u = $team->userFor($r);
                    if ($u !== null) {
                        $ids[] = $u;
                    }
                }
                break;
            case self::PUBLICATION_REOPENED:
            case self::PUBLICATION_RECHECKED:
            case self::PUBLICATION_LINK_CHANGED:
                $social = $team->userFor(Role::Social);
                if ($social !== null) {
                    $ids[] = $social;
                }
                break;
            case self::PUBLICATION_SCHEDULED:
            case self::PUBLICATION_LIVE:
            case self::PUBLICATION_PROMOTED:
            case self::PUBLICATION_ARCHIVED:
                break;
            default:
                $ids = [];
        }
        $out = [];
        foreach ($ids as $id) {
            if ($id !== '' && $id !== $actorId && !in_array($id, $out, true) && !self::isClientSlot($team, $id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Recipients of an asset status override: the asset's assignee, the job's
     * AM (the brief creator when nobody holds the AM slot) and the CD slot
     * holder. Never the actor, never a Client slot.
     * @return list<string>
     */
    public static function overrideRecipients(Team $team, ?string $creatorId, ?string $assetAssignee, string $actorId): array
    {
        $ids = [];
        foreach ([$assetAssignee, $team->userFor(Role::AM) ?? $creatorId, $team->userFor(Role::CD)] as $id) {
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $out = [];
        foreach ($ids as $id) {
            if ($id !== '' && $id !== $actorId && !in_array($id, $out, true) && !self::isClientSlot($team, $id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Edits of a draft or of the unsent working copy (BriefStore, BriefAssetStore).
     * Only people who may read the draft (view_brief_draft) are shown these.
     */
    public static function isWorkingCopyVerb(string $verb): bool
    {
        return in_array($verb, ['brief_edited', 'deliverable_added', 'deliverable_updated', 'deliverable_removed', 'deliverables_reordered'], true);
    }

    /** How a Social activity row reads ("marked Instagram Live"), or null for other verbs. @param array<string,mixed> $data */
    public static function socialPhrase(string $verb, array $data): ?string
    {
        $p = isset($data['platform']) && is_string($data['platform']) ? Platform::tryFrom($data['platform']) : null;
        $post = $p !== null ? 'the ' . $p->label() . ' post' : 'a post';
        $name = $p !== null ? $p->label() : 'a platform';
        $brief = ($data['scope'] ?? '') === 'brief';
        return match ($verb) {
            self::PUBLICATION_READY => $brief ? 'marked the whole brief Ready to schedule' : 'marked ' . $post . ' Ready to schedule',
            self::PUBLICATION_SCHEDULED => 'scheduled ' . $post,
            self::PUBLICATION_LIVE => 'marked ' . $post . ' Live',
            self::PUBLICATION_PROMOTED => (($data['promoted'] ?? null) === false ? 'unticked Promoted on ' : 'marked as promoted ') . $post,
            self::PUBLICATION_ARCHIVED => 'archived ' . $post,
            self::PUBLICATION_REOPENED => 'moved ' . $post . ' back a step',
            self::PUBLICATION_RECHECKED => 'sent ' . $post . ' back to checking',
            self::PUBLICATION_LINK_CHANGED => 'changed the live link of ' . $post,
            self::SOCIAL_ASSIGNED => 'assigned Social',
            self::ASSET_STATUS_OVERRIDDEN => 'overrode the status of ' . (isset($data['asset_name']) && is_string($data['asset_name']) && $data['asset_name'] !== '' ? $data['asset_name'] : 'an asset')
                . ($p !== null ? ' on ' . $p->label() : '')
                . (isset($data['from'], $data['to']) && is_string($data['from']) && is_string($data['to']) ? ' from ' . $data['from'] . ' to ' . $data['to'] : '')
                . (isset($data['reason']) && is_string($data['reason']) && $data['reason'] !== '' ? ': ' . $data['reason'] : ''),
            'demo_role_tasks_added' => 'added demo role tasks',
            'publication_added' => 'added ' . $name . ' to a social asset',
            'publication_removed' => 'removed ' . $name . ' from a social asset',
            'publication_checked' => 'updated the checklist of ' . $post,
            'publication_rescheduled' => 'changed the time of ' . $post,
            'publication_link_added' => 'added the live link of ' . $post,
            'job_ready_to_schedule' => 'moved the job to Ready to schedule',
            'job_schedule' => 'moved the job to Scheduled',
            'job_go_live' => 'moved the job to Live',
            'job_social_step_back' => 'moved the job back a Social step',
            default => null,
        };
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

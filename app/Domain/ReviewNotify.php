<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\JobReview;
use App\Domain\Types\Team;

/**
 * Activity verbs and recipients of internal review (owner spec 2026-10;
 * docs/roles.md N14, N16, N17). Recipients go in data.recipients of the
 * activity row written in the same transaction. The actor never notifies
 * themselves; a Client slot never receives these. Pure.
 *
 * - review_requested (Request review on a post, or every post handed in):
 *   the reviewer: the person the review was reassigned to (a CD or the ECD),
 *   else the CD slot holder, else every ECD.
 * - job_review_requested (the CD asks the ECD), job_cd_approved: every ECD.
 * - job_ecd_approved: the AM (or brief creator) and the CD (N16).
 * - asset_part_rejected: the maker of each rejected part (the other part stays).
 * - round_limit_warning: the AM (or brief creator).
 * - job_ready_for_client, job_sent_to_client: the AM (or brief creator) and Traffic.
 * - review_reassigned: the new reviewer (not a client: clients cannot sign in yet).
 * - asset_submitted, asset_part_approved: nobody (shown on the job's feed only).
 */
final class ReviewNotify
{
    public const ASSET_SUBMITTED = 'asset_submitted';
    public const REVIEW_REQUESTED = 'review_requested';
    public const PART_APPROVED = 'asset_part_approved';
    public const PART_REJECTED = 'asset_part_rejected';
    public const JOB_REVIEW_REQUESTED = 'job_review_requested';
    public const JOB_CD_APPROVED = 'job_cd_approved';
    public const JOB_ECD_APPROVED = 'job_ecd_approved';
    public const REVIEW_REASSIGNED = 'review_reassigned';
    public const JOB_READY_FOR_CLIENT = 'job_ready_for_client';
    public const JOB_SENT_TO_CLIENT = 'job_sent_to_client';
    public const ROUND_LIMIT_WARNING = 'round_limit_warning';

    /** @return list<string> */
    public static function verbs(): array
    {
        return [self::ASSET_SUBMITTED, self::REVIEW_REQUESTED, self::PART_APPROVED, self::PART_REJECTED, self::JOB_REVIEW_REQUESTED,
            self::JOB_CD_APPROVED, self::JOB_ECD_APPROVED, self::REVIEW_REASSIGNED, self::JOB_READY_FOR_CLIENT, self::JOB_SENT_TO_CLIENT,
            self::ROUND_LIMIT_WARNING];
    }

    /**
     * Who reviews the posts of this job: the reassigned CD or ECD, else the CD
     * slot holder, else every ECD.
     * @param list<string> $ecdIds active ECD users
     * @return list<string>
     */
    public static function reviewers(JobReview $jr, Team $team, array $ecdIds): array
    {
        if ($jr->assigneeId !== null && ($jr->assigneeRole === Role::CD || $jr->assigneeRole === Role::ECD)) {
            return [$jr->assigneeId];
        }
        $cd = $team->userFor(Role::CD);
        return $cd !== null ? [$cd] : $ecdIds;
    }

    /**
     * @param list<string> $ecdIds active ECD users
     * @param list<string> $extra event-specific people: the makers of the rejected parts, or the new reviewer
     * @return list<string>
     */
    public static function recipients(string $event, Team $team, JobReview $jr, array $ecdIds, ?string $creatorId, array $extra, string $actorId): array
    {
        $am = $team->userFor(Role::AM) ?? $creatorId;
        $ids = [];
        switch ($event) {
            case self::REVIEW_REQUESTED:
                $ids = self::reviewers($jr, $team, $ecdIds);
                break;
            case self::JOB_REVIEW_REQUESTED:
            case self::JOB_CD_APPROVED:
                $ids = $ecdIds;
                break;
            case self::JOB_ECD_APPROVED:
                $ids = [$am, $team->userFor(Role::CD)];
                break;
            case self::PART_REJECTED:
            case self::REVIEW_REASSIGNED:
                $ids = $extra;
                break;
            case self::ROUND_LIMIT_WARNING:
                $ids = [$am];
                break;
            case self::JOB_READY_FOR_CLIENT:
            case self::JOB_SENT_TO_CLIENT:
                $ids = [$am, $team->userFor(Role::Traffic)];
                break;
        }
        $out = [];
        foreach ($ids as $id) {
            if ($id !== null && $id !== '' && $id !== $actorId && !in_array($id, $out, true) && !self::isClientSlot($team, $id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /** How a review activity row reads in feeds ("rejected the copy of ..."), or null for other verbs. @param array<string,mixed> $data */
    public static function phrase(string $verb, array $data): ?string
    {
        $asset = isset($data['asset_name']) && is_string($data['asset_name']) && $data['asset_name'] !== '' ? $data['asset_name'] : 'a post';
        $parts = [];
        foreach (is_array($data['parts'] ?? null) ? $data['parts'] : [] as $p) {
            $part = is_string($p) ? ReviewPart::tryFrom($p) : null;
            if ($part !== null) {
                $parts[] = strtolower($part->label());
            }
        }
        $what = $parts === [] ? $asset : 'the ' . implode(' and ', $parts) . ' of ' . $asset;
        $round = is_int($data['round'] ?? null) ? ' (round ' . $data['round'] . ')' : '';
        return match ($verb) {
            self::ASSET_SUBMITTED => 'handed in ' . $what . $round,
            self::REVIEW_REQUESTED => ($data['scope'] ?? '') === 'job' ? 'handed in every post for review' : 'asked for a review of ' . $asset,
            self::PART_APPROVED => 'approved ' . $what,
            self::PART_REJECTED => (($data['decision'] ?? '') === ReviewDecision::RejectedWithFeedback->value ? 'asked for changes to ' : 'rejected ') . $what,
            self::JOB_REVIEW_REQUESTED => 'asked the ECD to review the job',
            self::JOB_CD_APPROVED => 'approved the job (CD)',
            self::JOB_ECD_APPROVED => 'approved the job (ECD)',
            self::REVIEW_REASSIGNED => 'reassigned the review' . (isset($data['to_name']) && is_string($data['to_name']) && $data['to_name'] !== '' ? ' to ' . $data['to_name'] : ''),
            self::JOB_READY_FOR_CLIENT => 'marked the job ready for client review',
            self::JOB_SENT_TO_CLIENT => 'sent the job to the client',
            self::ROUND_LIMIT_WARNING => $asset . ' passed the review round limit' . $round,
            'job_submit' => 'moved the job to In review',
            'job_send_back' => 'sent the job back to work',
            'job_approve_internal' => 'moved the job to Approved (internal)',
            default => null,
        };
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

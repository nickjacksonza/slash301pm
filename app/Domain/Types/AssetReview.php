<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\MediaKind;
use App\Domain\PartState;
use App\Domain\ReviewPart;
use App\Domain\ReviewRules;
use App\Domain\Role;

/**
 * One asset in review: the legacy assets row, its brief line, and its current
 * hand-in (the highest round in asset_submissions; round 0 and rowVersion 0
 * when nothing was handed in yet). copyMakerId / mediaMakerId are who last
 * handed in that part (asset_part_makers), or null. Go: type AssetReview struct.
 */
final class AssetReview
{
    public function __construct(
        public readonly string $assetId,
        public readonly string $jobId,
        public readonly string $assetName,
        public readonly string $assetType,
        public readonly ?string $templateId,
        public readonly string $assetStatus,
        public readonly ?string $assigneeId,
        public readonly ?Role $assigneeRole,
        public readonly string $assigneeName,
        public readonly string $lineLabel,
        public readonly string $channel,
        public readonly int $sortOrder,
        public readonly ?string $submissionId,
        public readonly int $round,
        public readonly string $copyText,
        public readonly string $mediaUrl,
        public readonly MediaKind $mediaKind,
        public readonly string $hashtags,
        public readonly string $linkUrl,
        public readonly string $note,
        public readonly PartState $copyState,
        public readonly PartState $mediaState,
        public readonly ?string $submittedBy,
        public readonly ?string $submittedAt,
        public readonly ?string $reviewRequestedAt,
        /** A part was rejected in the current round (the next hand-in opens a new round). */
        public readonly bool $rejectedInRound,
        public readonly int $rowVersion,
        public readonly ?string $copyMakerId,
        public readonly string $copyMakerName,
        public readonly ?string $mediaMakerId,
        public readonly string $mediaMakerName,
    ) {}

    public static function fromRow(array $r): self
    {
        $opt = static fn (string $k): ?string => isset($r[$k]) && $r[$k] !== null && $r[$k] !== '' ? (string) $r[$k] : null;
        $media = (string) ($r['media_url'] ?? '');
        return new self(
            (string) $r['asset_id'], (string) $r['job_id'], (string) $r['name'], (string) $r['type'], $opt('template_id'), (string) $r['status'],
            $opt('assigned_to'), Role::tryFrom((string) ($r['assignee_role'] ?? '')), (string) ($r['assignee_name'] ?? ''),
            (string) ($r['line_label'] ?? ''), (string) ($r['channel'] ?? ''), (int) ($r['sort_order'] ?? 0),
            $opt('sub_id'), (int) ($r['round'] ?? 0), (string) ($r['copy_text'] ?? ''), $media,
            MediaKind::tryFrom((string) ($r['media_kind'] ?? '')) ?? MediaKind::fromUrl($media),
            (string) ($r['hashtags'] ?? ''), (string) ($r['link_url'] ?? ''), (string) ($r['note'] ?? ''),
            PartState::read($r['copy_state'] ?? null), PartState::read($r['media_state'] ?? null),
            $opt('submitted_by'), $opt('submitted_at'), $opt('review_requested_at'), (int) ($r['rejected_in_round'] ?? 0) === 1,
            (int) ($r['row_version'] ?? 0),
            $opt('copy_maker'), (string) ($r['copy_maker_name'] ?? ''), $opt('media_maker'), (string) ($r['media_maker_name'] ?? ''),
        );
    }

    /** @return list<ReviewPart> */
    public function requiredParts(): array
    {
        return ReviewRules::requiredParts($this->assetType);
    }

    public function needs(ReviewPart $p): bool
    {
        return in_array($p, $this->requiredParts(), true);
    }

    public function state(ReviewPart $p): PartState
    {
        return $p === ReviewPart::Copy ? $this->copyState : $this->mediaState;
    }

    public function makerId(ReviewPart $p): ?string
    {
        return $p === ReviewPart::Copy ? $this->copyMakerId : $this->mediaMakerId;
    }

    public function makerName(ReviewPart $p): string
    {
        return $p === ReviewPart::Copy ? $this->copyMakerName : $this->mediaMakerName;
    }

    public function hasSubmission(): bool
    {
        return $this->round > 0;
    }

    /** Every required part approved. */
    public function allApproved(): bool
    {
        foreach ($this->requiredParts() as $p) {
            if ($this->state($p) !== PartState::Approved) {
                return false;
            }
        }
        return true;
    }

    /** A required part waits for a reviewer's decision. */
    public function waitingReview(): bool
    {
        foreach ($this->requiredParts() as $p) {
            if ($this->state($p) === PartState::Submitted) {
                return true;
            }
        }
        return false;
    }

    public function anyRejected(): bool
    {
        foreach ($this->requiredParts() as $p) {
            if ($this->state($p)->isRejected()) {
                return true;
            }
        }
        return false;
    }

    /** Every required part handed in (submitted, approved or rejected). */
    public function complete(): bool
    {
        foreach ($this->requiredParts() as $p) {
            if (!$this->state($p)->hasContent()) {
                return false;
            }
        }
        return true;
    }
}

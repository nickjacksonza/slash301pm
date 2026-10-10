<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\AssetReview;
use App\Domain\Types\JobReview;
use App\Domain\Types\SubmissionInput;
use App\Domain\Types\SubmissionPlan;
use App\Domain\Types\Team;

/**
 * Internal review rules (owner spec 2026-10). Pure: no clock, no database.
 *
 * - A post has a copy part (copy text, hashtags, link) and, unless it is a
 *   copy-only deliverable, a media part (a link). Makers hand them in; a
 *   reviewer approves or rejects each part separately.
 * - Rounds are counted per asset: the first hand-in is round 1; handing in a
 *   rejected part again after a rejection in the current round opens the next
 *   round, copying the other part forward (an approved part stays approved).
 *   An approved part is frozen until a reviewer rejects it.
 * - The job counts as CD-approvable when every non-cancelled asset has every
 *   part approved (aggregate() = AllApproved).
 * - A rejection clears the CD and ECD approvals and the client steps, and the
 *   job goes back to in_progress (stageMoves()).
 * Go: package domain, func RequiredParts, PlanSubmission, ...
 */
final class ReviewRules
{
    public const MAX_COPY = 10000;
    public const MAX_HASHTAGS = 1000;
    public const MAX_NOTE = 1000;
    public const MAX_FEEDBACK = 4000;
    /** The owner's default: the AM is warned when an asset reaches round 4. */
    public const DEFAULT_ROUND_LIMIT = 3;

    /** Copy deliverables (legacy assets.type 'copy') have no media part. @return list<ReviewPart> */
    public static function requiredParts(string $assetType): array
    {
        return $assetType === 'copy' ? [ReviewPart::Copy] : [ReviewPart::Copy, ReviewPart::Media];
    }

    /**
     * Who should make a part when nobody has handed it in yet. The asset's own
     * assignee wins for the part their role makes (a Copywriter the copy, a
     * Designer the media, any other role both); otherwise the job's Copywriter
     * slot makes the copy and the Designer slot the media.
     */
    public static function derivedMaker(AssetReview $a, ReviewPart $p, Team $team): ?string
    {
        if (!$a->needs($p)) {
            return null;
        }
        if ($a->assigneeId !== null && self::roleMakes($a->assigneeRole, $p)) {
            return $a->assigneeId;
        }
        return $team->userFor($p === ReviewPart::Copy ? Role::Copywriter : Role::Designer);
    }

    /** The maker a rejection of this part goes to: whoever handed it in, else the derived maker. */
    public static function maker(AssetReview $a, ReviewPart $p, Team $team): ?string
    {
        return $a->makerId($p) ?? self::derivedMaker($a, $p, $team);
    }

    /**
     * Is $userId one of the makers of this part? The part's slot holder
     * (Copywriter for copy, Designer for media), the asset's assignee when
     * their role makes this part, or whoever handed it in last.
     */
    public static function isMakerOf(string $userId, AssetReview $a, ReviewPart $p, Team $team): bool
    {
        if (!$a->needs($p)) {
            return false;
        }
        if ($a->makerId($p) === $userId) {
            return true;
        }
        if ($a->assigneeId === $userId && self::roleMakes($a->assigneeRole, $p)) {
            return true;
        }
        return $team->userFor($p === ReviewPart::Copy ? Role::Copywriter : Role::Designer) === $userId;
    }

    private static function roleMakes(?Role $role, ReviewPart $p): bool
    {
        if ($role === null) {
            return false;
        }
        if ($role === Role::Copywriter) {
            return $p === ReviewPart::Copy;
        }
        if ($role === Role::Designer) {
            return $p === ReviewPart::Media;
        }
        return true;
    }

    /** Field errors in what a maker typed, for the parts they may hand in. @param list<ReviewPart> $editable */
    public static function validateInput(SubmissionInput $in, array $editable): ValidationErrors
    {
        $e = new ValidationErrors();
        if (in_array(ReviewPart::Copy, $editable, true)) {
            if (mb_strlen($in->copyText) > self::MAX_COPY) {
                $e = $e->with('copy_text', 'Keep the copy under ' . self::MAX_COPY . ' characters.');
            } elseif (self::hasControl($in->copyText)) {
                $e = $e->with('copy_text', 'The copy contains characters that cannot be stored.');
            }
            if (mb_strlen($in->hashtags) > self::MAX_HASHTAGS) {
                $e = $e->with('hashtags', 'Keep the hashtags under ' . self::MAX_HASHTAGS . ' characters.');
            } elseif (self::hasControl($in->hashtags)) {
                $e = $e->with('hashtags', 'The hashtags contain characters that cannot be stored.');
            }
            if ($in->linkUrl !== '' && !Links::isHttpsUrl($in->linkUrl)) {
                $e = $e->with('link_url', 'The link must be an https:// address (no other kinds of link).');
            }
        }
        if (in_array(ReviewPart::Media, $editable, true) && $in->mediaUrl !== '' && !Links::isServerLink($in->mediaUrl)) {
            $e = $e->with('media_url', 'The media link must be an http(s) link or a server path (no other kinds of link).');
        }
        if (mb_strlen($in->note) > self::MAX_NOTE) {
            $e = $e->with('note', 'Keep the note under ' . self::MAX_NOTE . ' characters.');
        } elseif (self::hasControl($in->note)) {
            $e = $e->with('note', 'The note contains characters that cannot be stored.');
        }
        return $e;
    }

    /**
     * What a hand-in (Save, or Request review) writes. Parts the user may not
     * make keep their stored values whatever was sent. A changed part becomes
     * Submitted (Missing when emptied). An approved part refuses changes.
     * @param list<ReviewPart> $editable
     */
    public static function planSubmission(AssetReview $cur, SubmissionInput $in, array $editable, bool $requestReview): SubmissionPlan
    {
        $errors = self::validateInput($in, $editable);
        $copyText = $cur->copyText;
        $hashtags = $cur->hashtags;
        $linkUrl = $cur->linkUrl;
        $mediaUrl = $cur->mediaUrl;
        $changed = [];
        if (in_array(ReviewPart::Copy, $editable, true) && $cur->needs(ReviewPart::Copy)
            && ($in->copyText !== $cur->copyText || $in->hashtags !== $cur->hashtags || $in->linkUrl !== $cur->linkUrl)) {
            if ($cur->copyState === PartState::Approved) {
                $errors = $errors->with('copy_text', 'The copy is approved, so it cannot change. Ask the CD to reopen it.');
            } else {
                $copyText = $in->copyText;
                $hashtags = $in->hashtags;
                $linkUrl = $in->linkUrl;
                $changed[] = ReviewPart::Copy;
            }
        }
        if (in_array(ReviewPart::Media, $editable, true) && $cur->needs(ReviewPart::Media) && $in->mediaUrl !== $cur->mediaUrl) {
            if ($cur->mediaState === PartState::Approved) {
                $errors = $errors->with('media_url', 'The media is approved, so it cannot change. Ask the CD to reopen it.');
            } else {
                $mediaUrl = $in->mediaUrl;
                $changed[] = ReviewPart::Media;
            }
        }
        $noteChanged = $in->note !== $cur->note;
        $copyState = $cur->needs(ReviewPart::Copy) ? $cur->copyState : PartState::Missing;
        $mediaState = $cur->needs(ReviewPart::Media) ? $cur->mediaState : PartState::Missing;
        if (in_array(ReviewPart::Copy, $changed, true)) {
            $copyState = trim($copyText) === '' ? PartState::Missing : PartState::Submitted;
        }
        if (in_array(ReviewPart::Media, $changed, true)) {
            $mediaState = trim($mediaUrl) === '' ? PartState::Missing : PartState::Submitted;
        }
        $round = $cur->round;
        $newRound = false;
        if ($round === 0) {
            $round = 1;
            $newRound = true;
        } elseif ($cur->rejectedInRound
            && ((in_array(ReviewPart::Copy, $changed, true) && $cur->copyState->isRejected())
                || (in_array(ReviewPart::Media, $changed, true) && $cur->mediaState->isRejected()))) {
            $round++;
            $newRound = true;
        }
        if ($errors->isEmpty()) {
            if ($changed === [] && !$noteChanged && !$requestReview) {
                $errors = $errors->with('submission', 'Nothing changed.');
            } elseif ($requestReview) {
                $errors = self::reviewReadiness($cur, $copyState, $mediaState, $errors);
            } elseif ($cur->round === 0 && $changed === []) {
                $errors = $errors->with('submission', 'Hand in the copy or the media first.');
            }
        }
        return new SubmissionPlan($errors, $round, $newRound, $copyText, $mediaUrl, MediaKind::fromUrl($mediaUrl), $hashtags, $linkUrl,
            $noteChanged ? $in->note : $cur->note, $copyState, $mediaState, $changed, $requestReview);
    }

    /** Request review needs every part handed in, none still rejected, and something new to look at. */
    private static function reviewReadiness(AssetReview $cur, PartState $copy, PartState $media, ValidationErrors $e): ValidationErrors
    {
        $states = [];
        foreach ($cur->requiredParts() as $p) {
            $states[$p->value] = $p === ReviewPart::Copy ? $copy : $media;
        }
        $missing = [];
        $rejected = [];
        $waiting = false;
        foreach ($cur->requiredParts() as $p) {
            $s = $states[$p->value];
            if (!$s->hasContent()) {
                $missing[] = strtolower($p->label());
            } elseif ($s->isRejected()) {
                $rejected[] = strtolower($p->label());
            } elseif ($s === PartState::Submitted) {
                $waiting = true;
            }
        }
        if ($missing !== []) {
            return $e->with('submission', 'Hand in the ' . implode(' and the ', $missing) . ' before asking for review.');
        }
        if ($rejected !== []) {
            return $e->with('submission', 'Hand in the ' . implode(' and the ', $rejected) . ' again before asking for review.');
        }
        if (!$waiting) {
            return $e->with('submission', 'Nothing new to review: every part is approved.');
        }
        return $e;
    }

    /** The parts a reviewer's choice covers: both (every required part), copy or media. @return list<ReviewPart> */
    public static function partsFor(string $choice, AssetReview $a): array
    {
        if ($choice === ReviewPart::Copy->value || $choice === ReviewPart::Media->value) {
            $p = ReviewPart::from($choice);
            return $a->needs($p) ? [$p] : [];
        }
        return $choice === 'both' || $choice === '' ? $a->requiredParts() : [];
    }

    /** @param list<ReviewPart> $parts */
    public static function reviewProblems(AssetReview $a, array $parts, ReviewDecision $d, string $feedback): ValidationErrors
    {
        $e = new ValidationErrors();
        if ($parts === []) {
            return $e->with('part', 'Choose the copy, the media or both.');
        }
        $missing = [];
        $already = true;
        foreach ($parts as $p) {
            if (!$a->state($p)->hasContent()) {
                $missing[] = strtolower($p->label());
            }
            if ($a->state($p) !== $d->state()) {
                $already = false;
            }
        }
        if ($missing !== []) {
            return $e->with('part', 'The ' . implode(' and the ', $missing) . ' has not been handed in yet.');
        }
        if ($d === ReviewDecision::RejectedWithFeedback && trim($feedback) === '') {
            return $e->with('feedback', 'Write the feedback for the maker.');
        }
        if (mb_strlen($feedback) > self::MAX_FEEDBACK) {
            return $e->with('feedback', 'Keep the feedback under ' . self::MAX_FEEDBACK . ' characters.');
        }
        if (self::hasControl($feedback)) {
            return $e->with('feedback', 'The feedback contains characters that cannot be stored.');
        }
        if ($already && $d === ReviewDecision::Approved) {
            return $e->with('part', 'Already approved.');
        }
        return $e;
    }

    /** @param list<AssetReview> $assets the job's non-cancelled assets */
    public static function aggregate(array $assets): JobReviewStatus
    {
        $live = [];
        foreach ($assets as $a) {
            if (!AssetStatus::isCancelled($a->assetStatus)) {
                $live[] = $a;
            }
        }
        if ($live === []) {
            return JobReviewStatus::Empty;
        }
        $complete = true;
        $approved = true;
        foreach ($live as $a) {
            if ($a->anyRejected()) {
                return JobReviewStatus::NeedsChanges;
            }
            $complete = $complete && $a->complete();
            $approved = $approved && $a->allApproved();
        }
        if ($approved) {
            return JobReviewStatus::AllApproved;
        }
        return $complete ? JobReviewStatus::WaitingReview : JobReviewStatus::InProgress;
    }

    /**
     * The named stage moves a review event implies, in order, from the job's
     * stage (each is checked again by Transitions::plan when applied). A paused,
     * client-approved or closed job never moves here.
     * @return list<JobAction>
     */
    public static function stageMoves(Stage $from, ReviewEvent $e): array
    {
        return match ($e) {
            ReviewEvent::Submitted => $from === Stage::Briefed ? [JobAction::Start] : [],
            ReviewEvent::ReviewRequested => match ($from) {
                Stage::Briefed => [JobAction::Start, JobAction::Submit],
                Stage::InProgress => [JobAction::Submit],
                default => [],
            },
            ReviewEvent::Rejected => ($from === Stage::InReview || $from === Stage::ApprovedInternal) ? [JobAction::SendBack] : [],
            ReviewEvent::EcdApproved => match ($from) {
                Stage::InProgress => [JobAction::Submit, JobAction::ApproveInternal],
                Stage::InReview => [JobAction::ApproveInternal],
                default => [],
            },
        };
    }

    /** Warn the AM when a hand-in opens a round past the limit (limit 3: at round 4, 5, ...). 0 turns the warning off. */
    public static function passesRoundLimit(int $round, bool $newRound, int $limit): bool
    {
        return $newRound && $limit > 0 && $round > $limit;
    }

    /** Does a rejection now take something back (an approval, ready or sent for the client)? */
    public static function rejectionClears(JobReview $jr): bool
    {
        return $jr->cdApproved() || $jr->ecdApproved() || $jr->readyForClient() || $jr->sentToClient() || $jr->ecdRequestedAt !== null;
    }

    private static function hasControl(string $v): bool
    {
        return preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $v) === 1;
    }
}

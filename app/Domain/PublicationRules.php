<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\Publication;
use App\Domain\Types\PublicationOutcome;
use App\Domain\Types\SocialAssetState;
use DateTimeImmutable;

/**
 * Social publishing rules (docs/roles.md 2.13). Pure.
 *
 * One publication per asset and platform:
 *   checking -> ready_to_schedule   every checklist item ticked
 *   ready_to_schedule -> scheduled  scheduled_at required
 *   scheduled -> live               live link required (http or https, Links::isWebUrl)
 *   any but archived -> archived    reason required
 *   reopen: one step back           reason required (COO, ECD, assigned Social)
 *
 * The job follows its publications: its Social stage is the lowest status over
 * the job's social assets, where an asset's status is the lowest of its
 * non-archived publications (an asset with none is still being checked, an
 * asset whose every publication is archived no longer counts). The job only
 * moves forward on its own; it moves back only through a reopen.
 */
final class PublicationRules
{
    public const MAX_REASON = 1000;

    /** A non-cancelled asset made from a social template or on a social channel. */
    public static function isSocialAsset(?string $templateId, string $channel, string $assetStatus): bool
    {
        if (AssetStatus::isCancelled($assetStatus)) {
            return false;
        }
        if ($templateId !== null && str_starts_with($templateId, 'social-')) {
            return true;
        }
        return Platform::allFromChannel($channel) !== [];
    }

    /** Stages in which publications are worked on (the client has approved). */
    public static function isSocialWindow(Stage $s): bool
    {
        return $s === Stage::ApprovedClient || $s === Stage::ReadyToSchedule || $s === Stage::Scheduled || $s === Stage::Live;
    }

    public static function plan(Publication $p, PublicationAction $a, string $scheduledAtInput, string $liveUrlInput, string $reason): PublicationOutcome
    {
        $errors = new ValidationErrors();
        $reason = trim($reason);
        if (mb_strlen($reason) > self::MAX_REASON) {
            $errors = $errors->with('reason', 'Keep the reason under 1000 characters.');
        } elseif (preg_match('/[\x00-\x1f\x7f]/', $reason) === 1) {
            $errors = $errors->with('reason', 'Keep the reason on one line.');
        }
        $scheduledAt = $p->scheduledAt;
        $liveUrl = $p->liveUrl;
        $from = $p->status;
        $to = null;
        switch ($a) {
            case PublicationAction::Ready:
                $to = $from === PublicationStatus::Checking ? PublicationStatus::ReadyToSchedule : null;
                if ($to !== null && !$p->checklist->isComplete()) {
                    $errors = $errors->with('checklist', 'Tick every item first. Still open: ' . $p->checklist->missingText() . '.');
                }
                break;
            case PublicationAction::Schedule:
                $to = $from === PublicationStatus::ReadyToSchedule ? PublicationStatus::Scheduled : null;
                if ($to !== null) {
                    $in = trim($scheduledAtInput) !== '' ? self::normaliseScheduledAt($scheduledAtInput) : $scheduledAt;
                    if ($in === null) {
                        $errors = $errors->with('scheduled_at', 'Enter the date and time the post is scheduled for.');
                    } elseif ($in === false) {
                        $errors = $errors->with('scheduled_at', 'Enter the scheduled time as a date and time.');
                    } else {
                        $scheduledAt = $in;
                    }
                }
                break;
            case PublicationAction::GoLive:
                $to = $from === PublicationStatus::Scheduled ? PublicationStatus::Live : null;
                if ($to !== null) {
                    $url = trim($liveUrlInput) !== '' ? trim($liveUrlInput) : $liveUrl;
                    $problem = self::liveUrlProblem($url);
                    if ($problem !== '') {
                        $errors = $errors->with('live_url', $problem);
                    } else {
                        $liveUrl = $url;
                    }
                }
                break;
            case PublicationAction::Archive:
                $to = $from !== PublicationStatus::Archived ? PublicationStatus::Archived : null;
                if ($to !== null && $reason === '') {
                    $errors = $errors->with('reason', 'Say why the post is archived.');
                }
                break;
            case PublicationAction::Reopen:
                $to = $from->previous();
                if ($to !== null && $reason === '') {
                    $errors = $errors->with('reason', 'Say why it moves back a step.');
                }
                break;
        }
        if ($to === null) {
            $errors = $errors->with('status', $a->label() . ' is not possible while the post is ' . strtolower($from->label()) . '.');
        }
        return new PublicationOutcome($a, $from, $to, $errors, $scheduledAt, $liveUrl, $reason);
    }

    /**
     * A datetime-local value ('2026-10-12T09:30', seconds optional) or
     * 'Y-m-d H:i' as 'Y-m-d H:i'; null for ''; false when it is not a real time.
     */
    public static function normaliseScheduledAt(string $v): string|false|null
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(:\d{2})?$/', $v, $m) !== 1) {
            return false;
        }
        $y = (int) $m[1];
        if ($y < 2000 || $y > 2100 || !checkdate((int) $m[2], (int) $m[3], $y) || (int) $m[4] > 23 || (int) $m[5] > 59) {
            return false;
        }
        return sprintf('%04d-%02d-%02d %02d:%02d', $y, (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5]);
    }

    /** '' when the live link is fine. Only http and https links are accepted (never javascript: or data:). */
    public static function liveUrlProblem(string $url): string
    {
        if ($url === '') {
            return 'Paste the live link of the post first.';
        }
        if (!Links::isWebUrl($url)) {
            return 'The live link must be a web address starting with http:// or https://.';
        }
        return '';
    }

    /** Scheduled time may change while the post is ready or scheduled. */
    public static function canEditScheduledAt(PublicationStatus $s): bool
    {
        return $s === PublicationStatus::ReadyToSchedule || $s === PublicationStatus::Scheduled;
    }

    /** Live link and Promoted: from scheduled on (social_edit_live_link, social_set_promoted). */
    public static function canEditLiveFields(PublicationStatus $s): bool
    {
        return $s === PublicationStatus::Scheduled || $s === PublicationStatus::Live;
    }

    /** The checklist can change until the post is Ready to schedule. */
    public static function canEditChecklist(PublicationStatus $s): bool
    {
        return $s === PublicationStatus::Checking;
    }

    /**
     * The asset's status: the lowest of its non-archived publications;
     * Checking with none at all; null when every one is archived.
     * @param list<PublicationStatus> $statuses
     */
    public static function assetStatus(array $statuses): ?PublicationStatus
    {
        if ($statuses === []) {
            return PublicationStatus::Checking;
        }
        $min = null;
        foreach ($statuses as $s) {
            if ($s === PublicationStatus::Archived) {
                continue;
            }
            $min = $min === null || $s->rank() < $min ? $s->rank() : $min;
        }
        return $min === null ? null : PublicationStatus::fromRank($min);
    }

    /**
     * The Social stage the job's publications put it in, or null when no
     * social asset counts (none, or all archived).
     * @param list<SocialAssetState> $assets
     */
    public static function jobTarget(array $assets): ?Stage
    {
        $min = null;
        foreach ($assets as $a) {
            $s = self::assetStatus($a->statuses);
            if ($s === null) {
                continue;
            }
            $min = $min === null || $s->rank() < $min ? $s->rank() : $min;
        }
        if ($min === null) {
            return null;
        }
        $status = PublicationStatus::fromRank($min);
        return $status === null ? null : $status->jobStage();
    }

    /**
     * The named job moves, one step at a time, from the job's stage to the
     * target. Only inside the Social window; backward only when $allowBack (a
     * reopen). Empty when nothing changes.
     * @return list<JobAction>
     */
    public static function jobSteps(Stage $from, ?Stage $target, bool $allowBack): array
    {
        if ($target === null || !self::isSocialWindow($from) || !self::isSocialWindow($target)) {
            return [];
        }
        $order = [Stage::ApprovedClient, Stage::ReadyToSchedule, Stage::Scheduled, Stage::Live];
        $i = (int) array_search($from, $order, true);
        $j = (int) array_search($target, $order, true);
        $out = [];
        if ($j > $i) {
            $forward = [JobAction::ReadyToSchedule, JobAction::Schedule, JobAction::GoLive];
            for ($k = $i; $k < $j; $k++) {
                $out[] = $forward[$k];
            }
        } elseif ($j < $i && $allowBack) {
            for ($k = $i; $k > $j; $k--) {
                $out[] = JobAction::SocialStepBack;
            }
        }
        return $out;
    }

    /**
     * Scheduled today (South African date of $now) and past due: a scheduled
     * post whose time has passed probably went out and still needs its link.
     */
    public static function isScheduledOn(?string $scheduledAt, string $todaySast): bool
    {
        return $scheduledAt !== null && substr($scheduledAt, 0, 10) === $todaySast;
    }

    public static function isOverdueForLink(Publication $p, DateTimeImmutable $nowSast): bool
    {
        if ($p->status === PublicationStatus::Live) {
            return $p->liveUrl === '';
        }
        return $p->status === PublicationStatus::Scheduled && $p->scheduledAt !== null && $p->scheduledAt <= $nowSast->format('Y-m-d H:i');
    }
}

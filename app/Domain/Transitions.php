<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\TransitionOutcome;
use App\Domain\Types\TransitionRequest;

/**
 * The stage machine. Pure: who may make a move is Policy's job; this says which
 * moves exist, what they need (reasons, waiting_on) and where they land.
 *
 *   draft -> briefed            send (BriefRules checked by the caller)
 *   briefed -> draft            recall, only if no asset has started
 *   workable -> waiting         wait (waiting_on + reason)
 *   workable -> on_hold         hold (reason)
 *   waiting|on_hold -> resume   back to the stored resume stage (in_progress if none)
 *   open -> cancelled           cancel (reason)
 *   done|cancelled -> archived  archive
 *   briefed -> in_progress, in_progress -> in_review, in_review -> in_progress (send back, reason),
 *   in_review -> approved_internal, approved_internal -> approved_client,
 *   approved_client -> ready_to_schedule -> scheduled -> live (social),
 *   live -> scheduled -> ready_to_schedule -> approved_client (social step back, reason),
 *   approved_client|live -> done
 */
final class Transitions
{
    public const MAX_REASON = 1000;

    public static function plan(Stage $from, ?Stage $storedResume, TransitionRequest $req, bool $anyAssetStarted): TransitionOutcome
    {
        $errors = new ValidationErrors();
        $reason = trim($req->reason);
        if (mb_strlen($reason) > self::MAX_REASON) {
            $errors = $errors->with('reason', 'Keep the reason under 1000 characters.');
        }
        $a = $req->action;
        $to = null;
        $resume = null;
        $waitingOn = null;
        switch ($a) {
            case JobAction::Send:
                $to = $from === Stage::Draft ? Stage::Briefed : null;
                break;
            case JobAction::Recall:
                $to = $from === Stage::Briefed ? Stage::Draft : null;
                if ($to !== null && $anyAssetStarted) {
                    $errors = $errors->with('assets', 'An asset has started, so the brief cannot be recalled. Send an update instead.');
                }
                break;
            case JobAction::Start:
                $to = $from === Stage::Briefed ? Stage::InProgress : null;
                break;
            case JobAction::Submit:
                $to = $from === Stage::InProgress ? Stage::InReview : null;
                break;
            case JobAction::SendBack:
                $to = $from === Stage::InReview ? Stage::InProgress : null;
                if ($to !== null && $reason === '') {
                    $errors = $errors->with('reason', 'Say what needs to change.');
                }
                break;
            case JobAction::ApproveInternal:
                $to = $from === Stage::InReview ? Stage::ApprovedInternal : null;
                break;
            case JobAction::ApproveClient:
                $to = $from === Stage::ApprovedInternal ? Stage::ApprovedClient : null;
                break;
            case JobAction::ReadyToSchedule:
                $to = $from === Stage::ApprovedClient ? Stage::ReadyToSchedule : null;
                break;
            case JobAction::Schedule:
                $to = $from === Stage::ReadyToSchedule ? Stage::Scheduled : null;
                break;
            case JobAction::GoLive:
                $to = $from === Stage::Scheduled ? Stage::Live : null;
                break;
            // Social publishing: only PublicationStore makes this move (a reopened publication).
            case JobAction::SocialStepBack:
                $to = match ($from) {
                    Stage::ReadyToSchedule => Stage::ApprovedClient,
                    Stage::Scheduled => Stage::ReadyToSchedule,
                    Stage::Live => Stage::Scheduled,
                    default => null,
                };
                if ($to !== null && $reason === '') {
                    $errors = $errors->with('reason', 'Say why it moves back a step.');
                }
                break;
            case JobAction::MarkDone:
                $to = ($from === Stage::ApprovedClient || $from === Stage::Live) ? Stage::Done : null;
                break;
            case JobAction::Wait:
                $to = $from->isWorkable() ? Stage::Waiting : null;
                if ($to !== null) {
                    $resume = $from;
                    $waitingOn = $req->waitingOn;
                    if ($waitingOn === null) {
                        $errors = $errors->with('waiting_on', 'Choose who the job is waiting on.');
                    }
                    if ($reason === '') {
                        $errors = $errors->with('reason', 'Say what the job is waiting for.');
                    }
                }
                break;
            case JobAction::Hold:
                $to = $from->isWorkable() ? Stage::OnHold : null;
                if ($to !== null) {
                    $resume = $from;
                    if ($reason === '') {
                        $errors = $errors->with('reason', 'Say why the job is on hold.');
                    }
                }
                break;
            case JobAction::Resume:
                $to = $from->isPaused() ? ($storedResume !== null && !$storedResume->isPaused() && !$storedResume->isClosed() ? $storedResume : Stage::InProgress) : null;
                break;
            case JobAction::Cancel:
                $to = $from->isOpen() ? Stage::Cancelled : null;
                if ($to !== null && $reason === '') {
                    $errors = $errors->with('reason', 'Say why the job is cancelled.');
                }
                break;
            case JobAction::Archive:
                $to = ($from === Stage::Done || $from === Stage::Cancelled) ? Stage::Archived : null;
                break;
        }
        if ($to === null) {
            $errors = $errors->with('stage', $a->label() . ' is not possible while the job is ' . strtolower($from->label()) . '.');
        }
        return new TransitionOutcome($a, $from, $to, $errors, $resume, $waitingOn, $reason);
    }

    /**
     * The policy-matrix row id of a move from a stage, e.g. "transition:workable->waiting".
     * Null when the move does not exist from that stage.
     */
    public static function matrixId(JobAction $a, Stage $from): ?string
    {
        return match ($a) {
            JobAction::Send => $from === Stage::Draft ? 'transition:draft->briefed' : null,
            JobAction::Recall => $from === Stage::Briefed ? 'transition:briefed->draft' : null,
            JobAction::Start => $from === Stage::Briefed ? 'transition:briefed->in_progress' : null,
            JobAction::Submit => $from === Stage::InProgress ? 'transition:in_progress->in_review' : null,
            JobAction::SendBack => $from === Stage::InReview ? 'transition:in_review->in_progress' : null,
            JobAction::ApproveInternal => $from === Stage::InReview ? 'transition:in_review->approved_internal' : null,
            JobAction::ApproveClient => $from === Stage::ApprovedInternal ? 'transition:approved_internal->approved_client' : null,
            JobAction::ReadyToSchedule => $from === Stage::ApprovedClient ? 'transition:approved_client->ready_to_schedule' : null,
            JobAction::Schedule => $from === Stage::ReadyToSchedule ? 'transition:ready_to_schedule->scheduled' : null,
            JobAction::GoLive => $from === Stage::Scheduled ? 'transition:scheduled->live' : null,
            // Social publishing: no matrix row; driven by publications only, never by Policy::canTransition.
            JobAction::SocialStepBack => null,
            JobAction::MarkDone => $from === Stage::ApprovedClient ? 'transition:approved_client->done' : ($from === Stage::Live ? 'transition:live->done' : null),
            JobAction::Wait => $from->isWorkable() ? 'transition:workable->waiting' : null,
            JobAction::Hold => $from->isWorkable() ? 'transition:workable->on_hold' : null,
            JobAction::Resume => $from === Stage::Waiting ? 'transition:waiting->resume' : ($from === Stage::OnHold ? 'transition:on_hold->resume' : null),
            JobAction::Cancel => $from->isOpen() ? 'transition:open->cancelled' : null,
            JobAction::Archive => $from === Stage::Done ? 'transition:done->archived' : ($from === Stage::Cancelled ? 'transition:cancelled->archived' : null),
        };
    }
}

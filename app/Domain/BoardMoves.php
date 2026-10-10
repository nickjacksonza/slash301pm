<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\BoardTarget;
use App\Domain\Types\JobAccess;
use App\Domain\Types\TransitionRequest;
use App\Domain\Types\User;

/**
 * Board drag and drop on top of the stage machine: a drop from one column to
 * another is the one named move (JobAction) that Transitions says lands there,
 * or nothing. Policy still decides who may make it. Pure.
 */
final class BoardMoves
{
    /** The move a drag from $from to $to means, or null when no named move lands there. */
    public static function actionFor(Stage $from, Stage $to, ?Stage $storedResume): ?JobAction
    {
        if ($from === $to) {
            return null;
        }
        foreach (JobAction::cases() as $a) {
            $o = Transitions::plan($from, $storedResume, self::probe($a), false);
            if ($o->to === $to) {
                return $a;
            }
        }
        return null;
    }

    /** Why a drop cannot work when actionFor() found nothing. */
    public static function refusal(Stage $from, Stage $to, ?Stage $storedResume): string
    {
        if ($from === $to) {
            return 'The job is already ' . strtolower($from->label()) . '.';
        }
        if ($from->isPaused()) {
            $back = Transitions::plan($from, $storedResume, self::probe(JobAction::Resume), false)->to;
            if ($back !== null) {
                return 'A ' . strtolower($from->label()) . ' job resumes to ' . strtolower($back->label()) . '. Drop it there to resume it.';
            }
        }
        return 'A job cannot move from ' . strtolower($from->label()) . ' to ' . strtolower($to->label()) . '.';
    }

    /**
     * Every move this user may make on this job now (Policy plus the stage
     * machine; reasons are asked for later). Send is listed as viaBrief.
     * @return list<BoardTarget>
     */
    public static function targets(User $u, JobAccess $j, ?Stage $storedResume): array
    {
        $out = [];
        foreach (JobAction::cases() as $a) {
            if (!Policy::canTransition($u, $j, $a)->allowed) {
                continue;
            }
            $o = Transitions::plan($j->stage, $storedResume, self::probe($a), $j->anyAssetStarted);
            if (!$o->ok() || $o->to === null) {
                continue;
            }
            $out[] = new BoardTarget($a, $o->to, self::needsReason($a), $a === JobAction::Wait, $a === JobAction::Send);
        }
        return $out;
    }

    /** Moves that refuse an empty reason (Transitions::plan). */
    public static function needsReason(JobAction $a): bool
    {
        return in_array($a, [JobAction::Wait, JobAction::Hold, JobAction::Cancel, JobAction::SendBack], true);
    }

    /** A request with every optional input filled, to learn where a move lands. */
    private static function probe(JobAction $a): TransitionRequest
    {
        return new TransitionRequest($a, WaitingOn::Client, 'probe');
    }
}

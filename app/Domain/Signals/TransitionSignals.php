<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\JobAction;
use App\Domain\Types\TransitionRequest;
use App\Domain\WaitingOn;

/** tr.action, tr.waiting_on, tr.reason from the transition dialogs. Only Phase 2 actions are accepted. */
final class TransitionSignals
{
    public static function fromSignals(array $s): ?TransitionRequest
    {
        $t = SignalInput::obj($s, 'tr');
        $action = JobAction::tryFrom(SignalInput::str($t, 'action'));
        if ($action === null || !in_array($action, JobAction::phase2(), true)) {
            return null;
        }
        $reason = trim(SignalInput::str($t, 'reason'));
        return new TransitionRequest($action, WaitingOn::tryFrom(SignalInput::str($t, 'waiting_on')), $reason);
    }
}

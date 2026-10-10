<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\JobAction;
use App\Domain\Stage;
use App\Domain\ValidationErrors;
use App\Domain\WaitingOn;

/**
 * What a transition does to the job row: the target stage, the resume stage
 * to store (waiting / on hold) and the waiting fields. errors non-empty means
 * the move is refused and nothing else is meaningful.
 */
final class TransitionOutcome
{
    public function __construct(
        public readonly JobAction $action,
        public readonly Stage $from,
        public readonly ?Stage $to,
        public readonly ValidationErrors $errors,
        public readonly ?Stage $resumeStage = null,
        public readonly ?WaitingOn $waitingOn = null,
        public readonly string $reason = '',
    ) {}

    public function ok(): bool
    {
        return $this->to !== null && $this->errors->isEmpty();
    }
}

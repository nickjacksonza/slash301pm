<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\JobAction;
use App\Domain\WaitingOn;

/** A requested stage move, already parsed from signals. */
final class TransitionRequest
{
    public function __construct(
        public readonly JobAction $action,
        public readonly ?WaitingOn $waitingOn = null,
        public readonly string $reason = '',
    ) {}
}

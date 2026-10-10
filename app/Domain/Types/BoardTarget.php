<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\JobAction;
use App\Domain\Stage;

/** A stage a card may move to ("Move to..." menu). viaBrief: only through the brief's Send flow. */
final class BoardTarget
{
    public function __construct(
        public readonly JobAction $action,
        public readonly Stage $to,
        public readonly bool $needsReason,
        public readonly bool $needsWaitingOn,
        public readonly bool $viaBrief,
    ) {}
}

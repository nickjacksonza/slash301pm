<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\ValidationErrors;

/** BriefSend::plan() output: a plan, or the reasons it cannot be sent. */
final class SendResult
{
    public function __construct(
        public readonly ?SendPlan $plan,
        public readonly ValidationErrors $errors,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\PublicationAction;
use App\Domain\PublicationStatus;
use App\Domain\ValidationErrors;

/**
 * What a publication move does: the target status and the values it writes
 * (normalised scheduled_at, live_url, reason). errors non-empty: refused.
 */
final class PublicationOutcome
{
    public function __construct(
        public readonly PublicationAction $action,
        public readonly PublicationStatus $from,
        public readonly ?PublicationStatus $to,
        public readonly ValidationErrors $errors,
        public readonly ?string $scheduledAt,
        public readonly string $liveUrl,
        public readonly string $reason,
    ) {}

    public function ok(): bool
    {
        return $this->to !== null && $this->errors->isEmpty();
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One row of the send checklist. message says what is missing when ok is false. */
final class ReadinessItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $ok,
        public readonly string $message,
    ) {}
}

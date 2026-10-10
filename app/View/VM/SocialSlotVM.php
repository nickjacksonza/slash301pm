<?php
declare(strict_types=1);

namespace App\View\VM;

use App\View\ui\SelectOption;

/** The Social slot picker on the job sheet (after the brief is sent). */
final class SocialSlotVM
{
    /** @param list<SelectOption> $options '' = nobody */
    public function __construct(
        public readonly string $jobId,
        public readonly string $holderId,
        public readonly string $holderName,
        public readonly array $options,
        public readonly bool $canEdit,
    ) {}
}

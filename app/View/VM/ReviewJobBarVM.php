<?php
declare(strict_types=1);

namespace App\View\VM;

use App\View\ui\SelectOption;

/**
 * #review-jobbar: where the job stands in review and the job actions this
 * person may take now (each flag is the ReviewPolicy decision).
 */
final class ReviewJobBarVM
{
    /**
     * @param list<string> $facts lines like "Approved by Isabelle (CD) 12 Oct 10:00"
     * @param list<SelectOption> $reassignTo people the review may go to now
     */
    public function __construct(
        public readonly string $jobId,
        public readonly int $rowVersion,
        public readonly string $statusLabel,
        public readonly int $jobRound,
        public readonly array $facts,
        public readonly bool $canApproveJob,
        public readonly bool $canRequestEcd,
        public readonly bool $canEcdApprove,
        public readonly bool $canReassign,
        public readonly array $reassignTo,
        public readonly bool $canMarkReady,
        public readonly bool $canSend,
        public readonly string $hint,
    ) {}
}

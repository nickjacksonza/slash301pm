<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;

/** The job sheet (#sheet). nonce changes per response so a re-fetch reopens a closed sheet. */
final class JobSheetVM
{
    /**
     * @param list<array{0:string,1:string}> $team role label, person
     * @param list<string> $deliverables
     * @param list<ActivityItemVM> $activity
     * @param list<MoveOptionVM> $moves
     */
    public function __construct(
        public readonly string $id,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly Stage $stage,
        public readonly string $waitingText,
        public readonly string $brandName,
        public readonly string $campaignName,
        public readonly string $dueText,
        public readonly string $dueBadge,
        public readonly ?string $hoursText,
        public readonly ?string $budgetText,
        public readonly array $team,
        public readonly array $deliverables,
        public readonly string $versionLabel,
        public readonly string $versionNote,
        public readonly string $versionUrl,
        public readonly string $briefUrl,
        public readonly bool $unsent,
        public readonly array $activity,
        public readonly array $moves,
        public readonly int $rowVersion,
        public readonly string $nonce,
        public readonly string $moveUrl,
        // Social publishing: the Social slot picker, for who may set it after the send
        public readonly ?SocialSlotVM $social = null,
    ) {}
}

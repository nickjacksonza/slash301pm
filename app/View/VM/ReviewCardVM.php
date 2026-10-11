<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\PartState;

/**
 * One post card of the review page (#post-<key>): the preview, where each
 * part stands, who made it, earlier decisions, and the decision controls when
 * this person may decide. key is ReviewSignals::key(asset id).
 */
final class ReviewCardVM
{
    /**
     * @param list<ReviewHistoryVM> $history newest first
     * @param list<string> $partChoices 'both', 'copy', 'media' that may be chosen now
     */
    public function __construct(
        public readonly string $key,
        public readonly string $assetName,
        public readonly string $lineLabel,
        public readonly int $index,
        public readonly int $total,
        public readonly int $round,
        public readonly PostPreviewVM $preview,
        public readonly PartState $copyState,
        public readonly PartState $mediaState,
        public readonly bool $needsMedia,
        public readonly string $copyMaker,
        public readonly string $mediaMaker,
        public readonly string $handedInLine,
        public readonly string $note,
        public readonly array $history,
        public readonly bool $canDecide,
        public readonly string $cannotDecideReason,
        public readonly array $partChoices,
        public readonly string $defaultPart,
        public readonly int $rowVersion,
        public readonly string $decideUrl,
        public readonly bool $waitingForMe,
        public readonly bool $rejected,
        public readonly bool $handedIn,
    ) {}
}

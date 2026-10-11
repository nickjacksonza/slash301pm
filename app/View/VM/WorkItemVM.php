<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\PartState;

/** One post of the hand-in page (#work-<key>). key is ReviewSignals::key(asset id). */
final class WorkItemVM
{
    /** @param list<ReviewHistoryVM> $history newest first */
    public function __construct(
        public readonly string $key,
        public readonly string $assetName,
        public readonly string $lineLabel,
        public readonly int $round,
        public readonly PartState $copyState,
        public readonly PartState $mediaState,
        public readonly bool $needsMedia,
        public readonly bool $editCopy,
        public readonly bool $editMedia,
        public readonly string $copyText,
        public readonly string $mediaUrl,
        public readonly string $hashtags,
        public readonly string $linkUrl,
        public readonly string $note,
        public readonly int $rowVersion,
        public readonly string $saveUrl,
        public readonly string $requestUrl,
        public readonly array $history,
        public readonly string $readOnlyReason,
        public readonly PostPreviewVM $preview,
        public readonly bool $reviewRequested,
    ) {}
}

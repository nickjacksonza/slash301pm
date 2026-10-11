<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\MediaKind;
use App\Domain\PartState;
use App\Domain\ReviewPart;
use App\Domain\ValidationErrors;

/**
 * What a hand-in writes (ReviewRules::planSubmission): the round it lands in
 * (newRound when it opens the next one), every field of that round, the part
 * states, which parts changed, and whether review is requested.
 * Go: type SubmissionPlan struct.
 */
final class SubmissionPlan
{
    /** @param list<ReviewPart> $changed */
    public function __construct(
        public readonly ValidationErrors $errors,
        public readonly int $round,
        public readonly bool $newRound,
        public readonly string $copyText,
        public readonly string $mediaUrl,
        public readonly MediaKind $mediaKind,
        public readonly string $hashtags,
        public readonly string $linkUrl,
        public readonly string $note,
        public readonly PartState $copyState,
        public readonly PartState $mediaState,
        public readonly array $changed,
        public readonly bool $requestReview,
    ) {}

    public function ok(): bool
    {
        return $this->errors->isEmpty();
    }

    public function changes(ReviewPart $p): bool
    {
        return in_array($p, $this->changed, true);
    }
}

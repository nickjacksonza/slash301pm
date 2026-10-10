<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** What a maker typed for one asset (signals sub_<asset id>), trimmed. Untrusted until ReviewRules validates it. */
final class SubmissionInput
{
    public function __construct(
        public readonly string $copyText,
        public readonly string $mediaUrl,
        public readonly string $hashtags,
        public readonly string $linkUrl,
        public readonly string $note,
        public readonly int $rowVersion,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\ReviewDecision;

/**
 * A reviewer's decision on one post card, under rv_<asset id>:
 * {rv, decision, part (both | copy | media), feedback}. Untrusted input.
 */
final class ReviewSignals
{
    public function __construct(
        public readonly int $rowVersion,
        public readonly ?ReviewDecision $decision,
        public readonly string $part,
        public readonly string $feedback,
    ) {}

    public static function root(string $assetId): string
    {
        return 'rv_' . $assetId;
    }

    public static function fromSignals(array $s, string $assetId): self
    {
        $o = SignalInput::obj($s, self::root($assetId));
        $part = SignalInput::str($o, 'part');
        return new self(
            SignalInput::int($o, 'rv', -1), ReviewDecision::tryFrom(SignalInput::str($o, 'decision')),
            in_array($part, ['both', 'copy', 'media'], true) ? $part : 'both', trim(SignalInput::str($o, 'feedback')),
        );
    }
}

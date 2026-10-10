<?php
declare(strict_types=1);

namespace App\Domain\Signals;

/** The job action bar of the review page, under jr: {rv, to (reassign target user id)}. Untrusted input. */
final class JobReviewSignals
{
    public function __construct(
        public readonly int $rowVersion,
        public readonly string $to,
    ) {}

    public static function fromSignals(array $s): self
    {
        $o = SignalInput::obj($s, 'jr');
        $to = trim(SignalInput::str($o, 'to'));
        return new self(SignalInput::int($o, 'rv', -1), preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $to) === 1 ? $to : '');
    }
}

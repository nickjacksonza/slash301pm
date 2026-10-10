<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\BumpLevel;

/** send.bump and send.note from the "Send update" dialog. */
final class SendSignals
{
    public function __construct(
        public readonly ?BumpLevel $bump,
        public readonly string $note,
    ) {}

    public static function fromSignals(array $s): self
    {
        $x = SignalInput::obj($s, 'send');
        return new self(BumpLevel::tryFrom(SignalInput::str($x, 'bump')), trim(SignalInput::str($x, 'note')));
    }
}

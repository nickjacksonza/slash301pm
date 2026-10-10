<?php
declare(strict_types=1);

namespace App\Domain\Signals;

/**
 * The override dialogs of the assets page: ova.{asset_id, from, to, reason}
 * for an asset, ovp.{pub_id, rv, to, reason} for a Social post. Untrusted
 * input; ids, statuses and the reason are checked by the handler and
 * AssetOverride.
 */
final class OverrideSignals
{
    public function __construct(
        public readonly string $targetId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $reason,
        public readonly int $rowVersion,
    ) {}

    public static function asset(array $s): self
    {
        $o = SignalInput::obj($s, 'ova');
        return new self(trim(SignalInput::str($o, 'asset_id')), SignalInput::str($o, 'from'), trim(SignalInput::str($o, 'to')), trim(SignalInput::str($o, 'reason')), -1);
    }

    public static function post(array $s): self
    {
        $o = SignalInput::obj($s, 'ovp');
        return new self(trim(SignalInput::str($o, 'pub_id')), '', trim(SignalInput::str($o, 'to')), trim(SignalInput::str($o, 'reason')), SignalInput::int($o, 'rv', -1));
    }
}

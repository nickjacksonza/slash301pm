<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\Types\SubmissionInput;

/**
 * A maker's hand-in form for one asset, under sub_<asset id>:
 * {copy_text, media_url, hashtags, link_url, note, rv}. Untrusted input.
 */
final class SubmissionSignals
{
    public static function root(string $assetId): string
    {
        return 'sub_' . $assetId;
    }

    public static function fromSignals(array $s, string $assetId): SubmissionInput
    {
        $o = SignalInput::obj($s, self::root($assetId));
        return new SubmissionInput(
            rtrim(SignalInput::str($o, 'copy_text')), trim(SignalInput::str($o, 'media_url')), trim(SignalInput::str($o, 'hashtags')),
            trim(SignalInput::str($o, 'link_url')), trim(SignalInput::str($o, 'note')), SignalInput::int($o, 'rv', -1),
        );
    }
}

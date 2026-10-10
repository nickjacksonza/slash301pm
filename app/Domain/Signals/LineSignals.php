<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\AssetTemplates;
use App\Domain\Types\BriefLineInput;
use App\Domain\ValidationErrors;

/**
 * One deliverable line from dl.<key>.* where key is "ln_" plus the line id
 * (signal keys must not start with a digit). Untrusted input.
 */
final class LineSignals
{
    public function __construct(
        public readonly ?BriefLineInput $input,
        public readonly ValidationErrors $errors,
    ) {}

    public static function signalKey(string $lineId): string
    {
        return 'ln_' . $lineId;
    }

    public static function fromSignals(array $s, string $lineId): self
    {
        $dl = SignalInput::obj($s, 'dl');
        $l = SignalInput::obj($dl, self::signalKey($lineId));
        $errors = new ValidationErrors();
        if ($l === []) {
            return new self(null, $errors->with('line', 'Nothing to save for this deliverable.'));
        }
        $tpl = trim(SignalInput::str($l, 'template_id'));
        $templateId = null;
        if ($tpl !== '') {
            if (AssetTemplates::find($tpl) === null) {
                $errors = $errors->with('template_id', 'Unknown template.');
            } else {
                $templateId = $tpl;
            }
        }
        $label = trim(preg_replace('/\s+/u', ' ', SignalInput::str($l, 'label')) ?? '');
        if ($label === '' || mb_strlen($label) > 120) {
            $errors = $errors->with('label', 'Give the deliverable a name (up to 120 characters).');
        }
        $qty = SignalInput::int($l, 'qty', -1);
        if ($qty < 1 || $qty > 500) {
            $errors = $errors->with('qty', 'Quantity must be a whole number from 1 to 500. Remove the line instead of setting 0.');
        }
        $channel = trim(SignalInput::str($l, 'channel'));
        $size = trim(SignalInput::str($l, 'size_format'));
        $specs = rtrim(SignalInput::str($l, 'specs'));
        if (mb_strlen($channel) > 120 || mb_strlen($size) > 120 || SignalInput::hasControlChars($channel . $size, false)) {
            $errors = $errors->with('channel', 'Channel and size are limited to 120 characters.');
        }
        if (mb_strlen($specs) > 4000 || SignalInput::hasControlChars($specs, true)) {
            $errors = $errors->with('specs', 'Specs are limited to 4000 characters.');
        }
        $due = SignalInput::date(SignalInput::str($l, 'due_date'));
        if ($due === false) {
            $errors = $errors->with('due_date', 'The due date must be a real date.');
            $due = null;
        }
        if (!$errors->isEmpty()) {
            return new self(null, $errors);
        }
        return new self(new BriefLineInput($templateId, $label, $qty, $channel, $size, $specs, SignalInput::bool($l, 'copy_required'), $due), $errors);
    }
}

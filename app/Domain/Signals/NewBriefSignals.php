<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\ValidationErrors;

/** nb.campaign_id and nb.title (title optional: defaults to "New brief"). */
final class NewBriefSignals
{
    public function __construct(
        public readonly string $campaignId,
        public readonly string $title,
    ) {}

    public static function fromSignals(array $s): self
    {
        $n = SignalInput::obj($s, 'nb');
        $title = trim(preg_replace('/\s+/u', ' ', SignalInput::str($n, 'title')) ?? '');
        return new self(trim(SignalInput::str($n, 'campaign_id')), $title === '' ? 'New brief' : $title);
    }

    public function validate(): ValidationErrors
    {
        $e = new ValidationErrors();
        if ($this->campaignId === '') {
            $e = $e->with('campaign_id', 'Choose a campaign.');
        }
        if (mb_strlen($this->title) > 200) {
            $e = $e->with('title', 'Keep the title under 200 characters.');
        }
        return $e;
    }
}

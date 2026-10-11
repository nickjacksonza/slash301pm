<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\Links;
use App\Domain\ValidationErrors;

/** eb.brand_id and eb.logo_url from the Campaigns page's brand dialog. Untrusted input. */
final class BrandLogoSignals
{
    public function __construct(
        public readonly string $brandId,
        public readonly string $logoUrl,
    ) {}

    public static function fromSignals(array $s): self
    {
        $b = SignalInput::obj($s, 'eb');
        return new self(trim(SignalInput::str($b, 'brand_id')), trim(SignalInput::str($b, 'logo_url')));
    }

    /** '' clears the logo; anything else must be an https:// link (Links::logoUrlProblem). */
    public function validate(): ValidationErrors
    {
        $e = new ValidationErrors();
        if ($this->brandId === '') {
            $e = $e->with('brand_id', 'Choose a brand.');
        }
        $why = Links::logoUrlProblem($this->logoUrl);
        if ($why !== '') {
            $e = $e->with('logo_url', $why);
        }
        return $e;
    }
}

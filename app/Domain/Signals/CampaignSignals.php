<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\ValidationErrors;

/** nc.brand_id, nc.name, nc.description from the new-campaign dialog. */
final class CampaignSignals
{
    public function __construct(
        public readonly string $brandId,
        public readonly string $name,
        public readonly string $description,
    ) {}

    public static function fromSignals(array $s): self
    {
        $c = SignalInput::obj($s, 'nc');
        return new self(
            trim(SignalInput::str($c, 'brand_id')),
            trim(preg_replace('/\s+/u', ' ', SignalInput::str($c, 'name')) ?? ''),
            trim(SignalInput::str($c, 'description')),
        );
    }

    public function validate(): ValidationErrors
    {
        $e = new ValidationErrors();
        if ($this->brandId === '') {
            $e = $e->with('brand_id', 'Choose a brand.');
        }
        $n = mb_strlen($this->name);
        if ($n < 2 || $n > 100) {
            $e = $e->with('name', 'The campaign name must be 2 to 100 characters.');
        }
        if (mb_strlen($this->description) > 2000 || SignalInput::hasControlChars($this->description, true)) {
            $e = $e->with('description', 'The description is limited to 2000 characters.');
        }
        return $e;
    }
}

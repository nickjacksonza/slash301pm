<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** Validated fields of one deliverable line from the editor. */
final class BriefLineInput
{
    public function __construct(
        public readonly ?string $templateId,
        public readonly string $label,
        public readonly int $qty,
        public readonly string $channel,
        public readonly string $sizeFormat,
        public readonly string $specs,
        public readonly bool $copyRequired,
        public readonly ?string $dueDate,
    ) {}

    /** A new line from a template (or a blank custom line when null). */
    public static function fromTemplate(?AssetTemplate $t): self
    {
        if ($t === null) {
            return new self(null, 'Custom deliverable', 1, '', '', '', false, null);
        }
        return new self($t->id, $t->name, 1, '', '', '', $t->type === 'copy', null);
    }

    public function toLine(string $id, string $briefId, string $jobId, int $sortOrder): BriefLine
    {
        return new BriefLine($id, $briefId, $jobId, $this->templateId, $this->label, $this->qty, $this->channel, $this->sizeFormat, $this->specs, $this->copyRequired, $this->dueDate, $sortOrder);
    }
}

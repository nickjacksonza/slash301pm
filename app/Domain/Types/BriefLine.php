<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** A deliverable line (brief_assets row): "3x Social Static 1080x1350, IG". */
final class BriefLine
{
    public function __construct(
        public readonly string $id,
        public readonly string $briefId,
        public readonly string $jobId,
        public readonly ?string $templateId,
        public readonly string $label,
        public readonly int $qty,
        public readonly string $channel,
        public readonly string $sizeFormat,
        public readonly string $specs,
        public readonly bool $copyRequired,
        public readonly ?string $dueDate,
        public readonly int $sortOrder,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'], (string) $r['brief_id'], (string) $r['job_id'],
            $r['template_id'] !== null && $r['template_id'] !== '' ? (string) $r['template_id'] : null,
            (string) $r['label'], (int) $r['qty'], (string) ($r['channel'] ?? ''), (string) ($r['size_format'] ?? ''),
            (string) ($r['specs'] ?? ''), (int) $r['copy_required'] === 1,
            $r['due_date'] !== null && $r['due_date'] !== '' ? (string) $r['due_date'] : null, (int) $r['sort_order'],
        );
    }

    /** Snapshot form (no brief or job id: those never change inside a brief). */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'template_id' => $this->templateId, 'label' => $this->label, 'qty' => $this->qty,
            'channel' => $this->channel, 'size_format' => $this->sizeFormat, 'specs' => $this->specs,
            'copy_required' => $this->copyRequired, 'due_date' => $this->dueDate, 'sort_order' => $this->sortOrder,
        ];
    }

    public static function fromArray(array $a, string $briefId, string $jobId): self
    {
        return new self(
            (string) ($a['id'] ?? ''), $briefId, $jobId,
            isset($a['template_id']) && is_string($a['template_id']) && $a['template_id'] !== '' ? $a['template_id'] : null,
            (string) ($a['label'] ?? ''), (int) ($a['qty'] ?? 1), (string) ($a['channel'] ?? ''), (string) ($a['size_format'] ?? ''),
            (string) ($a['specs'] ?? ''), (bool) ($a['copy_required'] ?? false),
            isset($a['due_date']) && is_string($a['due_date']) && $a['due_date'] !== '' ? $a['due_date'] : null, (int) ($a['sort_order'] ?? 0),
        );
    }

    /** "3x Social Post (Static) · 1080x1350 · Instagram" */
    public function summary(): string
    {
        $parts = [$this->qty . 'x ' . $this->label];
        if ($this->sizeFormat !== '') {
            $parts[] = $this->sizeFormat;
        }
        if ($this->channel !== '') {
            $parts[] = $this->channel;
        }
        return implode(' · ', $parts);
    }
}

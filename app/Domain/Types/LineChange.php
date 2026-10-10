<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** A deliverable line added, removed or changed. changes is empty for added and removed. */
final class LineChange
{
    /** @param list<FieldChange> $changes */
    public function __construct(
        public readonly string $kind,      // added | removed | changed
        public readonly string $lineId,
        public readonly string $summary,   // BriefLine::summary() of the new line (old one when removed)
        public readonly array $changes,
    ) {}

    public function toArray(): array
    {
        $c = [];
        foreach ($this->changes as $ch) {
            $c[] = $ch->toArray();
        }
        return ['kind' => $this->kind, 'line_id' => $this->lineId, 'summary' => $this->summary, 'changes' => $c];
    }

    public static function fromArray(array $a): self
    {
        $c = [];
        foreach (is_array($a['changes'] ?? null) ? $a['changes'] : [] as $ch) {
            if (is_array($ch)) {
                $c[] = FieldChange::fromArray($ch);
            }
        }
        return new self((string) ($a['kind'] ?? ''), (string) ($a['line_id'] ?? ''), (string) ($a['summary'] ?? ''), $c);
    }
}

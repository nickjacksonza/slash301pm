<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One changed field, with display strings for before and after ('' = empty). */
final class FieldChange
{
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly string $before,
        public readonly string $after,
    ) {}

    public function toArray(): array
    {
        return ['field' => $this->field, 'label' => $this->label, 'before' => $this->before, 'after' => $this->after];
    }

    public static function fromArray(array $a): self
    {
        return new self((string) ($a['field'] ?? ''), (string) ($a['label'] ?? ''), (string) ($a['before'] ?? ''), (string) ($a['after'] ?? ''));
    }
}

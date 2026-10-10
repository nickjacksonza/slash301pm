<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\ChecklistEntry;

/**
 * The pre-schedule checklist of one publication (asset_publications.checklist_json):
 * copy, image, link, hashtags and test result, each ticked or not with a note.
 * All five are required before Ready to schedule. Immutable value; pure.
 * Go: type PublicationChecklist struct{ Items map[ChecklistItem]ChecklistEntry }.
 */
final class PublicationChecklist
{
    public const MAX_NOTE = 500;

    /** @param array<string,ChecklistEntry> $items keyed by ChecklistItem value, every item present */
    private function __construct(
        public readonly array $items,
    ) {}

    public static function empty(): self
    {
        $items = [];
        foreach (ChecklistItem::cases() as $c) {
            $items[$c->value] = new ChecklistEntry(false, '');
        }
        return new self($items);
    }

    /** From the stored JSON; anything missing or malformed counts as unticked. */
    public static function fromJson(?string $json): self
    {
        $c = self::empty();
        if ($json === null || $json === '') {
            return $c;
        }
        $data = json_decode($json, true, 8);
        if (!is_array($data)) {
            return $c;
        }
        $items = $c->items;
        foreach (ChecklistItem::cases() as $item) {
            $v = $data[$item->value] ?? null;
            if (is_array($v)) {
                $note = isset($v['note']) && is_string($v['note']) ? $v['note'] : '';
                $items[$item->value] = new ChecklistEntry(($v['ok'] ?? false) === true, $note);
            }
        }
        return new self($items);
    }

    public function toJson(): string
    {
        $out = [];
        foreach (ChecklistItem::cases() as $item) {
            $e = $this->entry($item);
            $out[$item->value] = ['ok' => $e->ok, 'note' => $e->note];
        }
        return json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function entry(ChecklistItem $item): ChecklistEntry
    {
        return $this->items[$item->value] ?? new ChecklistEntry(false, '');
    }

    public function with(ChecklistItem $item, bool $ok, string $note): self
    {
        $items = $this->items;
        $items[$item->value] = new ChecklistEntry($ok, $note);
        return new self($items);
    }

    /** Every item ticked. */
    public function allTicked(): self
    {
        $c = $this;
        foreach (ChecklistItem::cases() as $item) {
            $c = $c->with($item, true, $this->entry($item)->note);
        }
        return $c;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /** @return list<ChecklistItem> unticked items, in order */
    public function missing(): array
    {
        $out = [];
        foreach (ChecklistItem::cases() as $item) {
            if (!$this->entry($item)->ok) {
                $out[] = $item;
            }
        }
        return $out;
    }

    public function tickedCount(): int
    {
        return count(ChecklistItem::cases()) - count($this->missing());
    }

    /** 'Copy, Hashtags' */
    public function missingText(): string
    {
        $labels = [];
        foreach ($this->missing() as $m) {
            $labels[] = $m->label();
        }
        return implode(', ', $labels);
    }

    /** A note is plain text on one line, at most MAX_NOTE characters. '' when fine. */
    public static function noteProblem(string $note): string
    {
        if (mb_strlen($note) > self::MAX_NOTE) {
            return 'Keep the note under ' . self::MAX_NOTE . ' characters.';
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $note) === 1) {
            return 'Keep the note on one line.';
        }
        return '';
    }
}

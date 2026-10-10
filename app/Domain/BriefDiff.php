<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\BriefLine;
use App\Domain\Types\BriefSnapshot;
use App\Domain\Types\BumpSuggestion;
use App\Domain\Types\FieldChange;
use App\Domain\Types\LineChange;

/**
 * Field-level difference between two snapshots: brief fields, deliverable
 * lines (matched by id) and team slots. Stored as brief_versions.diff_json.
 * suggestBump() proposes the version bump for "Send update" (ADR 0003).
 */
final class BriefDiff
{
    public const FIELD_LABELS = [
        'title' => 'Title', 'campaign_id' => 'Campaign', 'brief_date' => 'Brief date', 'due_date' => 'Due date',
        'first_go_live' => 'First go-live', 'last_go_live' => 'Last go-live', 'creative_direction' => 'Creative direction',
        'mandatories' => 'Mandatories', 'references' => 'References', 'brief_pdf_url' => 'Brief PDF', 'server_link' => 'Server folder',
        'budget' => 'Budget', 'hours_estimate' => 'Hours estimate',
    ];

    public const LINE_LABELS = [
        'template_id' => 'Template', 'label' => 'Label', 'qty' => 'Quantity', 'channel' => 'Channel', 'size_format' => 'Size or format',
        'specs' => 'Specs', 'copy_required' => 'Copy required', 'due_date' => 'Due date',
    ];

    /** Fields whose change is a deliverables / dates / money change (minor). */
    private const MINOR_FIELDS = ['brief_date', 'due_date', 'first_go_live', 'last_go_live', 'budget', 'hours_estimate'];

    /**
     * @param list<FieldChange> $fields
     * @param list<LineChange> $lines
     * @param list<FieldChange> $team field = role value
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $lines,
        public readonly array $team,
    ) {}

    public static function between(BriefSnapshot $old, BriefSnapshot $new): self
    {
        $fields = [];
        $pairs = [
            'title' => [$old->title, $new->title],
            'campaign_id' => [$old->campaignId === $new->campaignId ? '' : $old->campaignName, $old->campaignId === $new->campaignId ? '' : $new->campaignName],
            'brief_date' => [$old->briefDate ?? '', $new->briefDate ?? ''],
            'due_date' => [$old->dueDate ?? '', $new->dueDate ?? ''],
            'first_go_live' => [$old->firstGoLive ?? '', $new->firstGoLive ?? ''],
            'last_go_live' => [$old->lastGoLive ?? '', $new->lastGoLive ?? ''],
            'creative_direction' => [$old->creativeDirection, $new->creativeDirection],
            'mandatories' => [implode("\n", $old->mandatories), implode("\n", $new->mandatories)],
            'references' => [self::refs($old), self::refs($new)],
            'brief_pdf_url' => [$old->briefPdfUrl, $new->briefPdfUrl],
            'server_link' => [$old->serverLink, $new->serverLink],
            'budget' => [self::num($old->budget), self::num($new->budget)],
            'hours_estimate' => [self::num($old->hoursEstimate), self::num($new->hoursEstimate)],
        ];
        foreach ($pairs as $field => $pair) {
            if ($field === 'campaign_id') {
                if ($old->campaignId !== $new->campaignId) {
                    $fields[] = new FieldChange($field, self::FIELD_LABELS[$field], $pair[0], $pair[1]);
                }
                continue;
            }
            if ($pair[0] !== $pair[1]) {
                $fields[] = new FieldChange($field, self::FIELD_LABELS[$field], $pair[0], $pair[1]);
            }
        }

        $lines = [];
        $oldById = [];
        foreach ($old->lines as $l) {
            $oldById[$l->id] = $l;
        }
        $newIds = [];
        foreach ($new->lines as $l) {
            $newIds[$l->id] = true;
            if (!isset($oldById[$l->id])) {
                $lines[] = new LineChange('added', $l->id, $l->summary(), []);
                continue;
            }
            $changes = self::lineChanges($oldById[$l->id], $l);
            if ($changes !== []) {
                $lines[] = new LineChange('changed', $l->id, $l->summary(), $changes);
            }
        }
        foreach ($old->lines as $l) {
            if (!isset($newIds[$l->id])) {
                $lines[] = new LineChange('removed', $l->id, $l->summary(), []);
            }
        }

        $team = [];
        $roles = array_values(array_unique(array_merge(array_keys($old->team), array_keys($new->team))));
        foreach ($roles as $role) {
            $before = $old->team[$role] ?? null;
            $after = $new->team[$role] ?? null;
            $b = $before === null ? '' : $before['user_id'];
            $a = $after === null ? '' : $after['user_id'];
            if ($a !== $b) {
                $team[] = new FieldChange((string) $role, (string) $role, $before === null ? '' : $before['name'], $after === null ? '' : $after['name']);
            }
        }
        return new self($fields, $lines, $team);
    }

    /** No change at all (brief fields, lines and team). */
    public function isEmpty(): bool
    {
        return $this->fields === [] && $this->lines === [] && $this->team === [];
    }

    /** No change to the brief content itself (team changes do not make a brief "unsent"). */
    public function contentIsEmpty(): bool
    {
        return $this->fields === [] && $this->lines === [];
    }

    public function field(string $name): ?FieldChange
    {
        foreach ($this->fields as $f) {
            if ($f->field === $name) {
                return $f;
            }
        }
        return null;
    }

    /**
     * major: campaign changed, half or more of the old lines removed, or the
     *        creative direction mostly rewritten (under half the words kept);
     * minor: any deliverable, date, budget, hours or team change;
     * patch: anything else (wording, mandatories, references, links).
     */
    public static function suggestBump(self $d, int $oldLineCount): BumpSuggestion
    {
        if ($d->field('campaign_id') !== null) {
            return new BumpSuggestion(BumpLevel::Major, 'The campaign changed.');
        }
        $removed = 0;
        foreach ($d->lines as $l) {
            if ($l->kind === 'removed') {
                $removed++;
            }
        }
        if ($oldLineCount > 0 && $removed > 0 && $removed * 2 >= $oldLineCount) {
            return new BumpSuggestion(BumpLevel::Major, 'Half or more of the deliverables were removed.');
        }
        $cd = $d->field('creative_direction');
        if ($cd !== null && $cd->before !== '' && self::wordOverlap($cd->before, $cd->after) < 0.5) {
            return new BumpSuggestion(BumpLevel::Major, 'The creative direction was largely rewritten.');
        }
        if ($d->lines !== []) {
            return new BumpSuggestion(BumpLevel::Minor, 'Deliverables changed.');
        }
        foreach ($d->fields as $f) {
            if (in_array($f->field, self::MINOR_FIELDS, true)) {
                return new BumpSuggestion(BumpLevel::Minor, $f->label . ' changed.');
            }
        }
        if ($d->team !== []) {
            return new BumpSuggestion(BumpLevel::Minor, 'The team changed.');
        }
        return new BumpSuggestion(BumpLevel::Patch, $d->fields === [] ? 'No content changes.' : 'Wording, references or links changed.');
    }

    /** Share of the old words (lower case, unique) still present in the new text, 0..1. */
    public static function wordOverlap(string $old, string $new): float
    {
        $a = self::words($old);
        if ($a === []) {
            return 1.0;
        }
        $b = array_fill_keys(self::words($new), true);
        $kept = 0;
        foreach ($a as $w) {
            if (isset($b[$w])) {
                $kept++;
            }
        }
        return $kept / count($a);
    }

    public function toArray(): array
    {
        $f = [];
        foreach ($this->fields as $x) {
            $f[] = $x->toArray();
        }
        $l = [];
        foreach ($this->lines as $x) {
            $l[] = $x->toArray();
        }
        $t = [];
        foreach ($this->team as $x) {
            $t[] = $x->toArray();
        }
        return ['fields' => $f, 'lines' => $l, 'team' => $t];
    }

    public static function fromArray(array $a): self
    {
        $f = [];
        foreach (is_array($a['fields'] ?? null) ? $a['fields'] : [] as $x) {
            if (is_array($x)) {
                $f[] = FieldChange::fromArray($x);
            }
        }
        $l = [];
        foreach (is_array($a['lines'] ?? null) ? $a['lines'] : [] as $x) {
            if (is_array($x)) {
                $l[] = LineChange::fromArray($x);
            }
        }
        $t = [];
        foreach (is_array($a['team'] ?? null) ? $a['team'] : [] as $x) {
            if (is_array($x)) {
                $t[] = FieldChange::fromArray($x);
            }
        }
        return new self($f, $l, $t);
    }

    /** @return list<FieldChange> */
    private static function lineChanges(BriefLine $o, BriefLine $n): array
    {
        $pairs = [
            'template_id' => [$o->templateId ?? '', $n->templateId ?? ''],
            'label' => [$o->label, $n->label],
            'qty' => [(string) $o->qty, (string) $n->qty],
            'channel' => [$o->channel, $n->channel],
            'size_format' => [$o->sizeFormat, $n->sizeFormat],
            'specs' => [$o->specs, $n->specs],
            'copy_required' => [$o->copyRequired ? 'Yes' : 'No', $n->copyRequired ? 'Yes' : 'No'],
            'due_date' => [$o->dueDate ?? '', $n->dueDate ?? ''],
        ];
        $out = [];
        foreach ($pairs as $field => $p) {
            if ($p[0] !== $p[1]) {
                $out[] = new FieldChange($field, self::LINE_LABELS[$field], $p[0], $p[1]);
            }
        }
        return $out;
    }

    private static function refs(BriefSnapshot $s): string
    {
        $lines = [];
        foreach ($s->references as $r) {
            $lines[] = $r->toLine();
        }
        return implode("\n", $lines);
    }

    private static function num(?float $v): string
    {
        if ($v === null) {
            return '';
        }
        return floor($v) === $v ? number_format($v, 0, '.', '') : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** @return list<string> */
    private static function words(string $s): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if ($p !== '' && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }
}

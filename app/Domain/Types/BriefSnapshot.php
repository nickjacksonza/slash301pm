<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * Everything a sent version freezes: brief fields, deliverable lines and the
 * team. Stored as brief_versions.snapshot_json (toArray / fromArray).
 * campaignName and brandName are kept so old versions render without joins.
 */
final class BriefSnapshot
{
    /**
     * @param list<string> $mandatories
     * @param list<BriefReference> $references
     * @param list<BriefLine> $lines
     * @param array<string,array{user_id:string,name:string}> $team role => holder
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $campaignId,
        public readonly string $campaignName,
        public readonly string $brandName,
        public readonly ?string $briefDate,
        public readonly ?string $dueDate,
        public readonly ?string $firstGoLive,
        public readonly ?string $lastGoLive,
        public readonly string $creativeDirection,
        public readonly array $mandatories,
        public readonly array $references,
        public readonly string $briefPdfUrl,
        public readonly string $serverLink,
        public readonly ?float $budget,
        public readonly ?float $hoursEstimate,
        public readonly array $lines,
        public readonly array $team,
    ) {}

    /** @param list<BriefLine> $lines */
    public static function of(Brief $b, array $lines, Team $team, string $campaignName, string $brandName): self
    {
        $sorted = $lines;
        usort($sorted, static fn (BriefLine $x, BriefLine $y): int => [$x->sortOrder, $x->id] <=> [$y->sortOrder, $y->id]);
        return new self(
            $b->title, $b->campaignId, $campaignName, $brandName, $b->briefDate, $b->dueDate, $b->firstGoLive, $b->lastGoLive,
            $b->creativeDirection, $b->mandatories, $b->references, $b->briefPdfUrl, $b->serverLink, $b->budget, $b->hoursEstimate,
            array_values($sorted), $team->toArray(),
        );
    }

    public function toArray(): array
    {
        $refs = [];
        foreach ($this->references as $r) {
            $refs[] = $r->toArray();
        }
        $lines = [];
        foreach ($this->lines as $l) {
            $lines[] = $l->toArray();
        }
        return [
            'title' => $this->title, 'campaign_id' => $this->campaignId, 'campaign_name' => $this->campaignName, 'brand_name' => $this->brandName,
            'brief_date' => $this->briefDate, 'due_date' => $this->dueDate, 'first_go_live' => $this->firstGoLive, 'last_go_live' => $this->lastGoLive,
            'creative_direction' => $this->creativeDirection, 'mandatories' => $this->mandatories, 'references' => $refs,
            'brief_pdf_url' => $this->briefPdfUrl, 'server_link' => $this->serverLink, 'budget' => $this->budget, 'hours_estimate' => $this->hoursEstimate,
            'lines' => $lines, 'team' => $this->team === [] ? new \stdClass() : $this->team,
        ];
    }

    /** From decoded snapshot_json; tolerant of missing keys. */
    public static function fromArray(array $a, string $briefId, string $jobId): self
    {
        $mand = [];
        foreach (is_array($a['mandatories'] ?? null) ? $a['mandatories'] : [] as $m) {
            if (is_string($m)) {
                $mand[] = $m;
            }
        }
        $lines = [];
        foreach (is_array($a['lines'] ?? null) ? $a['lines'] : [] as $l) {
            if (is_array($l)) {
                $lines[] = BriefLine::fromArray($l, $briefId, $jobId);
            }
        }
        $team = [];
        foreach (is_array($a['team'] ?? null) ? $a['team'] : [] as $role => $h) {
            if (is_array($h) && isset($h['user_id'], $h['name'])) {
                $team[(string) $role] = ['user_id' => (string) $h['user_id'], 'name' => (string) $h['name']];
            }
        }
        return new self(
            (string) ($a['title'] ?? ''), self::opt($a['campaign_id'] ?? null), (string) ($a['campaign_name'] ?? ''), (string) ($a['brand_name'] ?? ''),
            self::opt($a['brief_date'] ?? null), self::opt($a['due_date'] ?? null), self::opt($a['first_go_live'] ?? null), self::opt($a['last_go_live'] ?? null),
            (string) ($a['creative_direction'] ?? ''), $mand, BriefReference::listFromArray($a['references'] ?? []),
            (string) ($a['brief_pdf_url'] ?? ''), (string) ($a['server_link'] ?? ''),
            isset($a['budget']) && is_numeric($a['budget']) ? (float) $a['budget'] : null,
            isset($a['hours_estimate']) && is_numeric($a['hours_estimate']) ? (float) $a['hours_estimate'] : null,
            $lines, $team,
        );
    }

    private static function opt(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }
}

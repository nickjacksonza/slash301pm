<?php
declare(strict_types=1);

namespace App\Domain\Types;

/**
 * A partial update of a brief's own fields (autosave). Only names in $set are
 * written; a set field with a null value clears it. Built and validated by
 * App\Domain\Signals\BriefSignals. Go: struct with a field mask.
 */
final class BriefPatch
{
    /**
     * @param list<string> $set column names to write
     * @param list<string>|null $mandatories
     * @param list<BriefReference>|null $references
     */
    public function __construct(
        public readonly array $set,
        public readonly ?string $title = null,
        public readonly ?string $campaignId = null,
        public readonly ?string $briefDate = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $firstGoLive = null,
        public readonly ?string $lastGoLive = null,
        public readonly ?string $creativeDirection = null,
        public readonly ?array $mandatories = null,
        public readonly ?array $references = null,
        public readonly ?string $briefPdfUrl = null,
        public readonly ?string $serverLink = null,
        public readonly ?float $budget = null,
        public readonly ?float $hoursEstimate = null,
    ) {}

    public function has(string $field): bool
    {
        return in_array($field, $this->set, true);
    }

    public function isEmpty(): bool
    {
        return $this->set === [];
    }

    /** The brief as it would be after this patch (for diffs and rules before writing). */
    public function applyTo(Brief $b): Brief
    {
        return new Brief(
            $b->id, $b->jobId,
            $this->has('title') ? (string) $this->title : $b->title,
            $this->has('campaign_id') ? $this->campaignId : $b->campaignId,
            $this->has('brief_date') ? $this->briefDate : $b->briefDate,
            $this->has('due_date') ? $this->dueDate : $b->dueDate,
            $this->has('first_go_live') ? $this->firstGoLive : $b->firstGoLive,
            $this->has('last_go_live') ? $this->lastGoLive : $b->lastGoLive,
            $this->has('creative_direction') ? (string) $this->creativeDirection : $b->creativeDirection,
            $this->has('mandatories') ? ($this->mandatories ?? []) : $b->mandatories,
            $this->has('references') ? ($this->references ?? []) : $b->references,
            $this->has('brief_pdf_url') ? (string) $this->briefPdfUrl : $b->briefPdfUrl,
            $this->has('server_link') ? (string) $this->serverLink : $b->serverLink,
            $this->has('budget') ? $this->budget : $b->budget,
            $this->has('hours_estimate') ? $this->hoursEstimate : $b->hoursEstimate,
            $b->version, $b->hasUnsentChanges, $b->sentAt, $b->sentBy, $b->createdBy, $b->createdAt, $b->updatedBy, $b->updatedAt, $b->rowVersion,
        );
    }

    /** Drop fields (for example budget when the actor may not see it). */
    public function without(string ...$fields): self
    {
        $set = [];
        foreach ($this->set as $f) {
            if (!in_array($f, $fields, true)) {
                $set[] = $f;
            }
        }
        return new self($set, $this->title, $this->campaignId, $this->briefDate, $this->dueDate, $this->firstGoLive, $this->lastGoLive,
            $this->creativeDirection, $this->mandatories, $this->references, $this->briefPdfUrl, $this->serverLink, $this->budget, $this->hoursEstimate);
    }
}

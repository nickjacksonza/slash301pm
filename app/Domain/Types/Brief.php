<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefVersion;

/**
 * The working copy of a brief (briefs row). For a sent brief, version is the
 * last sent version and hasUnsentChanges says whether this copy differs from it.
 */
final class Brief
{
    /**
     * @param list<string> $mandatories
     * @param list<BriefReference> $references
     */
    public function __construct(
        public readonly string $id,
        public readonly string $jobId,
        public readonly string $title,
        public readonly ?string $campaignId,
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
        public readonly BriefVersion $version,
        public readonly bool $hasUnsentChanges,
        public readonly ?string $sentAt,
        public readonly ?string $sentBy,
        public readonly ?string $createdBy,
        public readonly string $createdAt,
        public readonly ?string $updatedBy,
        public readonly string $updatedAt,
        public readonly int $rowVersion,
    ) {}

    public static function fromRow(array $r): self
    {
        $mand = [];
        $decoded = is_string($r['mandatories'] ?? null) ? json_decode((string) $r['mandatories'], true) : null;
        if (is_array($decoded)) {
            foreach ($decoded as $m) {
                if (is_string($m)) {
                    $mand[] = $m;
                }
            }
        }
        $refs = is_string($r['references_json'] ?? null) ? BriefReference::listFromArray(json_decode((string) $r['references_json'], true)) : [];
        return new self(
            (string) $r['id'], (string) $r['job_id'], (string) $r['title'],
            self::optStr($r['campaign_id'] ?? null), self::optStr($r['brief_date'] ?? null), self::optStr($r['due_date'] ?? null),
            self::optStr($r['first_go_live'] ?? null), self::optStr($r['last_go_live'] ?? null),
            (string) ($r['creative_direction'] ?? ''), $mand, $refs,
            (string) ($r['brief_pdf_url'] ?? ''), (string) ($r['server_link'] ?? ''),
            $r['budget'] !== null ? (float) $r['budget'] : null, $r['hours_estimate'] !== null ? (float) $r['hours_estimate'] : null,
            new BriefVersion((int) $r['version_major'], (int) $r['version_minor'], (int) $r['version_patch']),
            (int) $r['has_unsent_changes'] === 1, self::optStr($r['sent_at'] ?? null), self::optStr($r['sent_by'] ?? null),
            self::optStr($r['created_by'] ?? null), (string) $r['created_at'], self::optStr($r['updated_by'] ?? null),
            (string) $r['updated_at'], (int) $r['row_version'],
        );
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    private static function optStr(mixed $v): ?string
    {
        return $v === null || $v === '' ? null : (string) $v;
    }
}

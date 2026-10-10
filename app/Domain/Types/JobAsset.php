<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** An asset of a job with its deliverable line and assignee, for the assets page and the job sheet. Go: type JobAsset struct. */
final class JobAsset
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly ?string $templateId,
        public readonly ?string $assigneeId,
        public readonly string $assigneeName,
        public readonly ?string $dueDate,
        public readonly ?string $lineId,
        public readonly string $lineLabel,
        public readonly string $channel,
    ) {}

    public static function fromRow(array $r): self
    {
        $opt = static fn (string $k): ?string => isset($r[$k]) && $r[$k] !== null && $r[$k] !== '' ? (string) $r[$k] : null;
        return new self(
            (string) $r['id'], (string) $r['name'], (string) $r['status'], $opt('template_id'), $opt('assigned_to'), (string) ($r['assignee_name'] ?? ''),
            $opt('due_date'), $opt('brief_asset_id'), (string) ($r['line_label'] ?? ''), (string) ($r['channel'] ?? ''),
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\BriefVersion;
use App\Domain\Stage;
use App\Domain\WaitingOn;

/** A job as the "My day" page needs it (store row). Go: type MyDayJob struct. */
final class MyDayJob
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $campaignName,
        public readonly string $brandName,
        public readonly Stage $stage,
        public readonly ?WaitingOn $waitingOn,
        public readonly string $waitingReason,
        public readonly BriefVersion $version,
        public readonly ?string $sentAt,
        public readonly bool $hasUnsentChanges,
        public readonly ?string $dueDate,
        public readonly bool $iAmAm,
        /** Assigned users only: the user holds the Traffic slot. */
        public readonly bool $iAmTraffic = false,
        /** Briefed with no CD, maker slot or asset assignee yet. */
        public readonly bool $needsTeam = false,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['job_id'], (string) $r['job_number'], (string) $r['title'], (string) ($r['campaign_name'] ?? ''), (string) ($r['brand_name'] ?? ''),
            Stage::tryFrom((string) ($r['stage'] ?? '')) ?? Stage::Draft,
            WaitingOn::tryFrom((string) ($r['waiting_on'] ?? '')),
            (string) ($r['waiting_reason'] ?? ''),
            new BriefVersion((int) $r['version_major'], (int) $r['version_minor'], (int) $r['version_patch']),
            $r['sent_at'] !== null && $r['sent_at'] !== '' ? (string) $r['sent_at'] : null,
            (int) ($r['has_unsent_changes'] ?? 0) === 1,
            $r['due_date'] !== null && $r['due_date'] !== '' ? (string) $r['due_date'] : null,
            (int) ($r['i_am_am'] ?? 0) === 1,
            (int) ($r['i_am_traffic'] ?? 0) === 1,
            (int) ($r['needs_team'] ?? 0) === 1,
        );
    }

    /** A job with no AM (JobStore::listWithoutAm), which the user could claim. */
    public static function fromListItem(BriefListItem $i): self
    {
        return new self($i->jobId, $i->jobNumber, $i->title, $i->campaignName, $i->brandName, $i->stage, null, '', $i->version, $i->sentAt, $i->hasUnsentChanges, $i->dueDate, false);
    }

    public function sent(): bool
    {
        return $this->sentAt !== null;
    }
}

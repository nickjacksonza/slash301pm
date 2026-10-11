<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** An activity row joined to its job, as a candidate for "Changed by others". Go: type MyDayChange struct. */
final class MyDayChange
{
    public function __construct(
        public readonly Activity $activity,
        public readonly string $jobNumber,
        public readonly string $jobTitle,
        /** The user holds the AM, PM or Producer slot, or created the brief. */
        public readonly bool $jobIsMine,
        /** The job's brand ('' for jobless rows or jobs without a campaign). */
        public readonly string $brandId = '',
    ) {}

    /** @param array<string,mixed> $r activity columns plus actor_name, job_number, job_title, job_is_mine */
    public static function fromRow(array $r): self
    {
        return new self(Activity::fromRow($r), (string) ($r['job_number'] ?? ''), (string) ($r['job_title'] ?? ''), (int) ($r['job_is_mine'] ?? 0) === 1,
            (string) ($r['brand_id'] ?? ''));
    }

    /** @return list<string> user ids in data.recipients (anything else in the JSON is ignored) */
    public function recipients(): array
    {
        $out = [];
        $raw = $this->activity->data['recipients'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $id) {
                if (is_string($id) && $id !== '') {
                    $out[] = $id;
                }
            }
        }
        return $out;
    }
}

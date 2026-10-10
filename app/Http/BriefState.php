<?php
declare(strict_types=1);

namespace App\Http;

use App\Domain\BriefDiff;
use App\Domain\BriefSend;
use App\Domain\BumpLevel;
use App\Domain\Types\Asset;
use App\Domain\Types\Brief;
use App\Domain\Types\BriefLine;
use App\Domain\Types\BriefSnapshot;
use App\Domain\Types\BriefVersionRecord;
use App\Domain\Types\Campaign;
use App\Domain\Types\Job;
use App\Domain\Types\JobAccess;
use App\Domain\Types\SendResult;
use App\Domain\Types\Team;
use DateTimeImmutable;

/**
 * Everything one brief page or action needs, loaded in one place (a few small
 * queries). lastSent is the snapshot creatives work from: the latest version
 * row, or for a brief backfilled as sent with no rows yet, the current state.
 * baseline is that synthesized snapshot, passed to every write so the store
 * records it as the imported 1.0.0 before the first change.
 * Go: a loader func in package web.
 */
final class BriefState
{
    /**
     * @param list<BriefLine> $lines
     * @param list<Asset> $assets
     */
    public function __construct(
        public readonly Job $job,
        public readonly JobAccess $access,
        public readonly Brief $brief,
        public readonly array $lines,
        public readonly Team $team,
        public readonly ?Campaign $campaign,
        public readonly array $assets,
        public readonly ?BriefVersionRecord $latest,
        public readonly ?BriefSnapshot $lastSent,
        public readonly ?BriefSnapshot $baseline,
    ) {}

    public static function load(Deps $d, string $jobId): ?self
    {
        $job = $d->jobs->get($jobId);
        $access = $job === null ? null : $d->jobs->access($jobId);
        $brief = $job === null ? null : $d->briefs->getByJob($jobId);
        if ($job === null || $access === null || $brief === null) {
            return null;
        }
        $lines = $d->briefAssets->listByBrief($brief->id);
        $team = $d->assignments->team($jobId);
        $campaign = $brief->campaignId !== null ? $d->campaigns->get($brief->campaignId) : null;
        $latest = $brief->isSent() ? $d->briefs->latestVersion($brief->id) : null;
        $baseline = null;
        $lastSent = null;
        if ($brief->isSent()) {
            if ($latest !== null) {
                $lastSent = $latest->snapshot;
            } else {
                $baseline = BriefSnapshot::of($brief, $lines, $team, $campaign !== null ? $campaign->name : '', $campaign !== null ? $campaign->brandName : '');
                $lastSent = $baseline;
            }
        }
        return new self($job, $access, $brief, $lines, $team, $campaign, $d->assets->listByJob($jobId), $latest, $lastSent, $baseline);
    }

    public function campaignName(): string
    {
        return $this->campaign !== null ? $this->campaign->name : '';
    }

    public function brandName(): string
    {
        return $this->campaign !== null ? $this->campaign->brandName : (string) $this->job->brandName;
    }

    /** The working copy as a snapshot. */
    public function current(): BriefSnapshot
    {
        return BriefSnapshot::of($this->brief, $this->lines, $this->team, $this->campaignName(), $this->brandName());
    }

    /** Working copy vs last sent version; null for a never-sent brief. */
    public function diff(): ?BriefDiff
    {
        return $this->lastSent === null ? null : BriefDiff::between($this->lastSent, $this->current());
    }

    public function sendPlan(?BumpLevel $bump, string $note, DateTimeImmutable $now): SendResult
    {
        return BriefSend::plan($this->job, $this->brief, $this->lines, $this->team, $this->assets, $this->lastSent,
            $this->campaignName(), $this->brandName(), $bump, $note, $now);
    }
}

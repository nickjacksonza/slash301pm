<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\MyDayBrand;
use App\Domain\Types\MyDayChange;
use App\Domain\Types\MyDayItem;
use App\Domain\Types\MyDayJob;
use App\Domain\Types\MyDayResult;
use App\Domain\Types\MyDaySection;
use App\Domain\Types\MyDayStrip;
use DateTimeImmutable;

/**
 * Builds the "My day" sections from store rows. Pure: no clock (pass $now) and
 * no database. Sections: Overdue, Due soon (today + next 3 business days),
 * Waiting on me, Changed by others since the last visit, and a week strip.
 */
final class MyDay
{
    public const OVERDUE = 'overdue';
    public const DUE_SOON = 'due-soon';
    public const WAITING = 'waiting';
    public const CHANGED = 'changed';
    public const STRIP = 'strip';
    /** Traffic: "Briefs waiting for Traffic". */
    public const TEAM = 'team';
    /** Assigned users: "New and updated briefs". */
    public const BRIEFS = 'briefs';

    /** Rows shown per section; the rest is a count and a link. */
    public const SHOW = 8;

    /** Rows shown for jobs nobody holds as AM (legacy jobs, often many). */
    public const CLAIM_SHOW = 4;

    /** The sections of each mode, in page order. @return list<string> */
    public static function sectionKeys(MyDayMode $mode = MyDayMode::Owner): array
    {
        return match ($mode) {
            MyDayMode::Owner => [self::STRIP, self::OVERDUE, self::DUE_SOON, self::WAITING, self::CHANGED],
            MyDayMode::Traffic => [self::STRIP, self::TEAM, self::OVERDUE, self::DUE_SOON, self::CHANGED],
            MyDayMode::Assigned => [self::STRIP, self::BRIEFS, self::OVERDUE, self::DUE_SOON, self::CHANGED],
        };
    }

    /** When there is no earlier visit, "changed" looks back this far. */
    public const FIRST_VISIT_DAYS = 7;

    /**
     * @param list<MyDayJob> $owned jobs the user holds as AM, PM or Producer, or created
     * @param list<MyDayJob> $claimable open jobs with no AM
     * @param list<MyDayChange> $changes activity candidates (the store may over-fetch; this filters exactly)
     * @param string $lastSeen UTC 'Y-m-d H:i:s' of the last "Mark all seen"
     */
    public static function build(string $userId, Role $role, array $owned, array $claimable, array $changes, string $lastSeen, DateTimeImmutable $now): MyDayResult
    {
        $today = Dates::today($now);
        $overdue = [];
        $dueSoon = [];
        $waiting = [];
        $unsentChanges = [];
        $drafts = [];
        $weekStart = Dates::weekStart($today);
        $weekEnd = Dates::weekEnd($today);
        $dueThisWeek = 0;
        $sentThisWeek = 0;
        $attention = [];
        foreach ($owned as $j) {
            if (!$j->stage->isOpen()) {
                continue;
            }
            $bucket = Dates::bucket($j->dueDate, $now);
            $due = Dates::normalize($j->dueDate);
            if (!$j->stage->countsDueDate()) {
                // Client-approved and Social stages: the delivery date no longer applies (N13).
            } elseif ($bucket === DueBucket::Overdue) {
                $overdue[] = self::item($j, $bucket, $today, '');
                $attention[$j->jobId] = true;
            } elseif ($bucket === DueBucket::Today || $bucket === DueBucket::Next3BusinessDays) {
                $dueSoon[] = self::item($j, $bucket, $today, '');
            }
            if ($due !== null && $due >= $today && $due <= $weekEnd) {
                $dueThisWeek++;
            }
            if ($j->sentAt !== null) {
                $sentOn = Dates::localDate($j->sentAt);
                if ($sentOn !== null && $sentOn >= $weekStart && $sentOn <= $weekEnd) {
                    $sentThisWeek++;
                }
            }
            if ($j->stage === Stage::Waiting && $j->waitingOn === WaitingOn::Am && $j->iAmAm) {
                $waiting[] = self::item($j, $bucket, $today, $j->waitingReason !== '' ? 'Waiting on you: ' . $j->waitingReason : 'Waiting on you');
                $attention[$j->jobId] = true;
            } elseif ($j->sent() && $j->hasUnsentChanges) {
                $unsentChanges[] = self::item($j, $bucket, $today, 'Unsent changes since v' . $j->version->format());
                $attention[$j->jobId] = true;
            } elseif ($j->stage === Stage::Draft && !$j->sent()) {
                $drafts[] = self::item($j, $bucket, $today, 'Draft, not sent to Traffic yet');
                $attention[$j->jobId] = true;
            }
        }
        $ownedIds = [];
        foreach ($owned as $j) {
            $ownedIds[$j->jobId] = true;
        }
        $claim = [];
        if (in_array($role, [Role::AM, Role::PM, Role::Producer], true)) {
            foreach ($claimable as $j) {
                if ($j->stage->isOpen() && !isset($ownedIds[$j->jobId])) {
                    $claim[] = self::item($j, Dates::bucket($j->dueDate, $now), $today, 'No AM yet. Open it and choose "Make me AM".');
                }
            }
        }
        $waitingAll = array_merge($waiting, $unsentChanges, $drafts);

        $mine = self::changesFor($userId, $changes, $lastSeen, false);

        $waitingOnMeCount = count($waiting) + count($unsentChanges) + count($drafts);
        $strip = new MyDayStrip($dueThisWeek, count($overdue), $waitingOnMeCount, $sentThisWeek);
        return new MyDayResult(
            self::section(self::OVERDUE, $overdue),
            self::section(self::DUE_SOON, $dueSoon),
            self::section(self::WAITING, $waitingAll),
            new MyDaySection('claimable', array_slice($claim, 0, self::CLAIM_SHOW), count($claim)),
            array_slice($mine, 0, self::SHOW),
            count($mine),
            $strip,
            count($attention),
        );
    }

    /**
     * My day of Traffic (MyDayMode::Traffic) and of CD, makers and QA
     * (MyDayMode::Assigned), from the jobs they are assigned to. Overdue and
     * Due soon as for owners; Traffic also gets "Briefs waiting for Traffic"
     * (briefed jobs on which they hold the Traffic slot and nobody makes the
     * work yet, plus briefs sent or updated since the last visit), assigned
     * users "New and updated briefs". Activity on the working copy is left out:
     * these users only ever see sent versions.
     * @param list<MyDayJob> $assigned open, sent jobs the user is assigned to (MyDayStore::assignedOpen)
     * @param list<MyDayChange> $changes
     */
    public static function buildAssigned(string $userId, MyDayMode $mode, array $assigned, array $changes, string $lastSeen, DateTimeImmutable $now): MyDayResult
    {
        $today = Dates::today($now);
        $weekStart = Dates::weekStart($today);
        $weekEnd = Dates::weekEnd($today);
        $overdue = [];
        $dueSoon = [];
        $news = [];
        $dueThisWeek = 0;
        $sentThisWeek = 0;
        $attention = [];
        foreach ($assigned as $j) {
            if (!$j->stage->isOpen() || $j->stage === Stage::Draft) {
                continue;
            }
            $bucket = Dates::bucket($j->dueDate, $now);
            $due = Dates::normalize($j->dueDate);
            if (!$j->stage->countsDueDate()) {
                // Client-approved and Social stages: the delivery date no longer applies (N13).
            } elseif ($bucket === DueBucket::Overdue) {
                $overdue[] = self::item($j, $bucket, $today, '');
                $attention[$j->jobId] = true;
            } elseif ($bucket === DueBucket::Today || $bucket === DueBucket::Next3BusinessDays) {
                $dueSoon[] = self::item($j, $bucket, $today, '');
            }
            if ($due !== null && $due >= $today && $due <= $weekEnd) {
                $dueThisWeek++;
            }
            $sentOn = $j->sentAt !== null ? Dates::localDate($j->sentAt) : null;
            if ($sentOn !== null && $sentOn >= $weekStart && $sentOn <= $weekEnd) {
                $sentThisWeek++;
            }
            $fresh = $j->sentAt !== null && $j->sentAt > $lastSeen;
            $what = self::briefNews($j);
            $reason = '';
            if ($mode === MyDayMode::Traffic) {
                if (!$j->iAmTraffic) {
                    continue;
                }
                if ($j->needsTeam) {
                    $reason = 'Needs a team: assign the CD and creatives' . ($fresh ? ' (' . $what . ')' : '') . '.';
                    $attention[$j->jobId] = true;
                } elseif ($fresh) {
                    $reason = $what . '.';
                }
            } elseif ($fresh) {
                $reason = $what . '.';
            }
            if ($reason !== '') {
                $news[] = [(string) $j->sentAt, self::item($j, $bucket, $today, $reason)];
            }
        }
        usort($news, static fn (array $a, array $b): int => strcmp($b[0], $a[0]) ?: strcmp($a[1]->jobNumber, $b[1]->jobNumber));
        $newsItems = [];
        foreach ($news as $n) {
            $newsItems[] = $n[1];
        }
        $mine = self::changesFor($userId, $changes, $lastSeen, true);
        $newsSection = new MyDaySection($mode === MyDayMode::Traffic ? self::TEAM : self::BRIEFS, array_slice($newsItems, 0, self::SHOW), count($newsItems));
        $empty = new MyDaySection(self::WAITING, [], 0);
        return new MyDayResult(
            self::section(self::OVERDUE, $overdue),
            self::section(self::DUE_SOON, $dueSoon),
            $empty,
            new MyDaySection('claimable', [], 0),
            array_slice($mine, 0, self::SHOW),
            count($mine),
            new MyDayStrip($dueThisWeek, count($overdue), count($newsItems), $sentThisWeek),
            count($attention),
            $mode,
            $mode === MyDayMode::Traffic ? $newsSection : null,
            $mode === MyDayMode::Assigned ? $newsSection : null,
        );
    }

    // ---- brand filter row (owner decision 2026-10) ---------------------------------

    /**
     * The brands of the open jobs My day shows (owned jobs for owners; sent,
     * assigned jobs otherwise), with a job count each, by name. Jobs without a
     * brand get no button.
     * @param list<MyDayJob> $jobs
     * @return list<MyDayBrand>
     */
    public static function brands(array $jobs, MyDayMode $mode): array
    {
        $by = [];
        foreach ($jobs as $j) {
            if (!$j->stage->isOpen() || $j->brandId === '' || ($mode !== MyDayMode::Owner && $j->stage === Stage::Draft)) {
                continue;
            }
            if (!isset($by[$j->brandId])) {
                $by[$j->brandId] = ['name' => $j->brandName, 'logo' => $j->brandLogoUrl, 'n' => 0];
            }
            $by[$j->brandId]['n']++;
        }
        $out = [];
        foreach ($by as $id => $b) {
            $out[] = new MyDayBrand((string) $id, $b['name'], $b['logo'], $b['n']);
        }
        usort($out, static fn (MyDayBrand $a, MyDayBrand $b): int => strcasecmp($a->name, $b->name) ?: strcmp($a->id, $b->id));
        return $out;
    }

    /**
     * The brand filter actually applied: $wanted when it is one of $brands
     * (a signal is user input; anything else means "All"), else ''.
     * @param list<MyDayBrand> $brands
     */
    public static function selectedBrand(string $wanted, array $brands): string
    {
        foreach ($brands as $b) {
            if ($b->id === $wanted) {
                return $wanted;
            }
        }
        return '';
    }

    /** @param list<MyDayJob> $jobs @return list<MyDayJob> only $brandId's jobs ('' keeps all) */
    public static function jobsOfBrand(array $jobs, string $brandId): array
    {
        if ($brandId === '') {
            return $jobs;
        }
        $out = [];
        foreach ($jobs as $j) {
            if ($j->brandId === $brandId) {
                $out[] = $j;
            }
        }
        return $out;
    }

    /** @param list<MyDayChange> $changes @return list<MyDayChange> only changes on $brandId's jobs ('' keeps all) */
    public static function changesOfBrand(array $changes, string $brandId): array
    {
        if ($brandId === '') {
            return $changes;
        }
        $out = [];
        foreach ($changes as $c) {
            if ($c->brandId === $brandId) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** "New brief v1.0.0" or "Brief updated to v1.2.0". */
    public static function briefNews(MyDayJob $j): string
    {
        $v = $j->version;
        return $v->major === 1 && $v->minor === 0 && $v->patch === 0 ? 'New brief ' . $v->label() : 'Brief updated to ' . $v->label();
    }

    /**
     * Activity by others after $lastSeen on the user's jobs or addressed to them, newest first.
     * @param list<MyDayChange> $changes
     * @return list<MyDayChange>
     */
    private static function changesFor(string $userId, array $changes, string $lastSeen, bool $sentOnly): array
    {
        $mine = [];
        foreach ($changes as $c) {
            $a = $c->activity;
            if ($a->actorId === $userId || $a->createdAt <= $lastSeen) {
                continue;
            }
            if ($sentOnly && Notifications::isWorkingCopyVerb($a->verb)) {
                continue;
            }
            if ($c->jobIsMine || in_array($userId, $c->recipients(), true)) {
                $mine[] = $c;
            }
        }
        usort($mine, static fn (MyDayChange $x, MyDayChange $y): int => strcmp($y->activity->createdAt, $x->activity->createdAt));
        return $mine;
    }

    /** UTC stamp to compare activity against: the saved one, else a week before $now. */
    public static function since(?string $saved, DateTimeImmutable $now): string
    {
        if ($saved !== null && $saved !== '') {
            return $saved;
        }
        return $now->modify('-' . self::FIRST_VISIT_DAYS . ' days')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** A short phrase for an activity verb ("sent the brief"). Unknown verbs read as words. */
    public static function verbPhrase(string $verb): string
    {
        return match ($verb) {
            Notifications::BRIEF_SENT => 'sent the brief',
            Notifications::BRIEF_UPDATED => 'sent a brief update',
            Notifications::BRIEF_RECALLED => 'recalled the brief',
            Notifications::ASSIGNED_TO_JOB => 'assigned someone to the job',
            Notifications::UNASSIGNED_FROM_JOB => 'removed someone from the job',
            Notifications::DELIVERABLE_CANCELLED => 'cancelled a deliverable',
            Notifications::STARTED_ASSET_CONFLICT => 'changed a deliverable that had started',
            Notifications::JOB_WAITING_ON_YOU, 'job_waiting' => 'put the job on waiting',
            Notifications::JOB_RESUMED => 'resumed the job',
            Notifications::JOB_ON_HOLD => 'put the job on hold',
            Notifications::JOB_CANCELLED => 'cancelled the job',
            'job_created' => 'created the job',
            'job_archived' => 'archived the job',
            'job_done' => 'marked the job done',
            'job_start' => 'started work',
            'brief_edited' => 'edited the brief',
            'deliverable_added' => 'added a deliverable',
            'deliverable_updated' => 'changed a deliverable',
            'deliverable_removed' => 'removed a deliverable',
            'deliverables_reordered' => 'reordered the deliverables',
            Notifications::ASSET_STATUS_OVERRIDDEN => 'overrode an asset status',
            // Social publishing
            default => Notifications::socialPhrase($verb, []) ?? str_replace('_', ' ', $verb),
        };
    }

    /** @param list<MyDayItem> $items sorted here: overdue/due soon by due date, others as given */
    private static function section(string $key, array $items): MyDaySection
    {
        if ($key === self::OVERDUE || $key === self::DUE_SOON) {
            usort($items, static fn (MyDayItem $a, MyDayItem $b): int => strcmp((string) $a->dueDate, (string) $b->dueDate) ?: strcmp($a->jobNumber, $b->jobNumber));
        }
        return new MyDaySection($key, array_slice($items, 0, self::SHOW), count($items));
    }

    private static function item(MyDayJob $j, DueBucket $bucket, string $today, string $reason): MyDayItem
    {
        $due = Dates::normalize($j->dueDate);
        return new MyDayItem(
            $j->jobId, $j->jobNumber, $j->title, $j->brandName, $j->campaignName, $j->stage, $j->version, $j->sent(),
            $due, $bucket, $due === null ? 0 : Dates::daysBetween($today, $due), $reason,
        );
    }
}

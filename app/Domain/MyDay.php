<?php
declare(strict_types=1);

namespace App\Domain;

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

    /** Rows shown per section; the rest is a count and a link. */
    public const SHOW = 8;

    /** Rows shown for jobs nobody holds as AM (legacy jobs, often many). */
    public const CLAIM_SHOW = 4;

    /** @return list<string> */
    public static function sectionKeys(): array
    {
        return [self::STRIP, self::OVERDUE, self::DUE_SOON, self::WAITING, self::CHANGED];
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
            if ($bucket === DueBucket::Overdue) {
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

        $mine = [];
        foreach ($changes as $c) {
            $a = $c->activity;
            if ($a->actorId === $userId || $a->createdAt <= $lastSeen) {
                continue;
            }
            if ($c->jobIsMine || in_array($userId, $c->recipients(), true)) {
                $mine[] = $c;
            }
        }
        usort($mine, static fn (MyDayChange $x, MyDayChange $y): int => strcmp($y->activity->createdAt, $x->activity->createdAt));

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
            'brief_edited' => 'edited the brief',
            'deliverable_added' => 'added a deliverable',
            'deliverable_updated' => 'changed a deliverable',
            'deliverable_removed' => 'removed a deliverable',
            'deliverables_reordered' => 'reordered the deliverables',
            default => str_replace('_', ' ', $verb),
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

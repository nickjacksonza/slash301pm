<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\ReviewDay;
use App\Domain\Types\ReviewDayItem;
use App\Domain\Types\ReviewJobState;
use App\Domain\Types\User;

/**
 * The review lists of My day (owner spec 2026-10). Pure.
 * - To review: the CD role, or anyone the review was handed to: one line per
 *   job whose reviewer (ReviewNotify::reviewers) is this person and that has
 *   a part waiting for a decision.
 * - Waiting for ECD approval: the ECD and the COO: jobs the CD approved or
 *   asked the ECD to review, not ECD-approved yet.
 * - Changes requested: makers: posts with a part rejected that this person
 *   makes (ReviewRules::maker).
 * - Round limit and Ready for client: owner roles (AM, PM, Producer, COO, ECD)
 *   on the jobs they own (AM slot, PM or Producer slot, or brief creator);
 *   Ready for client also for the Traffic slot holder (only when it has jobs). A job approved by the
 *   CD and the ECD and not sent to the client yet.
 * Go: package domain, func BuildReviewDay.
 */
final class ReviewDayRules
{
    /**
     * @param list<ReviewJobState> $jobs open jobs with review activity
     * @param list<string> $ecdIds active ECD users
     */
    public static function build(User $u, array $jobs, array $ecdIds, int $roundLimit): ReviewDay
    {
        $reviewerRole = Policy::rule('approve_internal', $u->role) !== PolicyRule::Deny;
        $ecdRole = $u->role === Role::ECD || $u->role === Role::COO;
        $maker = in_array($u->role, Policy::MAKERS, true);
        $owner = Policy::myDayMode($u) === MyDayMode::Owner;
        $traffic = $u->role === Role::Traffic;
        $toReview = [];
        $ecdWaiting = [];
        $changes = [];
        $warnings = [];
        $ready = [];
        $handedToMe = false;
        foreach ($jobs as $j) {
            $jr = $j->review;
            if (in_array($u->id, ReviewNotify::reviewers($jr, $j->team, $ecdIds), true)) {
                $waiting = 0;
                foreach ($j->assets as $a) {
                    if (!AssetStatus::isCancelled($a->assetStatus) && $a->waitingReview()) {
                        $waiting++;
                    }
                }
                $handedToMe = $handedToMe || $jr->assigneeId === $u->id;
                if ($waiting > 0) {
                    $toReview[] = new ReviewDayItem($j->jobId, $j->jobNumber, $j->title, $waiting === 1 ? '1 post waiting' : $waiting . ' posts waiting', 'review');
                }
            }
            if ($ecdRole && $jr->waitingForEcd()) {
                $ecdWaiting[] = new ReviewDayItem($j->jobId, $j->jobNumber, $j->title,
                    $jr->cdApproved() ? 'Approved by ' . ($jr->cdApprovedByName !== '' ? $jr->cdApprovedByName : 'the CD') : 'The CD asked for your review', 'review');
            }
            foreach ($j->assets as $a) {
                if (AssetStatus::isCancelled($a->assetStatus)) {
                    continue;
                }
                $mine = [];
                foreach ($a->requiredParts() as $p) {
                    if ($a->state($p)->isRejected() && ReviewRules::maker($a, $p, $j->team) === $u->id) {
                        $mine[] = strtolower($p->label());
                    }
                }
                if ($mine !== []) {
                    $changes[] = new ReviewDayItem($j->jobId, $j->jobNumber, $a->assetName, 'Change the ' . implode(' and the ', $mine) . ' (round ' . $a->round . ')', 'work');
                }
            }
            $owns = $j->creatorId === $u->id || $j->team->userFor(Role::AM) === $u->id || $j->team->userFor(Role::PM) === $u->id
                || $j->team->userFor(Role::Producer) === $u->id;
            if ($owner && $owns && $roundLimit > 0) {
                foreach ($j->assets as $a) {
                    if (!AssetStatus::isCancelled($a->assetStatus) && $a->round > $roundLimit && !$a->allApproved()) {
                        $warnings[] = new ReviewDayItem($j->jobId, $j->jobNumber, $a->assetName, 'Round ' . $a->round . ' (limit ' . $roundLimit . ')', 'review');
                    }
                }
            }
            $trafficHere = $traffic && $j->team->userFor(Role::Traffic) === $u->id;
            if ((($owner && $owns) || $trafficHere) && $jr->internallyApproved() && !$jr->sentToClient()) {
                $ready[] = new ReviewDayItem($j->jobId, $j->jobNumber, $j->title,
                    $jr->readyForClient() ? 'Ready for client review: send it' : 'Approved by the CD and the ECD: get it ready for the client', 'review');
            }
        }
        return new ReviewDay(
            $reviewerRole && ($u->role === Role::CD || $toReview !== [] || $handedToMe) ? $toReview : null,
            $ecdRole ? $ecdWaiting : null,
            $maker || $changes !== [] ? $changes : null,
            $owner ? $warnings : null,
            $owner || ($traffic && $ready !== []) ? $ready : null,
        );
    }
}

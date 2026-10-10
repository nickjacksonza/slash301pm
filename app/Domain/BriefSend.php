<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\Asset;
use App\Domain\Types\AssetNaming;
use App\Domain\Types\Brief;
use App\Domain\Types\BriefLine;
use App\Domain\Types\BriefSnapshot;
use App\Domain\Types\Job;
use App\Domain\Types\SendPlan;
use App\Domain\Types\SendResult;
use App\Domain\Types\Team;
use DateTimeImmutable;

/**
 * Plans "Send to Traffic" (first send, 1.0.0) and "Send update" (bump chosen by
 * the sender, change note required). A brief that was sent and then recalled to
 * draft is re-sent as an update. Pure: the caller loads, the store applies.
 */
final class BriefSend
{
    public const MAX_NOTE = 1000;

    /**
     * @param list<BriefLine> $lines
     * @param list<Asset> $assets every asset of the job
     */
    public static function plan(
        Job $job,
        Brief $brief,
        array $lines,
        Team $team,
        array $assets,
        ?BriefSnapshot $lastSent,
        string $campaignName,
        string $brandName,
        ?BumpLevel $bump,
        string $note,
        DateTimeImmutable $now,
    ): SendResult {
        $errors = BriefRules::readyToSend($brief, $lines, $team);
        $snapshot = BriefSnapshot::of($brief, $lines, $team, $campaignName, $brandName);
        $isFirst = !$brief->isSent();
        $diff = null;
        $note = trim($note);
        if ($job->stage->isClosed()) {
            $errors = $errors->with('stage', 'The job is ' . strtolower($job->stage->label()) . '; its brief can no longer be sent.');
        }
        if ($isFirst) {
            if ($job->stage !== Stage::Draft) {
                $errors = $errors->with('stage', 'Only a draft can be sent for the first time.');
            }
            $version = BriefVersion::first();
            $level = 'initial';
        } else {
            if ($lastSent !== null) {
                $diff = BriefDiff::between($lastSent, $snapshot);
                // A recalled brief (job back in draft) may be re-sent unchanged.
                if ($diff->isEmpty() && $job->stage !== Stage::Draft) {
                    $errors = $errors->with('changes', 'Nothing has changed since ' . $brief->version->label() . '.');
                }
            }
            if ($bump === null) {
                $errors = $errors->with('bump', 'Choose major, minor or patch.');
            }
            if ($note === '') {
                $errors = $errors->with('note', 'Write a short change note for the team.');
            }
            $version = $brief->version->bump($bump ?? BumpLevel::Patch);
            $level = ($bump ?? BumpLevel::Patch)->value;
        }
        if (mb_strlen($note) > self::MAX_NOTE) {
            $errors = $errors->with('note', 'Keep the change note under 1000 characters.');
        }
        if (!$errors->isEmpty()) {
            return new SendResult(null, $errors);
        }
        $toStage = $job->stage === Stage::Draft ? Stage::Briefed : $job->stage;
        $assetPlan = AssetPlan::plan($lines, $assets, new AssetNaming($job->jobNumber, $brandName, $campaignName, $brief->dueDate, $now));
        $event = $isFirst ? 'brief_sent' : 'brief_updated';
        $assignees = [];
        foreach ($assets as $a) {
            if ($a->assignedTo !== null) {
                $assignees[] = $a->assignedTo;
            }
        }
        $plan = new SendPlan(
            $job->id, $brief->id, $isFirst, $version, $level, $note, $snapshot, $diff, $assetPlan,
            $job->stage, $toStage, $toStage->toLegacy($job->status), $job->rowVersion, $brief->rowVersion,
            Notifications::recipients($event, $team, $assignees, ''),
        );
        return new SendResult($plan, $errors);
    }
}

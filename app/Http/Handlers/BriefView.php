<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\AssetTemplates;
use App\Domain\BriefDiff;
use App\Domain\BriefRules;
use App\Domain\BumpLevel;
use App\Domain\JobAction;
use App\Domain\Notifications;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\Activity;
use App\Domain\Types\BriefSnapshot;
use App\Domain\Types\BriefVersionRecord;
use App\Domain\Types\Team;
use App\Domain\Types\User;
use App\Http\BriefState;
use App\Http\Deps;
use App\View\ui\SelectOption;
use App\View\VM\ActivityItemVM;
use App\View\VM\BriefDocVM;
use App\View\VM\BriefEditorVM;
use App\View\VM\BriefRailVM;
use App\View\VM\SendDialogVM;
use App\View\VM\TeamSlotVM;
use DateTimeImmutable;

/** Builds the brief page view models from a BriefState and the actor. Templates never see Policy or stores. */
final class BriefView
{
    public static function editor(BriefState $s, User $u, Deps $d, bool $claimMode = false): BriefEditorVM
    {
        $canEdit = !$claimMode && Policy::canEditBrief($u, $s->access)->allowed;
        $doc = null;
        if (!$canEdit) {
            if ($s->lastSent !== null) {
                $doc = self::doc($s, $s->lastSent, $s->brief->version->label(), $s->latest, $u, false);
            } elseif ($claimMode || Policy::canViewBriefDraft($u, $s->access)->allowed) {
                $doc = self::doc($s, $s->current(), 'Draft', null, $u, true);
            }
        }
        $label = trim(($s->brandName() !== '' ? $s->brandName() . ' · ' : '') . $s->campaignName(), ' ·');
        // Readers who may not see the working copy get the last sent title (the heading must not leak unsent edits).
        $title = $canEdit || $claimMode || Policy::canViewBriefDraft($u, $s->access)->allowed ? $s->brief->title : ($s->lastSent !== null ? $s->lastSent->title : $s->job->title);
        return new BriefEditorVM(
            $s->job->id, $s->job->jobNumber, $title, $label, $canEdit,
            Policy::canViewBudget($u, $s->access)->allowed, Policy::canViewHours($u, $s->access)->allowed,
            $s->brief, self::campaignOptions($d, $s), self::templateOptions(), $s->lines, self::team($s, $u, $d), self::rail($s, $u, $d, $claimMode), $doc,
        );
    }

    public static function rail(BriefState $s, User $u, Deps $d, bool $claimMode = false): BriefRailVM
    {
        $a = $s->access;
        $canEdit = !$claimMode && Policy::canEditBrief($u, $a)->allowed;
        $sent = $s->brief->isSent();
        // Unsent changes and working-copy activity only for who may read the working copy.
        $draftReader = $canEdit || $claimMode || Policy::canViewBriefDraft($u, $a)->allowed;
        $diff = $draftReader ? $s->diff() : null;
        $actions = [];
        if (!$claimMode) {
            foreach (JobAction::phase2() as $act) {
                if (Transitions::matrixId($act, $a->stage) !== null && Policy::canTransition($u, $a, $act)->allowed) {
                    $actions[] = $act;
                }
            }
        }
        $checklist = BriefRules::checklist($s->brief, $s->lines, $s->team);
        $ready = true;
        foreach ($checklist as $c) {
            $ready = $ready && $c->ok;
        }
        $note = '';
        if ($s->job->stage === Stage::Waiting) {
            $note = 'Waiting on ' . ($s->job->waitingOn !== null ? strtolower($s->job->waitingOn->label()) : 'someone') . ($s->job->waitingReason !== '' ? ': ' . $s->job->waitingReason : '');
        } elseif ($s->job->stage === Stage::OnHold) {
            $note = 'On hold' . ($s->job->waitingReason !== '' ? ': ' . $s->job->waitingReason : '');
        } elseif (!$a->hasAm()) {
            $note = 'This job has no AM yet.';
        }
        return new BriefRailVM(
            $s->job->id, $s->job->stage->value, $s->job->stage->label(), $note,
            $s->brief->version->label(), $sent,
            $diff !== null && (!$diff->isEmpty() || $s->job->stage === Stage::Draft), $checklist, $ready,
            $s->brief->updatedBy !== null ? fmt_time($s->brief->updatedAt) : '',
            $canEdit && !$sent && Policy::canSendBrief($u, $a)->allowed,
            $canEdit && $sent && Policy::canSendBriefUpdate($u, $a)->allowed,
            $actions, Policy::canClaimAm($u, $a)->allowed, $canEdit,
            self::activity($d->activity->listForJob($s->job->id, 15), !$draftReader), $s->brief->rowVersion,
        );
    }

    /** @return list<TeamSlotVM> */
    public static function team(BriefState $s, User $u, Deps $d): array
    {
        $out = [];
        $editable = Policy::canEditBrief($u, $s->access)->allowed || Policy::canAssign($u, $s->access, Role::Traffic)->allowed;
        $anyEditable = false;
        foreach (Team::slotRoles() as $role) {
            $anyEditable = $anyEditable || ($editable && Policy::canAssign($u, $s->access, $role)->allowed);
        }
        foreach (Team::slotRoles() as $role) {
            $holder = $s->team->holder($role);
            // Readers who can set nothing see only the filled slots.
            if (!$anyEditable && $holder === null) {
                continue;
            }
            $options = [new SelectOption('', 'Not assigned')];
            foreach ($d->users->listActiveByRole($role) as $p) {
                $options[] = new SelectOption($p->id, $p->name);
            }
            $can = $editable && Policy::canAssign($u, $s->access, $role)->allowed;
            $hint = '';
            if (!$anyEditable) {
                $hint = '';
            } elseif (!$can && in_array($role, [Role::CD, Role::Copywriter, Role::Designer, Role::QA, Role::Developer, Role::SEO, Role::Social], true) && $s->brief->isSent()) {
                $hint = 'Traffic assigns this after the brief is sent.';
            } elseif ($role === Role::Traffic && !$s->brief->isSent()) {
                $hint = 'Required to send.';
            }
            $key = strtolower($role->value);
            $out[] = new TeamSlotVM($role->value, $key, $role === Role::CD ? 'Creative director' : $role->value, $role === Role::Traffic,
                $holder !== null ? $holder->userId : '', $holder !== null ? $holder->userName : '', $options, $can, 'team-' . $key, $hint);
        }
        return $out;
    }

    public static function doc(BriefState $s, BriefSnapshot $snap, string $versionLabel, ?BriefVersionRecord $rec, User $u, bool $working): BriefDocVM
    {
        $sentLine = '';
        $note = '';
        if ($rec !== null) {
            $sentLine = 'Sent ' . fmt_when($rec->createdAt) . ($rec->createdByName !== '' ? ' by ' . $rec->createdByName : '');
            $note = $rec->bumpLevel === 'initial' ? '' : $rec->note;
        } elseif (!$working && $s->brief->sentAt !== null) {
            $sentLine = 'Sent ' . fmt_when($s->brief->sentAt) . ' (from the old app)';
        }
        return new BriefDocVM($s->job->id, $s->job->jobNumber, $versionLabel, $sentLine, $note, $snap,
            Policy::canViewBudget($u, $s->access)->allowed, Policy::canViewHours($u, $s->access)->allowed, $working);
    }

    public static function sendDialog(BriefState $s, User $u, DateTimeImmutable $now, string $problem = ''): SendDialogVM
    {
        $checklist = BriefRules::checklist($s->brief, $s->lines, $s->team);
        $ready = BriefRules::readyToSend($s->brief, $s->lines, $s->team)->isEmpty();
        $preview = $s->sendPlan(null, '', $now);
        $create = $preview->plan !== null ? count($preview->plan->assets->create) : 0;
        $traffic = $s->team->holder(Role::Traffic);
        return new SendDialogVM($s->job->id, false, $checklist, $ready && $preview->errors->isEmpty(), '', [], '', '', null, $create, 0,
            $preview->plan !== null ? $preview->plan->assets->warnings : [], $traffic !== null ? $traffic->userName : '', false,
            $ready && !$preview->errors->isEmpty() ? $preview->errors->first() : $problem);
    }

    public static function updateDialog(BriefState $s, User $u, DateTimeImmutable $now): SendDialogVM
    {
        $checklist = BriefRules::checklist($s->brief, $s->lines, $s->team);
        $ready = BriefRules::readyToSend($s->brief, $s->lines, $s->team)->isEmpty();
        $diff = $s->diff() ?? new BriefDiff([], [], []);
        $sugg = BriefDiff::suggestBump($diff, $s->lastSent !== null ? count($s->lastSent->lines) : 0);
        $bumps = [];
        foreach (BumpLevel::cases() as $b) {
            $bumps[$b->value] = $s->brief->version->bump($b)->label();
        }
        $preview = $s->sendPlan($sugg->level, 'preview', $now);
        $plan = $preview->plan;
        $recalled = $s->job->stage === Stage::Draft;
        $problem = $diff->isEmpty() && !$recalled ? 'Nothing has changed since ' . $s->brief->version->label() . '.' : '';
        return new SendDialogVM($s->job->id, true, $checklist, $ready && ($recalled || !$diff->isEmpty()), $s->brief->version->label(), $bumps, $sugg->level->value, $sugg->reason, $diff,
            $plan !== null ? count($plan->assets->create) : 0, $plan !== null ? count($plan->assets->cancel) : 0, $plan !== null ? $plan->assets->warnings : [],
            '', Policy::canViewBudget($u, $s->access)->allowed, $problem);
    }

    /** @return list<SelectOption> */
    public static function templateOptions(): array
    {
        $out = [new SelectOption('', 'Custom deliverable')];
        foreach (AssetTemplates::all() as $t) {
            $out[] = new SelectOption($t->id, $t->name);
        }
        return $out;
    }

    /** Campaigns of the job's brand (job numbers belong to the brand). @return list<SelectOption> */
    public static function campaignOptions(Deps $d, BriefState $s): array
    {
        $out = [];
        $brandId = $s->campaign !== null ? $s->campaign->brandId : $s->job->brandId;
        if ($brandId === null) {
            return $out;
        }
        foreach ($d->campaigns->listByBrand($brandId) as $c) {
            $out[] = new SelectOption($c->id, $c->name);
        }
        return $out;
    }

    /**
     * @param list<Activity> $rows
     * @param bool $sentOnly leave out draft and working-copy edits (viewers without view_brief_draft)
     * @return list<ActivityItemVM>
     */
    public static function activity(array $rows, bool $sentOnly = false): array
    {
        $out = [];
        foreach ($rows as $a) {
            if ($sentOnly && Notifications::isWorkingCopyVerb($a->verb)) {
                continue;
            }
            $out[] = new ActivityItemVM($a->actorName !== '' ? $a->actorName : 'Someone', self::activityText($a), fmt_when($a->createdAt));
        }
        return $out;
    }

    public static function activityText(Activity $a): string
    {
        $d = $a->data;
        $str = static fn (string $k): string => isset($d[$k]) && is_scalar($d[$k]) ? (string) $d[$k] : '';
        $reason = $str('reason') !== '' ? ': ' . $str('reason') : '';
        return match ($a->verb) {
            'job_created' => 'created the job ' . $str('job_number'),
            'brief_sent' => 'sent v' . $str('version') . ' to Traffic',
            'brief_updated' => 'sent update v' . $str('version') . ($str('note') !== '' ? ': ' . $str('note') : ''),
            'brief_recalled' => 'recalled the brief to draft',
            'assigned_to_job' => ($str('claimed') === '1' ? 'took the AM slot' : 'assigned ' . ($str('user_name') !== '' ? $str('user_name') : 'someone') . ' as ' . $str('role')),
            'unassigned_from_job' => 'removed ' . ($str('user_name') !== '' ? $str('user_name') : 'someone') . ' from ' . $str('role'),
            'job_waiting' => 'put the job on waiting' . ($str('waiting_on') !== '' ? ' (' . str_replace('_', ' ', $str('waiting_on')) . ')' : '') . $reason,
            'job_on_hold' => 'put the job on hold' . $reason,
            'job_resumed' => 'resumed the job',
            'job_cancelled' => 'cancelled the job' . $reason,
            'job_archived' => 'archived the job',
            'job_start' => 'started work',
            'job_done' => 'marked the job done',
            'deliverable_cancelled' => 'cancelled ' . (isset($d['asset_ids']) && is_array($d['asset_ids']) ? count($d['asset_ids']) : 0) . ' unstarted assets',
            'started_asset_conflict' => 'kept started assets: ' . $str('message'),
            default => str_replace('_', ' ', $a->verb),
        };
    }
}

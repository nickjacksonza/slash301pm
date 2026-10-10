<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\BoardMoves;
use App\Domain\JobAction;
use App\Domain\JobQuery;
use App\Domain\Notifications;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Signals\SignalInput;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\TransitionRequest;
use App\Domain\Types\User;
use App\Domain\WaitingOn;
use App\Http\Deps;
// Social publishing
use App\Domain\SocialPolicy;
use App\Domain\Types\JobAccess;
use App\Domain\Types\Team;
use App\View\ui\SelectOption;
use App\View\VM\SocialSlotVM;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\Store\Ids;
use App\View\VM\JobSheetVM;
use App\View\VM\JobsPageVM;

/**
 * The board and the stage moves it makes: GET /jobs/board, GET
 * /jobs/board/columns (filters changed), POST /jobs/{id}/move (drag and
 * drop, "Move to...", sheet actions) and GET /jobs/{id}/sheet.
 *
 * A move maps (stage now, move.to) to one named JobAction (BoardMoves), so
 * draft -> briefed only happens through the brief's Send flow; Policy and
 * row_version are checked; the answer always re-renders what the page shows
 * (both board columns, or the grid row, plus the sheet when the move came
 * from it) from the database, so a refused move snaps back.
 */
final class JobBoardHandlers
{
    public static function page(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        [$q, $viewId] = SavedViewHandlers::resolve($r, $d, $u, JobQuery::SCREEN_BOARD);
        $res = JobsView::search($d, $u, $q);
        $vm = new JobsPageVM(JobQuery::SCREEN_BOARD, $q->toSignalState(), JobsView::filters(JobQuery::SCREEN_BOARD, $d),
            SavedViewHandlers::menu($d, $u, JobQuery::SCREEN_BOARD, $q, $viewId), null, JobsView::board($res, $q, $u, $d->clock->now()),
            url('/jobs'), url('/jobs/board'));
        return Shell::page($r, $d, 'Board', 'board', page_jobs_board($vm));
    }

    /** GET /jobs/board/columns: patch #board-columns and #views-menu. */
    public static function columns(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $q = JobQuery::fromState(SignalInput::obj($r->signals(), 'q'), JobQuery::SCREEN_BOARD);
        $res = JobsView::search($d, $u, $q);
        return Response::events(
            PatchElements::html(partial_board_columns(JobsView::board($res, $q, $u, $d->clock->now()))),
            PatchElements::html(partial_views_menu(SavedViewHandlers::menu($d, $u, JobQuery::SCREEN_BOARD, $q, ''))),
        );
    }

    /** POST /jobs/{id}/move: move.to, move.row_version, move.reason, move.waiting_on, move.page, move.sheet, plus q.*. */
    public static function move(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $s = $r->signals();
        $m = SignalInput::obj($s, 'move');
        $page = SignalInput::str($m, 'page') === JobQuery::SCREEN_BOARD ? JobQuery::SCREEN_BOARD : JobQuery::SCREEN_JOBS;
        $q = JobQuery::fromState(SignalInput::obj($s, 'q'), $page);
        $withSheet = SignalInput::bool($m, 'sheet');
        $to = Stage::tryFrom(SignalInput::str($m, 'to'));
        $job = $d->jobs->get($r->pathValue('id'));
        $access = $job === null ? null : $d->jobs->access($job->id);
        if ($job === null || $access === null || !Policy::canViewJob($u, $access)->allowed) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $from = $job->stage;
        $reply = static fn (Toast $t, ?Stage $target = null): Response => self::reply($d, $u, $q, $job->id, [$from, $target ?? $to ?? $from], $withSheet, $t);
        if ($to === null) {
            return $reply(Toast::error('Unknown stage.'), $from);
        }
        if (SignalInput::int($m, 'row_version', -1) !== $job->rowVersion) {
            return $reply(Toast::warn('This job was changed by someone else. The board now shows the latest; try the move again.'));
        }
        $action = BoardMoves::actionFor($from, $to, $job->resumeStage);
        if ($action === null) {
            return $reply(Toast::error(BoardMoves::refusal($from, $to, $job->resumeStage)));
        }
        // Social publishing: the Social stages follow the posts on /social, never a drag.
        if (in_array($action, [JobAction::ReadyToSchedule, JobAction::Schedule, JobAction::GoLive, JobAction::SocialStepBack], true)) {
            return $reply(Toast::error('The Social stages follow the posts. Set Ready to schedule, Scheduled and Live on the Social page.')
                ->withLink(url('/social/jobs/' . rawurlencode($job->id)), 'Open in Social'));
        }
        if ($action === JobAction::Send) {
            return $reply(Toast::error('A draft goes to Traffic through Send, which checks the brief first.')
                ->withLink(url('/jobs/' . rawurlencode($job->id) . '/brief'), 'Open the brief'));
        }
        $dec = Policy::canTransition($u, $access, $action);
        if (!$dec->allowed) {
            return $reply(Toast::error($dec->reason));
        }
        $req = new TransitionRequest($action, WaitingOn::tryFrom(SignalInput::str($m, 'waiting_on')), trim(SignalInput::str($m, 'reason')));
        $o = Transitions::plan($from, $job->resumeStage, $req, $access->anyAssetStarted);
        if (!$o->ok() || $o->to === null) {
            return $reply(Toast::error($o->errors->first()));
        }
        $team = $d->assignments->team($job->id);
        $verb = Notifications::verbFor($action);
        $data = ['recipients' => Notifications::recipients($verb, $team, $access->assetAssigneeIds, $u->id), 'via' => 'board'];
        if ($action === JobAction::Wait && $o->waitingOn === WaitingOn::Am) {
            // N23 job_waiting_on_you: the AM slot holder, or the brief creator (as JobHandlers::transition).
            $am = $team->userFor(Role::AM) ?? $access->creatorId;
            $data['waiting_on_user'] = $am;
            $data['recipients'] = $am !== null && $am !== $u->id ? [$am] : [];
        }
        if (!$d->jobs->applyTransition($job, $o, $u->id, $d->clock->now(), $verb, $data)) {
            return $reply(Toast::warn('This job was changed by someone else. The board now shows the latest; try the move again.'));
        }
        return $reply(Toast::ok($job->jobNumber . ': ' . $from->label() . ' to ' . $o->to->label() . '.'), $o->to);
    }

    /** GET /jobs/{id}/sheet: summary, team, deliverables, sent version, actions, activity. */
    public static function sheet(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $vm = self::sheetVm($d, $u, $r->pathValue('id'));
        if ($vm === null) {
            return $r->isDatastar() ? Response::events(Toast::error('This job no longer exists.')) : Response::notFound();
        }
        if (!$r->isDatastar()) {
            return Response::redirect(url('/jobs/' . rawurlencode($vm->id) . '/brief'));
        }
        return Response::events(PatchElements::html(partial_job_sheet($vm)));
    }

    // ---- helpers -------------------------------------------------------------------

    /**
     * Re-render from the database what the page shows of this job, plus a toast:
     * board: the from and to columns that are visible; grid: the row; and the
     * sheet when the move came from it. All outer patches by id (html transport safe).
     * @param list<Stage> $stages
     */
    private static function reply(Deps $d, User $u, JobQuery $q, string $jobId, array $stages, bool $withSheet, Toast $toast): Response
    {
        $events = [];
        if ($q->screen === JobQuery::SCREEN_BOARD) {
            $show = [];
            foreach ($stages as $s) {
                if ($q->hasStage($s) && !in_array($s, $show, true)) {
                    $show[] = $s;
                }
            }
            if ($show !== []) {
                $sub = $q->withStages($show);
                $res = JobsView::search($d, $u, $sub);
                $now = $d->clock->now();
                $by = [];
                foreach ($res->rows as $row) {
                    $by[$row->stage->value][] = $row;
                }
                foreach ($sub->stages as $s) {
                    $events[] = PatchElements::html(partial_board_column(JobsView::column($s, $by[$s->value] ?? [], $res, $u, $now)));
                }
            }
        } else {
            $one = JobsView::one($d, $u, $jobId);
            if ($one !== null) {
                $row = $one->rows[0];
                $events[] = PatchElements::html(partial_job_row(JobsView::row($row, $one->team($row->id), $u, $d->clock->now()), JobsView::columns($q, $u)));
            }
        }
        if ($withSheet) {
            $vm = self::sheetVm($d, $u, $jobId);
            if ($vm !== null) {
                $events[] = PatchElements::html(partial_job_sheet($vm));
            }
        }
        $events[] = $toast;
        return Response::events(...$events);
    }

    // Social publishing: the Social slot picker on the sheet once the brief is sent.
    private static function socialSlot(Deps $d, User $u, JobAccess $a, Team $team, bool $sent): ?SocialSlotVM
    {
        if (!$sent || !SocialPolicy::canSetSocialSlot($u, $a)->allowed) {
            return null;
        }
        $h = $team->holder(Role::Social);
        $options = [new SelectOption('', 'Nobody')];
        foreach ($d->users->listActiveByRole(Role::Social) as $p) {
            $options[] = new SelectOption($p->id, $p->name);
        }
        return new SocialSlotVM($a->jobId, $h !== null ? $h->userId : '', $h !== null ? $h->userName : '', $options, true);
    }

    public static function sheetVm(Deps $d, User $u, string $jobId): ?JobSheetVM
    {
        $res = JobsView::one($d, $u, $jobId);
        if ($res === null) {
            return null;
        }
        $row = $res->rows[0];
        $team = $res->team($row->id);
        $a = $row->access($team);
        $now = $d->clock->now();
        $people = [];
        foreach (Role::ordered() as $role) {
            $h = $team->holder($role);
            if ($h !== null) {
                $people[] = [$role->value, $h->userName];
            }
        }
        $brief = $d->briefs->getByJob($row->id);
        $latest = $brief !== null && $brief->isSent() ? $d->briefs->latestVersion($brief->id) : null;
        // Owners see the working copy's deliverables; everyone else the last sent version's.
        $lines = $brief === null ? [] : ($row->workingCopy || $latest === null ? $d->briefAssets->listByBrief($brief->id) : $latest->snapshot->lines);
        $deliverables = [];
        foreach ($lines as $l) {
            $deliverables[] = $l->qty . ' x ' . $l->label . ($l->channel !== '' ? ', ' . $l->channel : '') . ($l->sizeFormat !== '' ? ' (' . $l->sizeFormat . ')' : '');
        }
        $id = rawurlencode($row->id);
        return new JobSheetVM(
            $row->id, $row->jobNumber, $row->title !== '' ? $row->title : '(untitled)', $row->stage, JobsView::waitingText($row), $row->brandName, $row->campaignName,
            fmt_date($row->dueDate), JobsView::dueBadge($row->dueDate, $row->stage, $now),
            Policy::canViewHours($u, $a)->allowed ? fmt_num($row->hoursEstimate) : null,
            Policy::canViewBudget($u, $a)->allowed ? fmt_zar($row->budget) : null,
            $people, $deliverables,
            $row->briefSent ? 'Sent ' . ($latest !== null ? $latest->version->label() . ' on ' . fmt_when($latest->createdAt) : JobsView::versionLabel($row)) : 'Draft, not sent yet',
            $latest !== null ? $latest->note : '',
            $latest !== null ? url('/jobs/' . $id . '/brief/versions/' . rawurlencode($latest->version->format())) : '',
            url('/jobs/' . $id . '/brief'), $row->workingCopy && $row->hasUnsentChanges,
            BriefView::activity($d->activity->listForJob($row->id, 12), !$row->workingCopy), JobsView::moves($u, $a, $row), $row->rowVersion, Ids::new(), url('/jobs/' . $id . '/move'),
            self::socialSlot($d, $u, $a, $team, $row->briefSent),
        );
    }
}

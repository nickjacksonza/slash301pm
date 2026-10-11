<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Decision;
use App\Domain\GridField;
use App\Domain\JobQuery;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Signals\BriefSignals;
use App\Domain\Signals\SignalInput;
use App\Domain\Transitions;
use App\Domain\Types\JobGridRow;
use App\Domain\Types\JobSearchResult;
use App\Domain\Types\User;
use App\Domain\WaitingOn;
use App\Http\BriefState;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\ui\SelectOption;
use App\View\VM\GridCellEditorVM;
use App\View\VM\JobsPageVM;

/**
 * The job grid: GET /jobs (page), GET /jobs/rows (filters changed),
 * GET /jobs/{id}/row (cancel an edit), GET /jobs/{id}/cells/{field}/edit
 * (the editor cell), PATCH /jobs/{id}/fields/{field} (save one cell:
 * whitelist, Policy, row_version, then the row and a toast).
 */
final class JobGridHandlers
{
    public static function page(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        [$q, $viewId] = SavedViewHandlers::resolve($r, $d, $u, JobQuery::SCREEN_JOBS);
        $res = JobsView::search($d, $u, $q);
        $vm = new JobsPageVM(JobQuery::SCREEN_JOBS, $q->toSignalState(), JobsView::filters(JobQuery::SCREEN_JOBS, $d, $u),
            SavedViewHandlers::menu($d, $u, JobQuery::SCREEN_JOBS, $q, $viewId), JobsView::body($res, $q, $u, $d->clock->now()), null,
            url('/jobs'), url('/jobs/board'));
        return Shell::page($r, $d, 'Jobs', 'jobs', page_jobs_grid($vm));
    }

    /** GET /jobs/rows: q.* changed; patch #jobs-body and #views-menu (the active view may change). */
    public static function rows(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $q = JobQuery::fromState(SignalInput::obj($r->signals(), 'q'), JobQuery::SCREEN_JOBS);
        $res = JobsView::search($d, $u, $q);
        return Response::events(
            PatchElements::html(partial_jobs_body(JobsView::body($res, $q, $u, $d->clock->now()))),
            PatchElements::html(partial_views_menu(SavedViewHandlers::menu($d, $u, JobQuery::SCREEN_JOBS, $q, ''))),
        );
    }

    /** GET /jobs/{id}/row: the display row (Escape in an editor). */
    public static function row(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $res = JobsView::one($d, $u, $r->pathValue('id'));
        if ($res === null) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        return Response::events(PatchElements::html(self::rowHtml($res, $u, $r, $d)));
    }

    public static function editCell(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $field = GridField::tryFrom($r->pathValue('field'));
        if ($field === null) {
            return Response::events(Toast::error('That cell cannot be edited here.'));
        }
        $res = JobsView::one($d, $u, $r->pathValue('id'));
        if ($res === null) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $row = $res->rows[0];
        $team = $res->team($row->id);
        $dec = self::allowed($u, $row, $res, $field);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $options = [];
        $kind = 'text';
        $value = '';
        $waitingOn = '';
        switch ($field) {
            case GridField::Title:
                $value = $row->title;
                break;
            case GridField::DueDate:
                $kind = 'date';
                $value = (string) $row->dueDate;
                break;
            case GridField::HoursEstimate:
                $kind = 'number';
                $value = fmt_num($row->hoursEstimate);
                break;
            case GridField::CampaignId:
                $kind = 'select';
                $value = (string) $row->campaignId;
                $list = $row->brandId !== null ? $d->campaigns->listByBrand($row->brandId) : $d->campaigns->listAll();
                foreach ($list as $c) {
                    $options[] = new SelectOption($c->id, $c->name);
                }
                break;
            case GridField::Am:
            case GridField::Traffic:
                $kind = 'select';
                $slot = $field->slot() ?? Role::Traffic;
                $value = (string) $team->userFor($slot);
                $options[] = new SelectOption('', 'Nobody');
                foreach ($d->users->listActiveByRole($slot) as $p) {
                    $options[] = new SelectOption($p->id, $p->name);
                }
                break;
            case GridField::WaitingOn:
            case GridField::WaitingReason:
                $kind = 'waiting';
                $value = $row->waitingReason;
                $waitingOn = $row->waitingOn !== null ? $row->waitingOn->value : '';
                foreach (WaitingOn::cases() as $w) {
                    $options[] = new SelectOption($w->value, $w->label());
                }
                break;
        }
        $id = rawurlencode($row->id);
        $vm = new GridCellEditorVM('cell-' . $row->id . '-' . $field->column()->value, $field->value, $field->label(), $kind, $value, $options, $waitingOn,
            $row->rowVersion, $row->briefRowVersion, url('/jobs/' . $id . '/fields/' . $field->value), url('/jobs/' . $id . '/row'));
        return Response::events(PatchElements::html(partial_grid_cell_editor($vm)));
    }

    /** PATCH /jobs/{id}/fields/{field}: edit.value, edit.row_version, edit.brief_rv (brief fields), edit.waiting_on (waiting). */
    public static function updateField(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $field = GridField::tryFrom($r->pathValue('field'));
        if ($field === null) {
            return Response::events(Toast::error('That cell cannot be edited here.'));
        }
        $res = JobsView::one($d, $u, $r->pathValue('id'));
        if ($res === null) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $row = $res->rows[0];
        $dec = self::allowed($u, $row, $res, $field);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $e = SignalInput::obj($r->signals(), 'edit');
        $value = SignalInput::str($e, 'value');
        $stale = SignalInput::int($e, 'row_version', -1) !== $row->rowVersion
            || ($field->isBriefField() && SignalInput::int($e, 'brief_rv', -1) !== $row->briefRowVersion);
        if ($stale) {
            return self::conflict($res, $u, $r, $d);
        }
        $now = $d->clock->now();
        $msg = 'Saved.';
        if ($field->isBriefField()) {
            $s = BriefState::load($d, $row->id);
            if ($s === null) {
                return Response::events(Toast::error('This job has no brief.'));
            }
            $in = BriefSignals::fromSignals(['brief' => [$field->briefKey() => $value]]);
            if (!$in->errors->isEmpty()) {
                return Response::events(Toast::error($in->errors->first()));
            }
            $patch = $in->patch;
            if ($field === GridField::CampaignId) {
                $c = $d->campaigns->get((string) $patch->campaignId);
                if ($c === null || ($s->campaign !== null && $c->brandId !== $s->campaign->brandId)) {
                    return Response::events(Toast::error('Pick a campaign of the same brand.'));
                }
            }
            if (!$d->briefs->save($s->brief, $patch, $s->baseline, $u->id, $now)) {
                return self::conflict(JobsView::one($d, $u, $row->id) ?? $res, $u, $r, $d);
            }
            BriefHandlers::reconcile($d, $row->id);
            if ($s->brief->isSent()) {
                $msg = 'Saved to the working copy. The team sees it after you send an update.';
            }
        } elseif ($field->isWaitingField()) {
            $on = $field === GridField::WaitingOn ? WaitingOn::tryFrom(trim($value)) : (WaitingOn::tryFrom(SignalInput::str($e, 'waiting_on')) ?? $row->waitingOn);
            $reason = $field === GridField::WaitingReason ? trim($value) : $row->waitingReason;
            if ($on === null) {
                return Response::events(Toast::error('Choose who the job is waiting on.'));
            }
            if ($reason === '') {
                return Response::events(Toast::error('Say what the job is waiting for.'));
            }
            if (mb_strlen($reason) > Transitions::MAX_REASON || SignalInput::hasControlChars($reason, false)) {
                return Response::events(Toast::error('Keep the reason under 1000 characters, on one line.'));
            }
            if (!$d->jobFields->setWaiting($row->id, $row->rowVersion, $on, $reason, $u->id, $now)) {
                return self::conflict(JobsView::one($d, $u, $row->id) ?? $res, $u, $r, $d);
            }
        } else {
            $slot = $field->slot() ?? Role::Traffic;
            $who = trim($value);
            if ($who === '') {
                $d->assignments->unset($row->id, $slot, $u->id, $now);
            } else {
                $err = $d->assignments->set($row->id, $slot, $who, $u->id, $now);
                if ($err !== '') {
                    return Response::events(Toast::error($err));
                }
            }
        }
        $fresh = JobsView::one($d, $u, $row->id);
        if ($fresh === null) {
            return Response::events(Toast::ok($msg));
        }
        return Response::events(PatchElements::html(self::rowHtml($fresh, $u, $r, $d)), Toast::ok($msg));
    }

    // ---- helpers -------------------------------------------------------------------

    /** Field whitelist plus Policy (hours also need view_hours). */
    private static function allowed(User $u, JobGridRow $row, JobSearchResult $res, GridField $f): Decision
    {
        $a = $row->access($res->team($row->id));
        if ($f === GridField::HoursEstimate && !Policy::canViewHours($u, $a)->allowed) {
            return Decision::deny('Hours are not shown to your role.');
        }
        return Policy::canEditJobField($u, $a, $f);
    }

    /** The row as the grid shows it, with the columns from q.cols. */
    public static function rowHtml(JobSearchResult $res, User $u, Request $r, Deps $d): string
    {
        $q = JobQuery::fromState(SignalInput::obj($r->signals(), 'q'), JobQuery::SCREEN_JOBS);
        $row = $res->rows[0];
        return partial_job_row(JobsView::row($row, $res->team($row->id), $u, $d->clock->now()), JobsView::columns($q, $u));
    }

    private static function conflict(JobSearchResult $fresh, User $u, Request $r, Deps $d): Response
    {
        return Response::events(
            PatchElements::html(self::rowHtml($fresh, $u, $r, $d)),
            Toast::warn('This job was changed by someone else. The row now shows the latest; make your edit again.'),
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\JobAction;
use App\Domain\Notifications;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Signals\TransitionSignals;
use App\Domain\Transitions;
use App\Domain\WaitingOn;
use App\Http\Deps;
use App\Http\Redirect;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;

/** POST /jobs/{id}/claim-am and POST /jobs/{id}/transition (recall, wait, hold, resume, cancel, archive). */
final class JobHandlers
{
    public static function claimAm(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $access = $d->jobs->access($r->pathValue('id'));
        if ($access === null) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $dec = Policy::canClaimAm($u, $access);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        if (!$d->jobs->claimAm($access->jobId, $u->id, $d->clock->now())) {
            return Response::events(Toast::warn('Someone took the AM slot a moment ago.'));
        }
        return Response::navigate($r->isDatastar(), url('/jobs/' . rawurlencode($access->jobId) . '/brief'));
    }

    public static function transition(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $job = $d->jobs->get($r->pathValue('id'));
        $access = $job === null ? null : $d->jobs->access($job->id);
        if ($job === null || $access === null) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $req = TransitionSignals::fromSignals($r->signals());
        if ($req === null) {
            return Response::events(Toast::error('Unknown action.'));
        }
        $dec = Policy::canTransition($u, $access, $req->action);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $o = Transitions::plan($job->stage, $job->resumeStage, $req, $access->anyAssetStarted);
        if (!$o->ok()) {
            return Response::events(Toast::error($o->errors->first()));
        }
        $team = $d->assignments->team($job->id);
        $verb = Notifications::verbFor($req->action);
        $recipients = Notifications::recipients($verb, $team, $access->assetAssigneeIds, $u->id);
        $data = ['recipients' => $recipients];
        if ($req->action === JobAction::Wait && $o->waitingOn === WaitingOn::Am) {
            // N23 job_waiting_on_you: the AM slot holder, or the brief creator when there is none.
            $am = $team->userFor(Role::AM) ?? $access->creatorId;
            $data['waiting_on_user'] = $am;
            $data['recipients'] = $am !== null && $am !== $u->id ? [$am] : [];
        }
        if (!$d->jobs->applyTransition($job, $o, $u->id, $d->clock->now(), $verb, $data)) {
            return Response::events(Toast::warn('The job changed a moment ago. Reload the page to see the latest.'));
        }
        return Response::events(new Redirect(url('/jobs/' . rawurlencode($job->id) . '/brief')));
    }
}

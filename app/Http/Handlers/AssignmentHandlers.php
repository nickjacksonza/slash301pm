<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Policy;
use App\Domain\Role;
// Social publishing
use App\Domain\SocialPolicy;
use App\Domain\Signals\SignalInput;
use App\Domain\Types\Team;
use App\Http\BriefState;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;

/** POST /jobs/{id}/assignments/{role}: team_<role>.value is a user id, or '' to clear the slot. */
final class AssignmentHandlers
{
    public static function set(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $role = null;
        foreach (Team::slotRoles() as $candidate) {
            if (strtolower($candidate->value) === $r->pathValue('role')) {
                $role = $candidate;
            }
        }
        if ($role === null) {
            return Response::events(Toast::error('Unknown role.'));
        }
        $s = BriefState::load($d, $r->pathValue('id'));
        if ($s === null || !Policy::canViewJob($u, $s->access)->allowed) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        // Social publishing: the Social slot also opens after the send (SocialPolicy).
        $dec = $role === Role::Social ? SocialPolicy::canSetSocialSlot($u, $s->access) : Policy::canAssign($u, $s->access, $role);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        // Social publishing: the job sheet's Social picker sends sheet_social.value and gets the sheet back.
        $fromSheet = $role === Role::Social && SignalInput::has($r->signals(), 'sheet_social');
        $value = trim(SignalInput::str(SignalInput::obj($r->signals(), $fromSheet ? 'sheet_social' : 'team_' . strtolower($role->value)), 'value'));
        $now = $d->clock->now();
        $toast = '';
        if ($value === '') {
            $d->assignments->unset($s->job->id, $role, $u->id, $now);
        } else {
            $problem = $d->assignments->set($s->job->id, $role, $value, $u->id, $now);
            if ($problem !== '') {
                $toast = $problem;
            }
        }
        if ($fromSheet) {
            $sheet = JobBoardHandlers::sheetVm($d, $u, $s->job->id);
            $done = $toast !== '' ? Toast::error($toast) : Toast::ok($value === '' ? 'Social slot cleared.' : 'Social assigned. They have been told.');
            return $sheet === null ? Response::events($done) : Response::events(PatchElements::html(partial_job_sheet($sheet)), $done);
        }
        $fresh = BriefHandlers::reconcile($d, $s->job->id);
        $events = [
            PatchElements::html(partial_brief_team($fresh->job->id, BriefView::team($fresh, $u, $d), $fresh->brief->isSent())),
            PatchElements::html(partial_brief_rail(BriefView::rail($fresh, $u, $d))),
        ];
        if ($toast !== '') {
            $events[] = Toast::error($toast);
        }
        return Response::events(...$events);
    }
}

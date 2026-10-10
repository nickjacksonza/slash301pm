<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Policy;
use App\Domain\Role;
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
        $dec = Policy::canAssign($u, $s->access, $role);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $value = trim(SignalInput::str(SignalInput::obj($r->signals(), 'team_' . strtolower($role->value)), 'value'));
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

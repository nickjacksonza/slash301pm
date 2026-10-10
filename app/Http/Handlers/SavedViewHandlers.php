<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\JobQuery;
use App\Domain\Policy;
use App\Domain\SavedViews;
use App\Domain\Signals\SignalInput;
use App\Domain\Types\SavedView;
use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\VM\ViewsMenuVM;

/**
 * Saved views of the grid and board: GET /views (the menu), POST /views
 * (save the current q.* as a view), PATCH /views/{id} (vedit.op: update,
 * default, undefault, share, unshare, rename), DELETE /views/{id}. The owner
 * is always the session user. Answers patch #views-menu plus a toast.
 */
final class SavedViewHandlers
{
    /** GET /views?screen=jobs|board: the menu (Datastar), or back to the grid. */
    public static function list(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $screen = self::screen($r->query('screen'));
        if (!$r->isDatastar()) {
            return Response::redirect(url($screen === JobQuery::SCREEN_BOARD ? '/jobs/board' : '/jobs'));
        }
        $q = JobQuery::fromState(SignalInput::obj($r->signals(), 'q'), $screen);
        return Response::events(PatchElements::html(partial_views_menu(self::menu($d, $u, $screen, $q, ''))));
    }

    /** POST /views: view.name, view.shared, view.default, view.screen and the current q.*. */
    public static function create(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $s = $r->signals();
        $v = SignalInput::obj($s, 'view');
        $screen = self::screen(SignalInput::str($v, 'screen'));
        [$name, $err] = SavedViews::cleanName(SignalInput::str($v, 'name'));
        if ($err !== '') {
            return Response::events(Toast::error($err));
        }
        $shared = SignalInput::bool($v, 'shared');
        if ($shared) {
            $dec = Policy::canShareView($u);
            if (!$dec->allowed) {
                return Response::events(Toast::error($dec->reason));
            }
        }
        if ($d->savedViews->countFor($u->id) >= SavedViews::MAX_PER_USER) {
            return Response::events(Toast::error('You have 50 saved views. Delete one first.'));
        }
        $q = JobQuery::fromState(SignalInput::obj($s, 'q'), $screen);
        $id = $d->savedViews->create($u->id, $screen, $name, $q->toJson(), $shared, SignalInput::bool($v, 'default'), $d->clock->now());
        return Response::events(PatchElements::html(partial_views_menu(self::menu($d, $u, $screen, $q, $id))), Toast::ok('Saved the view "' . $name . '".'));
    }

    /** PATCH /views/{id}: one vedit.op. */
    public static function update(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $view = $d->savedViews->get($r->pathValue('id'));
        if ($view === null) {
            return Response::events(Toast::error('That view no longer exists.'));
        }
        $dec = Policy::canManageView($u, $view);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $s = $r->signals();
        $e = SignalInput::obj($s, 'vedit');
        $op = SignalInput::str($e, 'op');
        $q = JobQuery::fromState(SignalInput::obj($s, 'q'), $view->screen);
        $name = $view->name;
        $shared = $view->isShared;
        $default = $view->isDefault;
        $state = null;
        $msg = '';
        switch ($op) {
            case 'update':
                $state = $q->toJson();
                $msg = 'Saved the current filters into "' . $name . '".';
                break;
            case 'default':
            case 'undefault':
                if ($view->ownerId !== $u->id) {
                    return Response::events(Toast::error('Only your own views can be your default.'));
                }
                $default = $op === 'default';
                $msg = $default ? '"' . $name . '" now opens by default.' : '"' . $name . '" is no longer your default.';
                break;
            case 'share':
            case 'unshare':
                if ($op === 'share') {
                    $can = Policy::canShareView($u);
                    if (!$can->allowed) {
                        return Response::events(Toast::error($can->reason));
                    }
                }
                $shared = $op === 'share';
                $msg = $shared ? '"' . $name . '" is shared with everyone.' : '"' . $name . '" is private again.';
                break;
            case 'rename':
                [$clean, $err] = SavedViews::cleanName(SignalInput::str($e, 'name'));
                if ($err !== '') {
                    return Response::events(Toast::error($err));
                }
                $name = $clean;
                $msg = 'Renamed to "' . $name . '".';
                break;
            default:
                return Response::events(Toast::error('Unknown view action.'));
        }
        $d->savedViews->update($view, $name, $shared, $default, $state, $d->clock->now());
        return Response::events(PatchElements::html(partial_views_menu(self::menu($d, $u, $view->screen, $q, ''))), Toast::ok($msg));
    }

    /** DELETE /views/{id}. */
    public static function delete(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $view = $d->savedViews->get($r->pathValue('id'));
        if ($view === null) {
            return Response::events(Toast::error('That view no longer exists.'));
        }
        $dec = Policy::canManageView($u, $view);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $d->savedViews->delete($view->id);
        $q = JobQuery::fromState(SignalInput::obj($r->signals(), 'q'), $view->screen);
        return Response::events(PatchElements::html(partial_views_menu(self::menu($d, $u, $view->screen, $q, ''))), Toast::ok('Deleted the view "' . $view->name . '".'));
    }

    // ---- shared with the grid and board pages -------------------------------------

    /**
     * The query a page opens with: ?view=<id> (built-in, own or shared), else
     * the URL filters, else the user's default view, else the built-in fallback.
     * @return array{0:JobQuery,1:string} [query, preferred view id]
     */
    public static function resolve(Request $r, Deps $d, User $u, string $screen): array
    {
        $want = $r->query('view');
        if ($want === 'none') {
            return [JobQuery::defaults($screen), ''];
        }
        if ($want !== '') {
            $v = self::visible($d, $u, $want, $screen);
            if ($v !== null) {
                $q = JobQuery::fromSavedState($v->stateJson, $screen);
                if ($q !== null) {
                    return [$q, $v->id];
                }
            }
        }
        foreach (['stages', 'brand', 'campaign', 'owner', 'assignee', 'role', 'due', 'q', 'sort', 'group', 'cols'] as $k) {
            if (isset($r->query[$k])) {
                return [JobQuery::fromQuery($r->query, $screen), ''];
            }
        }
        $def = $d->savedViews->defaultFor($u->id, $screen);
        $v = $def ?? SavedViews::builtin(SavedViews::fallbackId($u), $screen);
        $q = $v !== null ? JobQuery::fromSavedState($v->stateJson, $screen) : null;
        return [$q ?? JobQuery::defaults($screen), $v !== null ? $v->id : ''];
    }

    /** The menu with the active view: $preferId when it matches the query, else the first view whose state equals it. */
    public static function menu(Deps $d, User $u, string $screen, JobQuery $q, string $preferId): ViewsMenuVM
    {
        $saved = $d->savedViews->listFor($u->id, $screen);
        $all = array_merge(SavedViews::builtins($screen), $saved);
        $json = $q->toJson();
        $activeId = '';
        $activeName = '';
        foreach ($all as $v) {
            $vq = JobQuery::fromSavedState($v->stateJson, $screen);
            if ($vq === null || $vq->toJson() !== $json) {
                continue;
            }
            if ($activeId === '' || $v->id === $preferId) {
                $activeId = $v->id;
                $activeName = $v->name;
            }
        }
        return JobsView::views($screen, $u, $saved, $activeId, $activeName);
    }

    private static function visible(Deps $d, User $u, string $id, string $screen): ?SavedView
    {
        if (str_starts_with($id, 'builtin:')) {
            return SavedViews::builtin($id, $screen);
        }
        $v = $d->savedViews->get($id);
        if ($v === null || $v->screen !== $screen || ($v->ownerId !== $u->id && !$v->isShared)) {
            return null;
        }
        return $v;
    }

    private static function screen(string $s): string
    {
        return $s === JobQuery::SCREEN_BOARD ? JobQuery::SCREEN_BOARD : JobQuery::SCREEN_JOBS;
    }
}

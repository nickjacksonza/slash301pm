<?php
declare(strict_types=1);

use App\Domain\JobQuery;
use App\Domain\Role;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

function sv_create($d, string $userId, string $name, array $query, bool $shared = false, bool $default = false, string $screen = 'jobs', array $extra = []): string
{
    $q = JobQuery::fromQuery($query, $screen)->toSignalState();
    return ts_body(bh_ds($d, bh_session($d, $userId), 'POST', '/views', ['view' => ['name' => $name, 'shared' => $shared, 'default' => $default, 'screen' => $screen] + $extra, 'q' => $q]));
}

function sv_id($d, string $userId, string $name): string
{
    foreach ($d->savedViews->listFor($userId, 'jobs') as $v) {
        if ($v->name === $name) {
            return $v->id;
        }
    }
    throw new TestFailure('no view ' . $name);
}

return [
    'saved views: create, list, load by URL, update, default, delete' => function (): void {
        [$d, $p] = jf_world();
        $body = sv_create($d, $p['am'], 'Merc overdue', ['brand' => 'brand_merc', 'due' => 'overdue', 'sort' => '-due'], false, false, 'jobs', ['owner_id' => $p['am2']]);
        t_contains('Saved the view', $body);
        t_contains('id="views-menu"', $body);
        $id = sv_id($d, $p['am'], 'Merc overdue');
        $v = $d->savedViews->get($id);
        t_eq($p['am'], $v->ownerId, 'owner from the session, never from signals');
        t_eq('jobs', $v->screen);
        $q = JobQuery::fromSavedState($v->stateJson, 'jobs');
        t_eq('brand_merc', $q->brandId);
        t_eq('-due', $q->sortToken());
        // ?view= loads it, and the menu marks it active
        $s = bh_session($d, $p['am']);
        $page = ts_body((ts_app($s))(ts_request('GET', '/jobs', [], '', [], ['view' => $id]), $d));
        t_contains('MERC-001', $page);
        t_not_contains('AURA-002', $page);
        t_true(preg_match('#<a href="/slash301pm/jobs\?view=' . $id . '"[^>]*aria-current="true"#', $page) === 1, 'the loaded view is marked active');
        // update (save current filters into it), make default, the page opens it with no query
        $s2 = bh_ds($d, $s, 'PATCH', '/views/' . $id, ['vedit' => ['op' => 'update'], 'q' => JobQuery::fromQuery(['brand' => 'brand_aura'], 'jobs')->toSignalState()]);
        t_contains('Saved the current filters', ts_body($s2));
        t_eq('brand_aura', JobQuery::fromSavedState($d->savedViews->get($id)->stateJson, 'jobs')->brandId);
        bh_ds($d, $s, 'PATCH', '/views/' . $id, ['vedit' => ['op' => 'default']]);
        t_true($d->savedViews->get($id)->isDefault);
        $page = ts_body((ts_app($s))(ts_request('GET', '/jobs'), $d));
        t_contains('AURA-002', $page, 'default view applied');
        t_not_contains('MERC-001', $page);
        // a second default replaces the first
        sv_create($d, $p['am'], 'Second', ['owner' => 'mine'], false, true);
        t_true(!$d->savedViews->get($id)->isDefault);
        bh_ds($d, $s, 'PATCH', '/views/' . $id, ['vedit' => ['op' => 'rename', 'name' => 'Aura only']]);
        t_eq('Aura only', $d->savedViews->get($id)->name);
        t_contains('Deleted the view', ts_body(bh_ds($d, $s, 'DELETE', '/views/' . $id)));
        t_eq(null, $d->savedViews->get($id));
        t_contains('That view no longer exists', ts_body(bh_ds($d, $s, 'PATCH', '/views/builtin:mine', ['vedit' => ['op' => 'rename', 'name' => 'x']])));
    },
    'saved views: sharing rules and who may change what (table)' => function (): void {
        [$d, $p] = jf_world();
        $pm = ts_user($d, 'pm_pat', Role::PM);
        t_contains('Saved the view', sv_create($d, $p['am'], 'Team board', ['stages' => 'all'], true));
        t_contains('Saved the view', sv_create($d, $p['am'], 'Private', ['owner' => 'mine']));
        $shared = sv_id($d, $p['am'], 'Team board');
        $private = sv_id($d, $p['am'], 'Private');
        // the other AM sees the shared view, not the private one
        $names = array_map(static fn ($v) => $v->name, $d->savedViews->listFor($p['am2'], 'jobs'));
        t_eq(['Team board'], $names);
        $page = ts_body((ts_app(bh_session($d, $p['am2'])))(ts_request('GET', '/jobs', [], '', [], ['view' => $private]), $d));
        t_not_contains('Private', $page, 'a private view id from someone else falls back to the default');
        $cases = [
            ['other AM deletes a shared view', $p['am2'], 'DELETE', $shared, [], 'Only the person who saved this view'],
            ['other AM unshares', $p['am2'], 'PATCH', $shared, ['vedit' => ['op' => 'unshare']], 'Only the person who saved this view'],
            ['other AM renames a private view', $p['am2'], 'PATCH', $private, ['vedit' => ['op' => 'rename', 'name' => 'x']], 'Only the person who saved this view'],
            ['ECD (admin) may not touch a private view', $p['coo'], 'DELETE', $private, [], 'Only the person who saved this view'],
            ['admin cannot make someone else\'s view their default', $p['coo'], 'PATCH', $shared, ['vedit' => ['op' => 'default']], 'Only your own views'],
            ['unknown op', $p['am'], 'PATCH', $private, ['vedit' => ['op' => 'explode']], 'Unknown view action'],
        ];
        foreach ($cases as [$name, $who, $method, $id, $signals, $want]) {
            $before = $d->savedViews->get($id);
            t_contains($want, ts_body(bh_ds($d, bh_session($d, $who), $method, '/views/' . $id, $signals)), $name);
            t_true($before == $d->savedViews->get($id), $name . ': unchanged');
        }
        // the COO (admin) may unshare a shared view
        t_contains('private again', ts_body(bh_ds($d, bh_session($d, $p['coo']), 'PATCH', '/views/' . $shared, ['vedit' => ['op' => 'unshare']])));
        t_true(!$d->savedViews->get($shared)->isShared);
        // Policy::canShareView: a Designer cannot share (checked in the handler too; Designers are outside the beta)
        t_true(!App\Domain\Policy::canShareView($d->users->findById($p['designer']))->allowed);
        t_true(App\Domain\Policy::canShareView($d->users->findById($pm))->allowed, 'PM is a manager role');
        t_contains('Give the view a name', sv_create($d, $p['am'], "  \t ", []));
        t_contains('under 60 characters', sv_create($d, $p['am'], str_repeat('x', 61), []));
    },
    'saved views: board views are separate and the menu follows the filters' => function (): void {
        [$d, $p] = jf_world();
        sv_create($d, $p['am'], 'Board paused', ['stages' => 'waiting,on_hold'], false, false, 'board');
        t_eq([], $d->savedViews->listFor($p['am'], 'jobs'));
        t_eq(1, count($d->savedViews->listFor($p['am'], 'board')));
        $s = bh_session($d, $p['am']);
        // filters equal to a built-in view: the menu names it
        $body = ts_body(bh_ds($d, $s, 'GET', '/jobs/rows', ['q' => JobQuery::fromQuery(['owner' => 'unowned', 'sort' => 'job_number'], 'jobs')->toSignalState()]));
        t_contains('id="views-menu"', $body);
        t_contains('Unowned</span>', $body);
        $body = ts_body(bh_ds($d, $s, 'GET', '/views', ['q' => JobQuery::fromQuery(['stages' => 'waiting,on_hold'], 'board')->toSignalState()]) );
        t_contains('Custom', $body, 'GET /views without ?screen is the grid menu');
        $resp = (ts_app($s))(ts_ds_request('GET', '/views', $s, ['q' => JobQuery::fromQuery(['stages' => 'waiting,on_hold'], 'board')->toSignalState()]), $d);
        t_eq(200, $resp->status());
    },
];

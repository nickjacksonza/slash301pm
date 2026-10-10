<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\JobQuery;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

const JOBS_HOSTILE = '<script>alert("x")</script>\'"&{{x}}</textarea>${1}';

function jh_check(string $where, string $html, int $scripts): void
{
    t_not_contains(JOBS_HOSTILE, $html, "$where: raw hostile string");
    t_not_contains('<script>alert', $html, "$where: script injection");
    t_not_contains('</textarea>\'"', $html, "$where: textarea breakout");
    t_eq($scripts, substr_count(strtolower($html), '<script'), "$where: script tags");
}

/** Every Phase 3 Datastar endpoint with valid input. @return array<string,array{0:string,1:string,2:array}> */
function jh_actions(array $p, array $j, string $viewId): array
{
    $grid = JobQuery::defaults('jobs')->toSignalState();
    $board = JobQuery::defaults('board')->toSignalState();
    return [
        'rows' => ['GET', '/jobs/rows', ['q' => $grid]],
        'rows grouped' => ['GET', '/jobs/rows', ['q' => ['group' => 'campaign'] + $grid]],
        'board columns' => ['GET', '/jobs/board/columns', ['q' => $board]],
        'row' => ['GET', '/jobs/' . $j['mine_today'] . '/row', ['q' => $grid]],
        'editor title' => ['GET', '/jobs/' . $j['mine_today'] . '/cells/title/edit', []],
        'editor campaign' => ['GET', '/jobs/' . $j['mine_today'] . '/cells/campaign_id/edit', []],
        'editor am' => ['GET', '/jobs/' . $j['mine_today'] . '/cells/am/edit', []],
        'editor waiting' => ['GET', '/jobs/' . $j['unowned'] . '/cells/waiting_reason/edit', []],
        'sheet' => ['GET', '/jobs/' . $j['mine_overdue'] . '/sheet', []],
        'views menu' => ['GET', '/views', ['q' => $grid]],
        'create view' => ['POST', '/views', ['view' => ['name' => 'V ' . substr(JOBS_HOSTILE, 0, 40), 'screen' => 'jobs'], 'q' => $grid]],
        'update view' => ['PATCH', '/views/' . $viewId, ['vedit' => ['op' => 'share'], 'q' => $grid]],
        'edit title' => ['PATCH', '/jobs/' . $j['mine_today'] . '/fields/title', ['edit' => ['value' => 'T ' . JOBS_HOSTILE], 'q' => $grid]],
        'edit refused' => ['PATCH', '/jobs/' . $j['other_am'] . '/fields/title', ['edit' => ['value' => 'x'], 'q' => $grid]],
        'move' => ['POST', '/jobs/' . $j['mine_overdue'] . '/move', ['move' => ['to' => 'on_hold', 'reason' => 'R ' . JOBS_HOSTILE, 'page' => 'board', 'sheet' => true], 'q' => $board]],
        'move refused' => ['POST', '/jobs/' . $j['created_no_am'] . '/move', ['move' => ['to' => 'briefed', 'page' => 'board'], 'q' => $board]],
        'delete view' => ['DELETE', '/views/' . $viewId, ['q' => $grid]],
    ];
}

return [
    'jobs http: every new handler renders over SSE and the html transport' => function (): void {
        foreach ([Transport::Sse, Transport::Html] as $t) {
            [$d, $p, , $j] = jf_world();
            $s = bh_session($d, $p['am']);
            $viewId = $d->savedViews->create($p['am'], 'jobs', 'Mine', '{}', false, false, $d->clock->now());
            foreach (['/jobs', '/jobs/board'] as $path) {
                $r = (ts_app($s))(ts_request('GET', $path), $d);
                t_eq(200, $r->status(), $path);
            }
            foreach (jh_actions($p, $j, $viewId) as $name => [$method, $path, $signals]) {
                // fill in the live row versions the page would hold
                if (isset($signals['edit'])) {
                    $id = explode('/', $path)[2];
                    $signals['edit'] += ['row_version' => $d->jobs->get($id)->rowVersion, 'brief_rv' => $d->briefs->getByJob($id)->rowVersion];
                }
                if (isset($signals['move'])) {
                    $signals['move']['row_version'] = $d->jobs->get(explode('/', $path)[2])->rowVersion;
                }
                $resp = bh_ds($d, $s, $method, $path, $signals);
                t_eq(200, $resp->status(), $t->value . ' ' . $name);
                $body = $resp->render($t)->body();
                t_true($body !== '', $t->value . ' ' . $name . ' has a body');
            }
            t_eq('on_hold', $d->jobs->get($j['mine_overdue'])->stage->value, $t->value . ': the move happened');
            t_eq(null, $d->savedViews->get($viewId), $t->value . ': the view was deleted');
        }
    },
    'jobs http: writes without the CSRF token are refused and change nothing (table)' => function (): void {
        [$d, $p, , $j] = jf_world();
        $s = bh_session($d, $p['am']);
        $viewId = $d->savedViews->create($p['am'], 'jobs', 'Mine', '{}', false, false, $d->clock->now());
        $id = $j['mine_today'];
        $rv = $d->jobs->get($id)->rowVersion;
        $cases = [
            ['PATCH', '/jobs/' . $id . '/fields/title', ['edit' => ['value' => 'Hijacked', 'row_version' => $rv, 'brief_rv' => $d->briefs->getByJob($id)->rowVersion]]],
            ['POST', '/jobs/' . $id . '/move', ['move' => ['to' => 'on_hold', 'reason' => 'x', 'row_version' => $rv, 'page' => 'board']]],
            ['POST', '/views', ['view' => ['name' => 'Sneaky', 'screen' => 'jobs']]],
            ['PATCH', '/views/' . $viewId, ['vedit' => ['op' => 'rename', 'name' => 'Sneaky']]],
            ['DELETE', '/views/' . $viewId, []],
        ];
        foreach ($cases as [$method, $path, $signals]) {
            $resp = (ts_app($s))(ts_ds_request($method, $path, $s, $signals, false), $d);
            t_eq(200, $resp->status(), "$method $path");
            t_contains('Error', ts_body($resp), "$method $path: error toast");
        }
        t_eq('Launch email 50% off', $d->jobs->get($id)->title);
        t_eq($rv, $d->jobs->get($id)->rowVersion);
        t_eq('Mine', $d->savedViews->get($viewId)->name);
        t_eq(1, $d->savedViews->countFor($p['am']));
    },
    'jobs http: hostile titles, campaigns, brands, people, reasons and view names are escaped everywhere' => function (): void {
        $h = JOBS_HOSTILE;
        [$d, $p, , $j] = jf_world();
        $d->db->exec('UPDATE brands SET name = :n || id', ['n' => 'Brand ' . $h]);
        $d->db->exec('UPDATE campaigns SET name = :n || id', ['n' => 'Camp ' . $h]);
        $d->db->exec('UPDATE users SET name = :n || username', ['n' => 'Person ' . $h]);
        $d->db->exec('UPDATE jobs SET title = :n', ['n' => 'Job ' . $h]);
        $d->db->exec('UPDATE briefs SET title = :n', ['n' => 'Job ' . $h]);
        $d->db->exec('UPDATE jobs SET waiting_on = \'client\', waiting_reason = :n WHERE stage = \'waiting\'', ['n' => 'Why ' . $h]);
        $d->db->exec("INSERT INTO brief_assets (id, brief_id, job_id, label, qty, channel) SELECT 'ba1', id, job_id, :n, 2, :n FROM briefs WHERE job_id = :j", ['n' => 'Line ' . $h, 'j' => $j['mine_overdue']]);
        $s = bh_session($d, $p['am']);
        $viewId = $d->savedViews->create($p['am'], 'jobs', 'View ' . $h, '{"stages":"open"}', false, false, $d->clock->now());
        $d->savedViews->create($p['am2'], 'jobs', 'Shared ' . $h, '{}', true, false, $d->clock->now());
        bh_ds($d, $s, 'POST', '/jobs/' . $j['mine_today'] . '/move', ['move' => ['to' => 'waiting', 'on' => 'am', 'waiting_on' => 'am', 'reason' => 'Move ' . $h, 'page' => 'board', 'row_version' => $d->jobs->get($j['mine_today'])->rowVersion], 'q' => JobQuery::defaults('board')->toSignalState()]);
        t_eq('waiting', $d->jobs->get($j['mine_today'])->stage->value);
        foreach ([['/jobs', [], 4], ['/jobs', ['group' => 'campaign', 'q' => 'Job'], 4], ['/jobs', ['view' => $viewId], 4], ['/jobs/board', [], 3], ['/jobs/board', ['stages' => 'all'], 3]] as [$path, $query, $scripts]) {
            $html = ts_body((ts_app($s))(ts_request('GET', $path, [], '', [], $query), $d));
            t_contains('Job &lt;script&gt;', $html, $path . ' shows the title, escaped');
            jh_check($path . '?' . http_build_query($query), $html, $scripts);
        }
        foreach (jh_actions($p, $j, $viewId) as $name => [$method, $path, $signals]) {
            if (isset($signals['edit'])) {
                $id = explode('/', $path)[2];
                $signals['edit'] += ['row_version' => $d->jobs->get($id)->rowVersion, 'brief_rv' => $d->briefs->getByJob($id)->rowVersion];
            }
            if (isset($signals['move'])) {
                $signals['move']['row_version'] = $d->jobs->get(explode('/', $path)[2])->rowVersion;
            }
            foreach ([Transport::Sse, Transport::Html] as $t) {
                jh_check($name . ' (' . $t->value . ')', bh_ds($d, $s, $method, $path, $signals)->render($t)->body(), 0);
            }
        }
        jh_check('sheet with hostile deliverables', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $j['mine_overdue'] . '/sheet')), 0);
        t_contains('Line &lt;script&gt;', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $j['mine_overdue'] . '/sheet')));
        jh_check('waiting editor', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $j['mine_today'] . '/cells/waiting_reason/edit')), 0);
    },
];

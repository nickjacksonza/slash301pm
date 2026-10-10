<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Http\Kernel;

require_once dirname(__DIR__, 2) . '/support/social_fx.php';

/** A scheduled Instagram post on a fresh world: [deps, people, job id, publication id]. */
function sh_scheduled(): array
{
    [$d, $p, $jobId] = sx_world(1);
    $sol = bh_session($d, $p['social']);
    [$a1] = sx_assets($d, $jobId);
    $pid = sx_add($d, $sol, $a1, 'instagram');
    sx_tick_all($d, $sol, $pid);
    sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/ready', sx_sig($d, $pid)));
    sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/scheduled', sx_sig($d, $pid, ['scheduled_at' => '2026-10-12T09:30'])));
    return [$d, $p, $jobId, $pid];
}

return [
    'social http: the AM cannot set Live (Q23, SOC-6) and sees the Policy reason; nothing changes' => function (): void {
        [$d, $p, $jobId, $pid] = sh_scheduled();
        $amy = bh_session($d, $p['am']);
        $body = ts_body(bh_ds($d, $amy, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => 'https://instagram.com/p/x'])));
        t_contains('data-toast="error"', $body);
        t_contains('Only Social, the COO or the ECD', $body);
        t_eq('scheduled', $d->publications->get($pid)->status->value);
        t_eq('scheduled', sx_stage($d, $jobId));
        // Plain (non-Datastar) POST answers 403... after CSRF, which a plain POST fails first: still nothing changes.
        // The AM can read the job page (own job) but gets no controls.
        $page = ts_body((ts_app($amy))(ts_request('GET', '/social/jobs/' . $jobId), $d));
        t_contains('Read only', $page);
        t_not_contains('/live&quot;', $page);
        // Social without a link is refused until a link is entered (SOC-6).
        $sol = bh_session($d, $p['social']);
        t_contains('Paste the live link', ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid))));
        // Another Social user without the slot can read but not write.
        $sam = bh_session($d, $p['social2']);
        t_contains('Social slot', ts_body(bh_ds($d, $sam, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => 'https://instagram.com/p/x']))));
        t_eq('scheduled', $d->publications->get($pid)->status->value);
    },

    'social http: a Designer cannot open the queue, the list or a job; Traffic neither' => function (): void {
        [$d, $p, $jobId, $pid] = sh_scheduled();
        foreach (['designer', 'traffic'] as $who) {
            $s = bh_session($d, $p[$who]);
            t_eq(403, (ts_app($s))(ts_request('GET', '/social'), $d)->status(), "$who page");
            t_eq(403, (ts_app($s))(ts_request('GET', '/social/jobs/' . $jobId), $d)->status(), "$who job");
            t_contains('data-toast="error"', ts_body(bh_ds($d, $s, 'GET', '/social/list', ['sq' => ['tab' => 'scheduled']])), "$who list");
            t_contains('no longer exists', ts_body(bh_ds($d, $s, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => 'https://x.com/1']))));
        }
        t_eq('scheduled', $d->publications->get($pid)->status->value);
        // The nav shows Social to Social and the AM, not to the Designer.
        $kim = bh_session($d, $p['designer']);
        t_not_contains('/slash301pm/social"', ts_body((ts_app($kim))(ts_request('GET', '/today'), $d)));
        t_contains('/slash301pm/social"', ts_body((ts_app(bh_session($d, $p['social'])))(ts_request('GET', '/today'), $d)));
    },

    'social http: CSRF: a write without the token is refused and changes nothing' => function (): void {
        [$d, $p, $jobId, $pid] = sh_scheduled();
        $sol = bh_session($d, $p['social']);
        $req = ts_ds_request('POST', '/social/publications/' . $pid . '/live', $sol, sx_sig($d, $pid, ['live_url' => 'https://x.com/1']), false);
        $body = ts_body((ts_app($sol))($req, $d));
        t_contains('data-toast="error"', $body);
        t_eq('scheduled', $d->publications->get($pid)->status->value);
        $cross = ts_ds_request('POST', '/social/jobs/' . $jobId . '/ready', $sol);
        $cross = new App\Http\Request($cross->method, $cross->path, $cross->query, $cross->headers + ['sec-fetch-site' => 'cross-site'], $cross->body, $cross->form, $cross->remoteAddr, $cross->scheme);
        t_contains('data-toast="error"', ts_body((ts_app($sol))($cross, $d)));
    },

    'social http: every reply is one html-transport patch set (outer by id plus the toast region)' => function (): void {
        [$d, $p, $jobId, $pid] = sh_scheduled();
        $sol = bh_session($d, $p['social']);
        $cases = [
            ['PATCH', '/social/publications/' . $pid . '/schedule', sx_sig($d, $pid, ['scheduled_at' => '2026-10-13T10:00'])],
            ['POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => 'not a url'])],
            ['GET', '/social/list', ['sq' => ['tab' => 'scheduled']]],
            ['POST', '/social/jobs/' . $jobId . '/ready', []],
        ];
        foreach ($cases as [$m, $path, $sig]) {
            $resp = bh_ds($d, $sol, $m, $path, $sig);
            $r = $resp->render(Transport::Html);
            t_eq(200, $r->status, "$m $path");
            t_true(str_starts_with($r->header('Content-Type'), 'text/html'), "$m $path is an html patch");
            t_eq('', $r->header('Datastar-Selector'), "$m $path patches by id");
        }
        t_eq('2026-10-13 10:00', $d->publications->get($pid)->scheduledAt);
    },

    'social http: hostile strings are escaped or refused; budget never appears (SOC-7)' => function (): void {
        [$d, $p, $jobId] = sx_world(1);
        $sol = bh_session($d, $p['social']);
        [$a1] = sx_assets($d, $jobId);
        $evil = '"><img src=x onerror=alert(1)>{{$_csrf}}\'</script>';
        $d->db->exec('UPDATE assets SET name = :n WHERE id = :a', ['n' => $evil, 'a' => $a1]);
        $pid = sx_add($d, $sol, $a1, 'instagram');
        sx_ok(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $pid . '/checklist', sx_sig($d, $pid, ['cl' => ['copy' => true, 'copy_note' => $evil]])));
        t_eq($evil, $d->publications->get($pid)->checklist->entry(App\Domain\ChecklistItem::Copy)->note);
        // Control characters in a note are refused.
        t_contains('one line', ts_body(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $pid . '/checklist', sx_sig($d, $pid, ['cl' => ['link_note' => "a\nb"]]))));
        $pages = [
            ts_body((ts_app($sol))(ts_request('GET', '/social/jobs/' . $jobId), $d)),
            ts_body((ts_app($sol))(ts_request('GET', '/social'), $d)),
            ts_body(bh_ds($d, $sol, 'GET', '/social/list', ['sq' => ['tab' => 'check']])),
            ts_body(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $pid . '/checklist', sx_sig($d, $pid))),
            ts_body((ts_app($sol))(ts_request('GET', '/today'), $d)),
            ts_body((ts_app(bh_session($d, $p['coo'])))(ts_request('GET', '/social/jobs/' . $jobId), $d)),
        ];
        foreach ($pages as $i => $html) {
            t_not_contains('<img src=x', $html, "page $i escapes");
            t_not_contains('</script>\'', $html, "page $i");
            t_not_contains('25 000', $html, "page $i: no budget");
            t_not_contains('25000', $html, "page $i: no budget");
            t_not_contains('Budget', $html, "page $i: no budget label");
        }
        // live_url: javascript:, data:, quotes and spaces are refused; nothing is stored.
        sx_tick_all($d, $sol, $pid);
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/ready', sx_sig($d, $pid)));
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/scheduled', sx_sig($d, $pid, ['scheduled_at' => '2026-10-12T09:30'])));
        foreach (['javascript:alert(1)', ' JavaScript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'https://x.com/"onmouseover="alert(1)', 'vbscript:msgbox(1)'] as $bad) {
            $body = ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => $bad])));
            t_contains('data-toast="error"', $body, $bad);
            t_eq('', $d->publications->get($pid)->liveUrl, $bad);
            $body = ts_body(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $pid . '/live-link', sx_sig($d, $pid, ['live_url' => $bad])));
            t_contains('data-toast="error"', $body, $bad);
            t_eq('', $d->publications->get($pid)->liveUrl, $bad);
        }
        // Unknown platform and a non-hex publication id are refused.
        t_contains('Unknown platform', ts_body(bh_ds($d, $sol, 'POST', '/social/assets/' . $a1 . '/platforms/myspace')));
        t_contains('no longer exists', ts_body(bh_ds($d, $sol, 'PATCH', '/social/publications/x%27%3B/checklist', [])));
    },

    'social http: the board refuses moves into the Social stages and points to /social' => function (): void {
        [$d, $p, $jobId] = sx_world(1);
        $coo = bh_session($d, $p['coo']);
        $q = App\Domain\JobQuery::defaults('board')->toSignalState();
        $body = ts_body(bh_ds($d, $coo, 'POST', '/jobs/' . $jobId . '/move', ['move' => [
            'to' => 'ready_to_schedule', 'row_version' => $d->jobs->get($jobId)->rowVersion, 'reason' => '', 'waiting_on' => '', 'page' => 'board', 'sheet' => false, 'job_id' => $jobId], 'q' => $q]));
        t_contains('Social stages follow the posts', $body);
        t_contains('/slash301pm/social/jobs/' . $jobId, $body);
        t_eq('approved_client', sx_stage($d, $jobId));
        // The sheet offers the Social stage as a link to /social, not a move.
        $sheet = ts_body(bh_ds($d, $coo, 'GET', '/jobs/' . $jobId . '/sheet'));
        t_contains('Ready to schedule (in Social)', $sheet);
    },
];

<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Http\Handlers\BriefHandlers;
use App\Http\Response;

require_once dirname(__DIR__, 2) . '/support/brief_http.php';

return [
    'brief http: AM creates, autosaves (SSE patches), adds a line, assigns Traffic, sends and updates' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $s = bh_session($d, $p['am']);
        $page = (ts_app($s))(ts_request('GET', '/jobs/' . $jobId . '/brief'), $d);
        t_eq(200, $page->status());
        $html = ts_body($page);
        t_contains('id="brief-form"', $html);
        t_contains('data-on:input__debounce.800ms', $html);
        t_contains('Traffic is required', $html);
        $rv = $d->briefs->getByJob($jobId)->rowVersion;
        $auto = bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'Launch v2', 'due_date' => '2026-10-20', 'creative_direction' => 'Bold.', 'row_version' => $rv]]);
        $body = ts_body($auto);
        t_contains('event: datastar-patch-elements', $body);
        t_contains('id="brief-rail"', $body);
        t_contains('id="brief-heading"', $body);
        t_contains('data-signals:brief.row_version="' . ($rv + 1) . '"', $body);
        t_eq('Launch v2', $d->briefs->getByJob($jobId)->title);
        $bad = ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['due_date' => 'tomorrow', 'row_version' => $rv + 1]]));
        t_contains('Dates must be real dates', $bad);
        $add = ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]));
        t_contains('id="brief-deliverables"', $add);
        $line = $d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id)[0];
        $upd = ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . $line->id, ['dl' => ['ln_' . $line->id => ['template_id' => 'social-static', 'label' => 'Hero', 'qty' => 3, 'copy_required' => false]]]));
        t_contains('3 assets on send', $upd);
        $dialog = ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $jobId . '/brief/send'));
        t_contains('Traffic is required', $dialog);
        $refused = ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/send'));
        t_contains('Traffic is required', $refused);
        $team = ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]]));
        t_contains('id="brief-team"', $team);
        $wrong = ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['designer']]]));
        t_contains('Only a Traffic can hold the Traffic slot.', $wrong);
        $sent = ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/send'));
        t_contains('{"_redirect":"/slash301pm/jobs/' . $jobId . '/brief"}', $sent);
        t_eq('briefed', $d->jobs->get($jobId)->stage->value);
        t_eq(3, count($d->assets->listByJob($jobId)));
        // after send: edit the working copy, then send an update
        $rv = $d->briefs->getByJob($jobId)->rowVersion;
        bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['due_date' => '2026-10-27', 'row_version' => $rv]]);
        t_true($d->briefs->getByJob($jobId)->hasUnsentChanges);
        $ud = ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $jobId . '/brief/update'));
        t_contains('Changes since v1.0.0', $ud);
        t_contains('(suggested)', $ud);
        t_contains('Write a short change note', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'minor', 'note' => '']])));
        $ok = ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'minor', 'note' => 'New date']]));
        t_contains('"_redirect":', $ok);
        t_eq('1.1.0', $d->briefs->getByJob($jobId)->version->format());
        foreach (['/jobs/' . $jobId . '/brief/versions', '/jobs/' . $jobId . '/brief/versions/1.1.0', '/jobs/' . $jobId . '/brief/print', '/briefs', '/campaigns'] as $path) {
            t_eq(200, (ts_app($s))(ts_request('GET', $path), $d)->status(), $path);
        }
        t_eq(404, (ts_app($s))(ts_request('GET', '/jobs/' . $jobId . '/brief/versions/9.9.9'), $d)->status());
        // transitions: wait without a reason refused, with one accepted; resume goes back
        t_contains('Say what the job is waiting for.', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => '']])));
        t_contains('"_redirect":', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'Images']])));
        t_eq('waiting', $d->jobs->get($jobId)->stage->value);
        bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'resume']]);
        t_eq('briefed', $d->jobs->get($jobId)->stage->value);
        // recall (no asset started) and re-send unchanged as an update
        t_contains('"_redirect":', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'recall']])));
        t_eq('draft', $d->jobs->get($jobId)->stage->value);
        t_eq('Inbox', $d->jobs->get($jobId)->status);
        t_eq(1, count($d->activity->listByVerb($jobId, 'brief_recalled')));
        t_contains('Re-send to Traffic', ts_body((ts_app($s))(ts_request('GET', '/jobs/' . $jobId . '/brief'), $d)));
        t_contains('"_redirect":', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'patch', 'note' => 'Re-sent after recall']])));
        t_eq(['briefed', '1.1.1'], [$d->jobs->get($jobId)->stage->value, $d->briefs->getByJob($jobId)->version->format()]);
        // once an asset has started, recall is refused
        $d->db->exec("UPDATE assets SET status = 'In Progress' WHERE job_id = :j AND rowid = (SELECT MIN(rowid) FROM assets WHERE job_id = :j)", ['j' => $jobId]);
        t_contains('An asset has started', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'recall']])));
        t_contains('Unknown action', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'approve_internal']])));
    },
    'brief http: CSRF is required on every brief write' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $s = bh_session($d, $p['am']);
        $title = $d->briefs->getByJob($jobId)->title;
        foreach ([['PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'Hacked']]], ['POST', '/jobs/' . $jobId . '/brief/send', []],
            ['POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'cancel', 'reason' => 'x']]], ['POST', '/briefs', ['nb' => ['campaign_id' => $c['campaign']]]],
            ['POST', '/campaigns', ['nc' => ['brand_id' => $c['brand'], 'name' => 'Sneaky']]]] as [$m, $path, $sig]) {
            $resp = (ts_app($s))(ts_ds_request($m, $path, $s, $sig, false), $d);
            t_contains('Security check failed', ts_body($resp), $path);
        }
        t_eq($title, $d->briefs->getByJob($jobId)->title);
        t_eq('draft', $d->jobs->get($jobId)->stage->value);
        t_eq(1, (int) $d->db->scalar('SELECT COUNT(*) FROM jobs'));
    },
    'brief http: a Designer is let in (BetaGate) but refused by Policy: 404 on a draft, no edits' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $s = bh_session($d, $p['designer']);
        $page = (ts_app($s))(ts_request('GET', '/jobs/' . $jobId . '/brief'), $d);
        t_eq(404, $page->status(), 'a draft does not leak through the gate');
        t_contains('Only the brief owner can edit this draft.', ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'x']])));
        // straight to the handlers: Policy refuses
        $designer = $d->users->findById($p['designer']);
        $req = ts_ds_request('PATCH', '/jobs/' . $jobId . '/brief', $s, ['brief' => ['title' => 'Designer edit']])->withUser($designer)->withPathValues(['id' => $jobId]);
        t_contains('Only the brief owner can edit this draft.', ts_body(BriefHandlers::autosave($req, $d)));
        $get = ts_request('GET', '/jobs/' . $jobId . '/brief')->withUser($designer)->withPathValues(['id' => $jobId]);
        t_eq(404, BriefHandlers::editor($get, $d)->status(), 'a draft does not leak');
        $create = ts_ds_request('POST', '/briefs', $s, ['nb' => ['campaign_id' => $c['campaign']]])->withUser($designer);
        t_contains('Only account managers', ts_body(BriefHandlers::create($create, $d)));
        t_eq('Launch', $d->briefs->getByJob($jobId)->title);
    },
    'brief http: another AM gets 404 on a draft and no budget on a sent job; claim AM on unowned jobs' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $ben = bh_session($d, $p['am2']);
        t_eq(404, (ts_app($ben))(ts_request('GET', '/jobs/' . $jobId . '/brief'), $d)->status());
        t_contains('Only the brief owner', ts_body(bh_ds($d, $ben, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'x']])));
        // amy finishes and sends with a budget of 9000
        $amy = bh_session($d, $p['am']);
        $rv = $d->briefs->getByJob($jobId)->rowVersion;
        bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['due_date' => '2026-10-20', 'creative_direction' => 'x', 'budget' => '9000', 'row_version' => $rv]]);
        bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'print-ad']]);
        bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]]);
        bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/send');
        t_eq(9000.0, $d->briefs->getByJob($jobId)->budget);
        $html = ts_body((ts_app($ben))(ts_request('GET', '/jobs/' . $jobId . '/brief'), $d));
        t_contains('Brief v1.0.0', $html);
        t_not_contains('9000', $html);
        t_not_contains('9 000', $html);
        t_not_contains('id="brief-form"', $html, 'read only');
        $print = ts_body((ts_app($ben))(ts_request('GET', '/jobs/' . $jobId . '/brief/print'), $d));
        t_not_contains('9 000', $print);
        t_contains('R 9 000', ts_body((ts_app($amy))(ts_request('GET', '/jobs/' . $jobId . '/brief/print'), $d)));
        // a legacy job without AM or creator: claim page, then Make me AM
        $d->db->exec("INSERT INTO jobs (id, job_number, campaign_id, title, status) VALUES ('legacy1', 'MERC-050', :c, 'Legacy job', 'In Progress')", ['c' => $c['campaign']]);
        $claim = ts_body((ts_app($ben))(ts_request('GET', '/jobs/legacy1/brief'), $d));
        t_contains('Make me AM', $claim);
        t_contains('"_redirect":', ts_body(bh_ds($d, $ben, 'POST', '/jobs/legacy1/claim-am')));
        t_eq($p['am2'], $d->jobs->get('legacy1')->amUserId);
        t_contains('already has an AM', ts_body(bh_ds($d, $amy, 'POST', '/jobs/legacy1/claim-am')));
        t_contains('id="brief-form"', ts_body((ts_app($ben))(ts_request('GET', '/jobs/legacy1/brief'), $d)), 'Ben can now edit');
    },
    'brief http: autosave conflict from another user re-renders the form with a warning' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $amy = bh_session($d, $p['am']);
        $rv = $d->briefs->getByJob($jobId)->rowVersion;
        // the COO edits first
        $coo = bh_session($d, $p['coo']);
        bh_ds($d, $coo, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'COO title', 'row_version' => $rv]]);
        $resp = ts_body(bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'Amy title', 'row_version' => $rv]]));
        t_contains('Someone else changed this brief', $resp);
        t_contains('id="brief-form"', $resp);
        t_contains('COO title', $resp);
        t_eq('COO title', $d->briefs->getByJob($jobId)->title);
        // her own stale version (two quick saves in a row) is not a conflict
        $rv2 = $d->briefs->getByJob($jobId)->rowVersion;
        bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'Amy 1', 'row_version' => $rv2]]);
        bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'Amy 2', 'row_version' => $rv2]]);
        t_eq('Amy 2', $d->briefs->getByJob($jobId)->title);
    },
    'brief http: every brief handler renders with transport=html' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $s = bh_session($d, $p['am']);
        $line = null;
        $calls = [
            ['GET', '/briefs', []], ['GET', '/campaigns', []], ['GET', '/jobs/' . $jobId . '/brief', []],
            ['POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]],
            ['POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => '']]],
            ['PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'T', 'due_date' => '2026-10-20', 'creative_direction' => 'x']]],
            ['PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['due_date' => 'bad']]],
            ['POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]]],
            ['POST', '/jobs/' . $jobId . '/assignments/designer', ['team_designer' => ['value' => $p['designer']]]],
            ['GET', '/jobs/' . $jobId . '/brief/send', []],
            ['POST', '/jobs/' . $jobId . '/brief/send', []],
            ['GET', '/jobs/' . $jobId . '/brief/update', []],
            ['POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'minor', 'note' => 'x']]],
            ['PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['due_date' => '2026-10-21']]],
            ['POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'minor', 'note' => 'x']]],
            ['GET', '/jobs/' . $jobId . '/brief/versions', []], ['GET', '/jobs/' . $jobId . '/brief/versions/1.1.0', []], ['GET', '/jobs/' . $jobId . '/brief/print', []],
            ['POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'hold', 'reason' => 'Paused']]],
            ['POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'resume']]],
            ['POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'wait']]],
            ['POST', '/jobs/' . $jobId . '/claim-am', []],
            ['POST', '/campaigns', ['nc' => ['brand_id' => $c['brand'], 'name' => 'New one']]],
            ['POST', '/campaigns', ['nc' => ['brand_id' => $c['brand'], 'name' => 'New one']]],
            ['POST', '/briefs', ['nb' => ['campaign_id' => $c['campaign']]]],
            ['POST', '/briefs', ['nb' => []]],
        ];
        foreach ($calls as [$m, $path, $sig]) {
            $resp = $m === 'GET' && $sig === [] && !str_ends_with($path, '/send') && !str_ends_with($path, '/update')
                ? (ts_app($s))(ts_request('GET', $path), $d)
                : bh_ds($d, $s, $m, $path, $sig);
            $r = $resp->render(Transport::Html);
            t_eq(200, $r->status, "$m $path");
            t_true($r->body() !== '', "$m $path body");
        }
        $lines = $d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id);
        foreach ([
            ['PATCH', '/jobs/' . $jobId . '/brief/assets/' . $lines[0]->id, ['dl' => ['ln_' . $lines[0]->id => ['label' => 'A', 'qty' => 2]]]],
            ['POST', '/jobs/' . $jobId . '/brief/assets/order', ['reorder' => ['line_id' => $lines[1]->id, 'dir' => 'up']]],
            ['DELETE', '/jobs/' . $jobId . '/brief/assets/' . $lines[1]->id, []],
        ] as [$m, $path, $sig]) {
            $r = bh_ds($d, $s, $m, $path, $sig)->render(Transport::Html);
            t_eq(200, $r->status, "$m $path");
        }
        t_eq(1, count($d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id)));
    },
];

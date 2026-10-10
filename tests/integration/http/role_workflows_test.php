<?php
declare(strict_types=1);

use App\Domain\Role;
use App\Http\Deps;
use App\Http\MemorySession;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

/**
 * Per-role workflows from docs/roles.md section 6, for what the new UI does
 * today, through the full Kernel (BetaGate, CSRF, routes, handlers, stores).
 * "Refused" means: the answer refuses, the page does not offer the control,
 * and the row is unchanged.
 */

/**
 * am_amy sends a Meridian brief (budget 25000, hours 30, creative direction
 * "Warm gold") to traffic_morgan at v1.0.0, then edits the working copy
 * without sending. She also keeps an unsent draft. am_ben owns a sent legacy
 * job with budget 25000 that nobody else is on.
 * @return array{0:Deps,1:array<string,string>,2:array<string,string>}
 */
function rw_world(): array
{
    [$d, $c, $p, $sent] = bh_world();
    foreach (['pm' => ['pm_sarah', Role::PM], 'pm2' => ['pm_lena', Role::PM], 'producer' => ['producer_taylor', Role::Producer],
        'qa' => ['qa_jordan', Role::QA], 'ecd' => ['ecd_dominic', Role::ECD], 'client' => ['client_sophie', Role::Client],
        'dev' => ['dev_dana', Role::Developer], 'seo' => ['seo_sam', Role::SEO], 'social' => ['social_sol', Role::Social],
        'copy2' => ['copy_chloe', Role::Copywriter]] as $k => [$name, $role]) {
        $p[$k] = ts_user($d, $name, $role);
    }
    $amy = bh_session($d, $p['am']);
    $rv = $d->briefs->getByJob($sent)->rowVersion;
    bh_ds($d, $amy, 'PATCH', '/jobs/' . $sent . '/brief', ['brief' => ['title' => 'Launch', 'due_date' => '2026-10-12', 'creative_direction' => 'Warm gold',
        'budget' => '25000', 'hours_estimate' => '30', 'row_version' => $rv]]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $sent . '/brief/assets', ['new_line' => ['template_id' => 'print-ad']]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $sent . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]]);
    t_contains('"_redirect":', ts_body(bh_ds($d, $amy, 'POST', '/jobs/' . $sent . '/brief/send')), 'sent');
    $rv = $d->briefs->getByJob($sent)->rowVersion;
    bh_ds($d, $amy, 'PATCH', '/jobs/' . $sent . '/brief', ['brief' => ['title' => 'Unsent Title QQQ', 'creative_direction' => 'UNSENT-CHANGE-XYZ', 'row_version' => $rv]]);
    t_true($d->briefs->getByJob($sent)->hasUnsentChanges, 'unsent changes');
    $draft = ts_body(bh_ds($d, $amy, 'POST', '/briefs', ['nb' => ['campaign_id' => $c['campaign'], 'title' => 'Secret draft ZZZ']]));
    t_true(preg_match('#/jobs/([0-9a-f]{32})/brief#', $draft, $m) === 1, 'draft created');
    $other = jf_job($d, 'MERC-090', $c['campaign'], 'Ben only job', ['status' => 'In Progress', 'due' => '2026-10-20', 'slots' => ['AM' => $p['am2'], 'Traffic' => $p['traffic2']]]);
    $d->db->exec('UPDATE briefs SET budget = 25000 WHERE job_id = :j', ['j' => $other]);
    return [$d, $p, ['sent' => $sent, 'draft' => $m[1], 'other' => $other, 'campaign' => $c['campaign']]];
}

function rw_get(Deps $d, MemorySession $s, string $path): App\Http\Response
{
    $query = [];
    $qs = strpos($path, '?');
    if ($qs !== false) {
        parse_str(substr($path, $qs + 1), $query);
        $path = substr($path, 0, $qs);
    }
    return (ts_app($s))(ts_request('GET', $path, [], '', [], $query), $d);
}

/** Traffic assigns the team the way the brief page does. */
function rw_assign(Deps $d, MemorySession $s, string $jobId, string $role, string $userId): string
{
    return ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/assignments/' . $role, ['team_' . $role => ['value' => $userId]]));
}

function rw_no_budget(string $html, string $where): void
{
    foreach (['25000', '25 000', 'Budget (ZAR)'] as $needle) {
        t_not_contains($needle, $html, $where . ': budget must not appear');
    }
}

function rw_no_working_copy(string $html, string $where): void
{
    foreach (['UNSENT-CHANGE-XYZ', 'Unsent Title QQQ', 'Unsent changes', 'Secret draft ZZZ', 'id="brief-form"'] as $needle) {
        t_not_contains($needle, $html, $where . ': working copy or drafts must not appear');
    }
}

return [
    // ---- Traffic --------------------------------------------------------------------
    'roles TRF-1: Traffic sees the sent brief waiting for a team, assigns CD, creatives and QA, then starts work' => function (): void {
        [$d, $p, $j] = rw_world();
        $t = bh_session($d, $p['traffic']);
        $today = rw_get($d, $t, '/today');
        t_eq(200, $today->status(), 'Traffic is in the new UI');
        $html = ts_body($today);
        t_contains('Briefs waiting for Traffic', $html);
        t_contains('Needs a team', $html);
        t_contains('Launch', $html);
        rw_no_working_copy($html, '/today');
        // start before a team is refused, and the rail does not offer it
        $page = ts_body(rw_get($d, $t, '/jobs/' . $j['sent'] . '/brief'));
        t_not_contains('Start work', $page, 'no Start button without a team');
        t_contains('Assign the CD or a creative', ts_body(bh_ds($d, $t, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'start']])));
        t_eq('briefed', $d->jobs->get($j['sent'])->stage->value);
        foreach (['cd' => $p['cd'], 'copywriter' => $p['copy'], 'designer' => $p['designer'], 'qa' => $p['qa']] as $slot => $uid) {
            t_contains('id="brief-team"', rw_assign($d, $t, $j['sent'], $slot, $uid), $slot);
        }
        $team = $d->assignments->team($j['sent']);
        t_eq([$p['cd'], $p['copy'], $p['designer'], $p['qa']], [$team->userFor(Role::CD), $team->userFor(Role::Copywriter), $team->userFor(Role::Designer), $team->userFor(Role::QA)]);
        $acts = $d->activity->listByVerb($j['sent'], 'assigned_to_job');
        t_eq($p['traffic'], $acts[count($acts) - 1]->actorId, 'the actor comes from the session');
        t_contains('Start work', ts_body(rw_get($d, $t, '/jobs/' . $j['sent'] . '/brief')));
        t_contains('"_redirect":', ts_body(bh_ds($d, $t, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'start']])));
        t_eq('in_progress', $d->jobs->get($j['sent'])->stage->value);
        t_eq('In Progress', $d->jobs->get($j['sent'])->status, 'legacy status mirrored');
        t_not_contains('Needs a team', ts_body(rw_get($d, $t, '/today')));
    },
    'roles TRF-3/4/5: Traffic cannot edit the brief, never sees drafts or budget, and has no Briefs or Campaigns' => function (): void {
        [$d, $p, $j] = rw_world();
        $t = bh_session($d, $p['traffic']);
        $before = $d->briefs->getByJob($j['sent']);
        t_contains('Only the brief owner', ts_body(bh_ds($d, $t, 'PATCH', '/jobs/' . $j['sent'] . '/brief', ['brief' => ['creative_direction' => 'Traffic text', 'row_version' => $before->rowVersion]])));
        t_contains('You cannot change the title', ts_body(bh_ds($d, $t, 'GET', '/jobs/' . $j['sent'] . '/cells/title/edit')));
        t_eq($before->rowVersion, $d->briefs->getByJob($j['sent'])->rowVersion, 'brief unchanged');
        $page = ts_body(rw_get($d, $t, '/jobs/' . $j['sent'] . '/brief'));
        t_contains('Warm gold', $page, 'the sent version');
        rw_no_working_copy($page, 'brief page');
        rw_no_budget($page, 'brief page');
        t_eq(404, rw_get($d, $t, '/jobs/' . $j['draft'] . '/brief')->status(), 'a draft is 404');
        $grid = ts_body(rw_get($d, $t, '/jobs?stages=all&cols=job_number,title,stage,budget,hours'));
        t_contains('Ben only job', $grid, 'Traffic sees every sent job');
        rw_no_working_copy($grid, '/jobs');
        rw_no_budget($grid, '/jobs');
        t_not_contains('id="col-draft"', ts_body(rw_get($d, $t, '/jobs/board?stages=all')), 'no Draft column');
        $sheet = ts_body(bh_ds($d, $t, 'GET', '/jobs/' . $j['other'] . '/sheet'));
        t_contains('Ben only job', $sheet);
        rw_no_budget($sheet, 'sheet');
        t_contains('no longer exists', ts_body(bh_ds($d, $t, 'GET', '/jobs/' . $j['draft'] . '/sheet')));
        t_contains('no longer exists', ts_body(bh_ds($d, $t, 'POST', '/jobs/' . $j['draft'] . '/assignments/designer', ['team_designer' => ['value' => $p['designer']]])));
        t_eq(null, $d->assignments->team($j['draft'])->userFor(Role::Designer));
        $nav = ts_body(rw_get($d, $t, '/today'));
        t_not_contains('href="/slash301pm/briefs"', $nav);
        t_not_contains('href="/slash301pm/campaigns"', $nav);
        t_not_contains('href="/slash301pm/admin/users"', $nav);
        t_contains('href="/slash301pm/jobs"', $nav);
        t_eq(403, rw_get($d, $t, '/briefs')->status());
        t_eq(403, rw_get($d, $t, '/campaigns')->status());
        t_eq(403, rw_get($d, $t, '/admin/users')->status());
    },
    'roles TRF-6: Traffic marks an approved job done, then archives it' => function (): void {
        [$d, $p, $j] = rw_world();
        $d->db->exec("UPDATE jobs SET stage = 'approved_client', status = 'Approved (External)' WHERE id = :j", ['j' => $j['sent']]);
        $t = bh_session($d, $p['traffic']);
        t_contains('Mark done', ts_body(rw_get($d, $t, '/jobs/' . $j['sent'] . '/brief')));
        t_contains('"_redirect":', ts_body(bh_ds($d, $t, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'done']])));
        t_eq('done', $d->jobs->get($j['sent'])->stage->value);
        bh_ds($d, $t, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'archive']]);
        t_eq('archived', $d->jobs->get($j['sent'])->stage->value);
        t_contains('You cannot cancel job', ts_body(bh_ds($d, $t, 'POST', '/jobs/' . $j['other'] . '/transition', ['tr' => ['action' => 'cancel', 'reason' => 'x']])), 'Q4: Traffic cannot cancel');
    },

    // ---- Creatives ------------------------------------------------------------------
    'roles CPY-3/DSG/DEV-3: makers only see jobs they are on; anything else is 404' => function (): void {
        [$d, $p, $j] = rw_world();
        $t = bh_session($d, $p['traffic']);
        rw_assign($d, $t, $j['sent'], 'designer', $p['designer']);
        foreach (['designer' => true, 'copy' => false, 'dev' => false, 'seo' => false, 'social' => false] as $who => $assigned) {
            $s = bh_session($d, $p[$who]);
            t_eq(200, rw_get($d, $s, '/today')->status(), $who . ' is in the new UI');
            $grid = ts_body(rw_get($d, $s, '/jobs?stages=all'));
            t_not_contains('Ben only job', $grid, $who . ': unassigned job not listed');
            foreach (['/jobs/' . $j['other'] . '/brief', '/jobs/' . $j['other'] . '/brief/versions', '/jobs/' . $j['other'] . '/brief/print', '/jobs/' . $j['draft'] . '/brief'] as $path) {
                t_eq(404, rw_get($d, $s, $path)->status(), $who . ' ' . $path);
            }
            t_contains('no longer exists', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $j['other'] . '/sheet')), $who);
            t_contains('no longer exists', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $j['other'] . '/transition', ['tr' => ['action' => 'hold', 'reason' => 'x']])), $who);
            t_eq($assigned ? 200 : 404, rw_get($d, $s, '/jobs/' . $j['sent'] . '/brief')->status(), $who . ' on the sent job');
            t_eq($assigned, str_contains($grid, 'Launch'), $who . ' sees the assigned job in the grid');
        }
    },
    'roles CPY-2/CPY-5/CD-5: an assigned Copywriter reads only the last sent version, with hours and no budget' => function (): void {
        [$d, $p, $j] = rw_world();
        rw_assign($d, bh_session($d, $p['traffic']), $j['sent'], 'copywriter', $p['copy']);
        $s = bh_session($d, $p['copy']);
        foreach (['/jobs/' . $j['sent'] . '/brief', '/jobs/' . $j['sent'] . '/brief/versions', '/jobs/' . $j['sent'] . '/brief/versions/1.0.0', '/jobs/' . $j['sent'] . '/brief/print'] as $path) {
            $r = rw_get($d, $s, $path);
            t_eq(200, $r->status(), $path);
            $html = ts_body($r);
            t_contains('Warm gold', $html, $path);
            rw_no_working_copy($html, $path);
            rw_no_budget($html, $path);
        }
        $page = ts_body(rw_get($d, $s, '/jobs/' . $j['sent'] . '/brief'));
        t_contains('Hours estimate', $page, 'hours visible on an assigned job');
        t_not_contains('Start work', $page, 'makers start implicitly');
        t_not_contains('Cancel job...', $page);
        t_contains('Only the brief owner', ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $j['sent'] . '/brief', ['brief' => ['title' => 'x']])));
        $sheet = ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $j['sent'] . '/sheet'));
        rw_no_working_copy($sheet, 'sheet');
        rw_no_budget($sheet, 'sheet');
        $grid = ts_body(rw_get($d, $s, '/jobs?stages=all&cols=job_number,title,budget,hours'));
        rw_no_budget($grid, 'grid');
        t_not_contains('Unsent Title QQQ', $grid);
        $today = ts_body(rw_get($d, $s, '/today'));
        t_contains('New and updated briefs', $today);
        t_contains('New brief v1.0.0', $today);
        t_contains('Due soon', $today);
        rw_no_working_copy($today, '/today');
        // CPY-2: after Amy sends v1.1.0 the Copywriter reads it and My day says so
        bh_ds($d, bh_session($d, $p['am']), 'POST', '/jobs/' . $j['sent'] . '/brief/update', ['send' => ['bump' => 'minor', 'note' => 'New title']]);
        t_contains('Brief updated to v1.1.0', ts_body(rw_get($d, $s, '/today')));
        t_contains('UNSENT-CHANGE-XYZ', ts_body(rw_get($d, $s, '/jobs/' . $j['sent'] . '/brief')), 'now sent');
    },
    'roles DSG-3/CD-6: a Designer cannot move a job; the CD cannot assign creatives; the CD reads all sent jobs' => function (): void {
        [$d, $p, $j] = rw_world();
        $t = bh_session($d, $p['traffic']);
        rw_assign($d, $t, $j['sent'], 'designer', $p['designer']);
        rw_assign($d, $t, $j['sent'], 'cd', $p['cd']);
        rw_assign($d, $t, $j['sent'], 'copywriter', $p['copy']);
        $rv = $d->jobs->get($j['sent'])->rowVersion;
        $ds = bh_session($d, $p['designer']);
        foreach (['approved_internal' => 'cannot move from briefed', 'in_progress' => 'by itself'] as $to => $want) {
            t_contains($want, ts_body(bh_ds($d, $ds, 'POST', '/jobs/' . $j['sent'] . '/move', ['move' => ['to' => $to, 'row_version' => $rv, 'page' => 'board']])), $to);
        }
        t_eq('briefed', $d->jobs->get($j['sent'])->stage->value);
        $board = ts_body(rw_get($d, $ds, '/jobs/board'));
        t_not_contains('draggable="true"', $board, 'nothing to drag for a Designer');
        $cd = bh_session($d, $p['cd']);
        t_contains('Traffic assigns', rw_assign($d, $cd, $j['sent'], 'copywriter', $p['copy2']));
        t_eq($p['copy'], $d->assignments->team($j['sent'])->userFor(Role::Copywriter), 'CD-6: unchanged');
        $grid = ts_body(rw_get($d, $cd, '/jobs?stages=all&cols=job_number,title,hours,budget'));
        t_contains('Ben only job', $grid, 'CD reads all sent jobs');
        rw_no_budget($grid, 'CD grid');
        t_eq(200, rw_get($d, $cd, '/jobs/' . $j['other'] . '/brief')->status());
        t_not_contains('Hours estimate', ts_body(rw_get($d, $cd, '/jobs/' . $j['other'] . '/brief')), 'hours only on the CD\'s jobs');
        t_eq(404, rw_get($d, $cd, '/jobs/' . $j['draft'] . '/brief')->status());
        // the CD may start work and put their own job on waiting
        t_contains('"_redirect":', ts_body(bh_ds($d, $cd, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'start']])));
        t_contains('"_redirect":', ts_body(bh_ds($d, $cd, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'Logo']])));
        t_eq('waiting', $d->jobs->get($j['sent'])->stage->value);
        t_contains('You cannot put on waiting', ts_body(bh_ds($d, $cd, 'POST', '/jobs/' . $j['other'] . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'x']])));
    },

    // ---- QA ---------------------------------------------------------------------------
    'roles QA-3: QA reads its jobs, cannot start, move or approve, and sees no budget' => function (): void {
        [$d, $p, $j] = rw_world();
        rw_assign($d, bh_session($d, $p['traffic']), $j['sent'], 'qa', $p['qa']);
        rw_assign($d, bh_session($d, $p['traffic']), $j['sent'], 'designer', $p['designer']);
        $q = bh_session($d, $p['qa']);
        $page = ts_body(rw_get($d, $q, '/jobs/' . $j['sent'] . '/brief'));
        t_contains('Warm gold', $page);
        rw_no_budget($page, 'QA brief');
        t_contains('Nothing for you to do here right now.', $page, 'no dead buttons');
        t_contains('You cannot start work', ts_body(bh_ds($d, $q, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'start']])));
        t_contains('Unknown action', ts_body(bh_ds($d, $q, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'approve_internal']])));
        t_eq('briefed', $d->jobs->get($j['sent'])->stage->value);
        t_eq(404, rw_get($d, $q, '/jobs/' . $j['other'] . '/brief')->status(), 'unassigned job');
        t_contains('Launch', ts_body(rw_get($d, $q, '/today')), 'My day lists the assigned job');
    },

    // ---- Managers ---------------------------------------------------------------------
    'roles PM-1/PM-3: a PM creates and sends a brief; cannot edit another PM\'s job; sees budget only on own jobs' => function (): void {
        [$d, $p, $j] = rw_world();
        $pm = bh_session($d, $p['pm']);
        $resp = ts_body(bh_ds($d, $pm, 'POST', '/briefs', ['nb' => ['campaign_id' => $j['campaign'], 'title' => 'PM brief']]));
        t_true(preg_match('#/jobs/([0-9a-f]{32})/brief#', $resp, $m) === 1);
        $id = $m[1];
        $rv = $d->briefs->getByJob($id)->rowVersion;
        bh_ds($d, $pm, 'PATCH', '/jobs/' . $id . '/brief', ['brief' => ['due_date' => '2026-10-30', 'creative_direction' => 'x', 'budget' => '7000', 'row_version' => $rv]]);
        bh_ds($d, $pm, 'POST', '/jobs/' . $id . '/brief/assets', ['new_line' => ['template_id' => 'print-ad']]);
        bh_ds($d, $pm, 'POST', '/jobs/' . $id . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic2']]]);
        t_contains('"_redirect":', ts_body(bh_ds($d, $pm, 'POST', '/jobs/' . $id . '/brief/send')));
        t_eq('briefed', $d->jobs->get($id)->stage->value);
        t_eq($p['pm'], $d->briefs->getByJob($id)->createdBy, 'creator from the session');
        t_eq(null, $d->assignments->team($id)->userFor(Role::AM), 'the AM slot stays empty');
        t_contains('R 7 000', ts_body(rw_get($d, $pm, '/jobs/' . $id . '/brief/print')));
        // PM-3: pm_lena's job
        $lena = bh_session($d, $p['pm2']);
        $other = jf_job($d, 'MERC-091', $j['campaign'], 'Lena job', ['status' => 'In Progress', 'slots' => ['PM' => $p['pm2']]]);
        $before = $d->jobs->get($other)->rowVersion;
        $grid = ts_body(bh_ds($d, $pm, 'PATCH', '/jobs/' . $other . '/fields/title', ['edit' => ['value' => 'Hijack', 'row_version' => $before, 'brief_rv' => $d->briefs->getByJob($other)->rowVersion]]));
        t_contains('You cannot change the title', $grid);
        t_eq('Lena job', $d->jobs->get($other)->title);
        t_eq(200, rw_get($d, $lena, '/today')->status());
        t_not_contains('7 000', ts_body(rw_get($d, $lena, '/jobs/' . $id . '/brief/print')), 'budget only for the owner');
        t_contains('href="/slash301pm/briefs"', ts_body(rw_get($d, $pm, '/today')), 'PM has Briefs');
        t_eq(200, rw_get($d, $pm, '/campaigns')->status());
    },
    'roles PRD-1/2/3: the Producer sees a job he holds on My day, starts it, cannot approve internally' => function (): void {
        [$d, $p, $j] = rw_world();
        $id = jf_job($d, 'MERC-092', $j['campaign'], 'Shoot day', ['status' => 'To Do', 'due' => '2026-10-12', 'slots' => ['Producer' => $p['producer'], 'CD' => $p['cd']]]);
        $s = bh_session($d, $p['producer']);
        $today = ts_body(rw_get($d, $s, '/today'));
        t_contains('Shoot day', $today);
        t_contains('Waiting on me', $today, 'the owner sections');
        t_contains('"_redirect":', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $id . '/transition', ['tr' => ['action' => 'start']])));
        t_eq('in_progress', $d->jobs->get($id)->stage->value);
        $d->db->exec("UPDATE jobs SET stage = 'in_review', status = 'In Review' WHERE id = :j", ['j' => $id]);
        $rv = $d->jobs->get($id)->rowVersion;
        t_contains('not available yet', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $id . '/move', ['move' => ['to' => 'approved_internal', 'row_version' => $rv]])));
        t_eq('in_review', $d->jobs->get($id)->stage->value);
        t_contains('You cannot start work', ts_body(bh_ds($d, bh_session($d, $p['am']), 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'start']])), 'AM: matrix deny');
    },
    'roles AM-6: another AM\'s draft is 404 and not listed; their sent job shows no budget' => function (): void {
        [$d, $p, $j] = rw_world();
        $d->db->exec('UPDATE briefs SET budget = 9100 WHERE job_id = :j', ['j' => $j['other']]);
        $ben = bh_session($d, $p['am2']);
        t_eq(404, rw_get($d, $ben, '/jobs/' . $j['draft'] . '/brief')->status());
        $grid = ts_body(rw_get($d, $ben, '/jobs?stages=all&cols=job_number,title,budget'));
        t_not_contains('Secret draft ZZZ', $grid);
        t_contains('Launch', $grid);
        t_not_contains('25 000', $grid, 'Amy\'s budget hidden');
        t_contains('R 9 100', $grid, 'his own budget shows');
        t_contains('Secret draft ZZZ', ts_body(rw_get($d, bh_session($d, $p['am']), '/jobs?stages=all')), 'Amy sees her draft');
    },

    // ---- Admin ------------------------------------------------------------------------
    'roles COO/ECD: admin pages and nav; other roles get 403 and no admin nav' => function (): void {
        [$d, $p] = rw_world();
        foreach (['coo', 'ecd'] as $who) {
            $s = bh_session($d, $p[$who]);
            t_eq(200, rw_get($d, $s, '/admin/users')->status(), $who);
            t_eq(200, rw_get($d, $s, '/admin/system')->status(), $who);
            $nav = ts_body(rw_get($d, $s, '/today'));
            t_contains('href="/slash301pm/admin/users"', $nav, $who);
            t_contains('href="/slash301pm/campaigns"', $nav, $who);
        }
        foreach (['am', 'traffic', 'cd', 'qa'] as $who) {
            $s = bh_session($d, $p[$who]);
            t_eq(403, rw_get($d, $s, '/admin/users')->status(), $who);
            t_eq(403, rw_get($d, $s, '/admin/system')->status(), $who);
            t_not_contains('href="/slash301pm/admin/system"', ts_body(rw_get($d, $s, '/today')), $who);
        }
    },

    // ---- Client -----------------------------------------------------------------------
    'roles CLI-6: a Client is sent to /legacy/ from every new screen' => function (): void {
        [$d, $p, $j] = rw_world();
        $s = bh_session($d, $p['client']);
        foreach (['/today', '/jobs', '/jobs/board', '/briefs', '/admin/system', '/jobs/' . $j['sent'] . '/brief'] as $path) {
            $r = rw_get($d, $s, $path);
            t_eq(302, $r->status(), $path);
            t_eq('/slash301pm/legacy/', $r->render(App\Config\Transport::Sse)->header('Location'), $path);
        }
        t_contains('{"_redirect":"/slash301pm/legacy/"}', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $j['sent'] . '/sheet')));
        t_contains('{"_redirect":"/slash301pm/legacy/"}', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $j['sent'] . '/transition', ['tr' => ['action' => 'hold', 'reason' => 'x']])));
        t_eq('briefed', $d->jobs->get($j['sent'])->stage->value);
    },
];

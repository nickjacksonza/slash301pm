<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\Role;
use App\Http\Deps;
use App\Http\MemorySession;

require_once dirname(__DIR__, 2) . '/support/social_fx.php';

/**
 * Owner decisions 2026-10 through the full Kernel: brand logos and the My day
 * brand row, assigned-only filter lists, asset status overrides and the COO
 * report, role-task templates and their default assignees, Producer test
 * reports, and the demo role tasks action.
 */
const OD_HOSTILE = '<script>"\' & {{x}} <img src=x onerror=1>';

/**
 * As $amId: a brief with the given lines ([template_id, qty, channel]), the
 * given slots filled before the send, sent. Returns the job id.
 * @param list<array{0:string,1:int,2:string}> $lines
 * @param array<string,string> $slots role key (lower case) => user id
 */
function od_send(Deps $d, string $campaignId, string $amId, string $title, array $lines, array $slots): string
{
    $amy = bh_session($d, $amId);
    $body = ts_body(bh_ds($d, $amy, 'POST', '/briefs', ['nb' => ['campaign_id' => $campaignId, 'title' => $title]]));
    t_true(preg_match('#/slash301pm/jobs/([0-9a-f]{32})/brief#', $body, $m) === 1, 'brief created');
    $jobId = $m[1];
    sx_ok(bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => $title, 'brief_date' => '2026-10-01', 'due_date' => '2026-10-07', 'creative_direction' => 'Warm and gold.', 'row_version' => $d->briefs->getByJob($jobId)->rowVersion]]));
    foreach ($lines as $i => [$tpl, $qty, $channel]) {
        sx_ok(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => $tpl]]));
        $line = $d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id)[$i];
        sx_ok(bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . $line->id, ['dl' => ['ln_' . $line->id => [
            'template_id' => $tpl, 'label' => \App\Domain\AssetTemplates::find($tpl)->name, 'qty' => $qty, 'channel' => $channel, 'size_format' => '', 'specs' => '']]]));
    }
    foreach ($slots as $role => $uid) {
        sx_ok(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/assignments/' . $role, ['team_' . $role => ['value' => $uid]]));
    }
    sx_ok(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/send'));
    return $jobId;
}

/** @return array{0:Deps,1:array<string,string>,2:array<string,string>} deps, people (+ dev, seo, producer, ecd), campaign ids (merc, aura) */
function od_world(): array
{
    [$d, $c, $p] = bh_world();
    $aura = bd_seed_campaign($d, 'AURA', 'Aura Spa', 'Winter Glow');
    $p['dev'] = ts_user($d, 'dev_dana', Role::Developer);
    $p['seo'] = ts_user($d, 'seo_sam', Role::SEO);
    $p['producer'] = ts_user($d, 'producer_taylor', Role::Producer);
    $p['ecd'] = ts_user($d, 'ecd_dominic', Role::ECD);
    $p['social'] = ts_user($d, 'social_sol', Role::Social);
    return [$d, $p, ['merc' => $c['campaign'], 'merc_brand' => $c['brand'], 'aura' => $aura['campaign'], 'aura_brand' => $aura['brand']]];
}

function od_page(Deps $d, MemorySession $s, string $path, array $query = []): App\Http\Response
{
    return (ts_app($s))(ts_request('GET', $path, [], '', [], $query), $d);
}

return [
    'role tasks: assets go to the job holder of the template role at send; Traffic fills Developer, SEO, Producer after the send' => function (): void {
        [$d, $p, $c] = od_world();
        $job = od_send($d, $c['merc'], $p['am'], 'Launch tracking', [['utm-links', 1, ''], ['campaign-hashtags', 1, ''], ['asset-test-report', 1, ''], ['social-static', 1, 'Instagram']],
            ['traffic' => $p['traffic'], 'developer' => $p['dev'], 'producer' => $p['producer']]);
        $by = [];
        foreach ($d->assets->listByJob($job) as $a) {
            $by[(string) $a->templateId] = $a->assignedTo;
        }
        t_eq($p['dev'], $by['utm-links'], 'Developer slot holder');
        t_eq(null, $by['campaign-hashtags'], 'no SEO on the job: unassigned');
        t_eq($p['producer'], $by['asset-test-report'], 'Producer slot holder');
        t_eq(null, $by['social-static'], 'other templates stay unassigned');
        // After the send: Traffic may fill SEO (and Producer); the Designer may not; the AM keeps owner rights.
        $tr = bh_session($d, $p['traffic']);
        $team = ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $job . '/assignments/seo', ['team_seo' => ['value' => $p['seo']]]));
        t_contains('id="brief-team"', $team);
        t_eq($p['seo'], $d->assignments->team($job)->userFor(Role::SEO));
        t_contains('data-toast="error"', ts_body(bh_ds($d, bh_session($d, $p['designer']), 'POST', '/jobs/' . $job . '/assignments/producer', ['team_producer' => ['value' => '']])));
        t_eq($p['producer'], $d->assignments->team($job)->userFor(Role::Producer));
        // The Traffic brief page offers the three pickers.
        $page = ts_body(od_page($d, $tr, '/jobs/' . $job . '/brief'));
        foreach (['team-developer', 'team-seo', 'team-producer'] as $id) {
            t_contains('id="' . $id . '"', $page, $id);
        }
    },

    'overrides: Traffic overrides an asset with a reason (activity, recipients, html transport); Designer, AM and no-CSRF are refused' => function (): void {
        [$d, $p, $c] = od_world();
        $job = od_send($d, $c['merc'], $p['am'], 'Crunch job', [['social-static', 2, 'Instagram']], ['traffic' => $p['traffic'], 'cd' => $p['cd']]);
        [$a1, $a2] = sx_assets($d, $job);
        $d->db->exec('UPDATE assets SET assigned_to = :u WHERE id = :a', ['u' => $p['designer'], 'a' => $a1]);
        $tr = bh_session($d, $p['traffic']);
        $sig = static fn (string $to, string $reason, string $from = 'Inbox'): array => ['ova' => ['asset_id' => $a1, 'from' => $from, 'to' => $to, 'reason' => $reason]];
        // the page: Traffic sees every asset with an Override control
        $page = od_page($d, $tr, '/jobs/' . $job . '/assets');
        t_eq(200, $page->status());
        t_contains('Override the status of', ts_body($page));
        // refusals first: no reason, unknown value, the Designer, the AM, missing CSRF token
        t_contains('Say why', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $job . '/assets/override', $sig('Done', '  '))));
        t_contains('Choose one of the listed statuses', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $job . '/assets/override', $sig('Hacked', 'x'))));
        foreach (['designer', 'am'] as $who) {
            $s = bh_session($d, $p[$who]);
            t_contains('data-toast="error"', ts_body(bh_ds($d, $s, 'POST', '/jobs/' . $job . '/assets/override', $sig('Done', 'Mine now'))), $who);
            t_eq(403, od_page($d, $s, '/jobs/' . $job . '/assets')->status(), "$who page");
        }
        $noToken = ts_ds_request('POST', '/jobs/' . $job . '/assets/override', $tr, $sig('Done', 'x'), false);
        t_contains('data-toast="error"', ts_body((ts_app($tr))($noToken, $d)));
        t_eq('Inbox', $d->assets->listByJob($job)[0]->status, 'nothing changed');
        // the override itself, answered in the html transport (one patch set: #job-assets + the toast region)
        $req = ts_ds_request('POST', '/jobs/' . $job . '/assets/override', $tr, $sig('In Review', 'Client deadline moved ' . OD_HOSTILE));
        $resp = (ts_app($tr))($req, $d);
        $html = ts_body($resp, Transport::Html);
        t_contains('id="job-assets"', $html);
        t_contains('Logged as an override', $html);
        t_not_contains('<img src=x', $html);
        t_eq('In Review', $d->assets->listByJob($job)[0]->status);
        $rows = $d->activity->listByVerb($job, 'asset_status_overridden');
        t_eq(1, count($rows));
        t_eq($p['traffic'], $rows[0]->actorId, 'actor from the session');
        t_eq(['Inbox', 'In Review', 'Client deadline moved ' . OD_HOSTILE], [$rows[0]->data['from'], $rows[0]->data['to'], $rows[0]->data['reason']]);
        t_eq([$p['designer'], $p['am'], $p['cd']], $rows[0]->data['recipients'], 'assignee, AM, CD');
        // stale: the browser still thinks the asset is Inbox
        t_contains('Changed by someone else', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $job . '/assets/override', $sig('Done', 'again'))));
        t_eq(1, count($d->activity->listByVerb($job, 'asset_status_overridden')));
        // the feed marks it: job sheet (Traffic) and the brief rail (AM)
        $sheet = ts_body(bh_ds($d, $tr, 'GET', '/jobs/' . $job . '/sheet'));
        t_contains('>Override</span>', $sheet);
        t_contains('overrode the status of', $sheet);
        t_contains('/slash301pm/jobs/' . $job . '/assets', $sheet, 'sheet links to the assets page');
        t_not_contains('All assets', ts_body(bh_ds($d, bh_session($d, $p['am']), 'GET', '/jobs/' . $job . '/sheet')), 'the AM gets no asset list');
        // the Designer sees it in My day "Changed by others" (a recipient)
        t_contains('overrode an asset status', ts_body(od_page($d, bh_session($d, $p['designer']), '/today')));
    },

    'overrides: a Social post override moves the job stage, is logged; the report is COO only and escaped' => function (): void {
        [$d, $p, $jobId] = sx_world(1);
        $coo = bh_session($d, $p['coo']);
        $sol = bh_session($d, $p['social']);
        [$a1] = sx_assets($d, $jobId);
        $pid = sx_add($d, $sol, $a1, 'instagram');
        $tr = bh_session($d, $p['traffic']);
        $post = static fn (string $to, string $reason): array => ['ovp' => ['pub_id' => $pid, 'rv' => $d->publications->get($pid)->rowVersion, 'to' => $to, 'reason' => $reason]];
        t_contains('Logged as an override', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $jobId . '/publications/override', $post('live', 'Posted by the client ' . OD_HOSTILE))));
        t_eq('live', $d->publications->get($pid)->status->value);
        t_eq('live', sx_stage($d, $jobId), 'the job follows (checklist skipped on purpose)');
        t_eq('Approved (External)', sx_status($d, $jobId), 'legacy status unchanged');
        t_contains('Changed by someone else', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $jobId . '/publications/override',
            ['ovp' => ['pub_id' => $pid, 'rv' => 1, 'to' => 'checking', 'reason' => 'x']])));
        t_contains('already', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $jobId . '/publications/override', $post('live', 'x'))));
        t_contains('data-toast="error"', ts_body(bh_ds($d, bh_session($d, $p['designer']), 'POST', '/jobs/' . $jobId . '/publications/override', $post('checking', 'x'))));
        t_contains('Logged as an override', ts_body(bh_ds($d, $coo, 'POST', '/jobs/' . $jobId . '/publications/override', $post('checking', 'Wrong account'))));
        t_eq('approved_client', sx_stage($d, $jobId), 'and back');
        $rows = $d->activity->listByVerb($jobId, 'asset_status_overridden');
        t_eq(['publication', 'instagram', 'checking', 'live'], [$rows[0]->data['kind'], $rows[0]->data['platform'], $rows[0]->data['from'], $rows[0]->data['to']]);
        // the report
        $rep = od_page($d, $coo, '/admin/overrides', ['from' => '2026-10-01', 'to' => '2026-10-09']);
        t_eq(200, $rep->status());
        $html = ts_body($rep);
        t_contains('2 overrides', $html);
        t_contains('Wrong account', $html);
        t_contains('Posted by the client &lt;script&gt;', $html);
        t_not_contains('<img src=x', $html);
        t_contains('To check', $html, 'post statuses by label');
        t_contains('No overrides in this period.', ts_body(od_page($d, $coo, '/admin/overrides', ['from' => '2026-09-01', 'to' => '2026-09-02'])));
        foreach (['traffic', 'am', 'designer'] as $who) {
            t_eq(403, od_page($d, bh_session($d, $p[$who]), '/admin/overrides')->status(), $who);
        }
        $ecd = ts_user($d, 'ecd_e', Role::ECD);
        t_eq(403, od_page($d, bh_session($d, $ecd), '/admin/overrides')->status(), 'ECD');
        t_contains('/slash301pm/admin/overrides"', ts_body(od_page($d, $coo, '/today')), 'COO nav');
    },

    'brand logos: https only; javascript: and http refused; shown on Campaigns and on the Designer My day brand row' => function (): void {
        [$d, $p, $c] = od_world();
        $j1 = od_send($d, $c['merc'], $p['am'], 'Merc job', [['social-static', 1, 'Instagram']], ['traffic' => $p['traffic']]);
        $j2 = od_send($d, $c['aura'], $p['am'], 'Aura job', [['print-ad', 1, 'Print']], ['traffic' => $p['traffic']]);
        $d->db->exec('UPDATE assets SET assigned_to = :u WHERE job_id IN (:a, :b)', ['u' => $p['designer'], 'a' => $j1, 'b' => $j2]);
        $amy = bh_session($d, $p['am']);
        foreach (['javascript:alert(1)', 'http://example.com/l.png', 'data:image/png;base64,AAAA', 'https://x.com/"onerror=1'] as $bad) {
            t_contains('The logo must be an https:// link', ts_body(bh_ds($d, $amy, 'POST', '/brands/logo', ['eb' => ['brand_id' => $c['merc_brand'], 'logo_url' => $bad]])), $bad);
        }
        t_eq('', $d->brands->get($c['merc_brand'])->logoUrl);
        t_contains('data-toast="error"', ts_body(bh_ds($d, bh_session($d, $p['designer']), 'POST', '/brands/logo', ['eb' => ['brand_id' => $c['merc_brand'], 'logo_url' => 'https://x.com/a.png']])));
        t_contains('data-toast="error"', ts_body(bh_ds($d, bh_session($d, $p['traffic']), 'POST', '/brands/logo', ['eb' => ['brand_id' => $c['merc_brand'], 'logo_url' => 'https://x.com/a.png']])));
        $noToken = ts_ds_request('POST', '/brands/logo', $amy, ['eb' => ['brand_id' => $c['merc_brand'], 'logo_url' => 'https://x.com/a.png']], false);
        t_contains('data-toast="error"', ts_body((ts_app($amy))($noToken, $d)));
        $ok = bh_ds($d, $amy, 'POST', '/brands/logo', ['eb' => ['brand_id' => $c['merc_brand'], 'logo_url' => 'https://cdn.example.com/merc.png']]);
        $okHtml = ts_body($ok, Transport::Html);
        t_contains('Logo saved', $okHtml);
        t_contains('src="https://cdn.example.com/merc.png"', $okHtml);
        t_eq('https://cdn.example.com/merc.png', $d->brands->get($c['merc_brand'])->logoUrl);
        t_true($d->activity->count() > 0 && $d->db->scalar("SELECT COUNT(*) FROM activity WHERE verb = 'brand_logo_changed' AND actor_id = :u", ['u' => $p['am']]) == 1);
        // a row written by hand with a javascript: link is never rendered as an image
        $d->db->exec("UPDATE brands SET logo_url = 'javascript:alert(1)' WHERE id = :b", ['b' => $c['aura_brand']]);
        // the Designer's My day: both brands, the Meridian logo, Aura as initials, with counts and accessible names
        $kim = bh_session($d, $p['designer']);
        $html = ts_body(od_page($d, $kim, '/today'));
        t_contains('id="today-brands"', $html);
        t_contains('src="https://cdn.example.com/merc.png"', $html);
        t_not_contains('javascript:alert', $html);
        t_contains('aria-label="The Meridian Collection, 1 job"', $html);
        t_contains('aria-label="Aura Spa, 1 job"', $html);
        t_contains('aria-label="All brands, 2 jobs"', $html);
        t_contains('aria-pressed="true"', $html);
        t_contains('data-slot="avatar"', $html, 'initials badge without a logo');
    },

    'my day brand filter: the signal filters every section to one brand; unknown brands mean All; one html patch' => function (): void {
        [$d, $p, $c] = od_world();
        $j1 = od_send($d, $c['merc'], $p['am'], 'Merc overdue', [['social-static', 1, 'Instagram']], ['traffic' => $p['traffic']]);
        $j2 = od_send($d, $c['aura'], $p['am'], 'Aura overdue', [['print-ad', 1, 'Print']], ['traffic' => $p['traffic']]);
        $d->db->exec('UPDATE assets SET assigned_to = :u WHERE job_id IN (:a, :b)', ['u' => $p['designer'], 'a' => $j1, 'b' => $j2]);
        $kim = bh_session($d, $p['designer']);
        $all = ts_body(bh_ds($d, $kim, 'GET', '/today/body', ['today' => ['brand' => '']]));
        t_contains('Merc overdue', $all);
        t_contains('Aura overdue', $all);
        $aura = (ts_app($kim))(ts_ds_request('GET', '/today/body', $kim, ['today' => ['brand' => $c['aura_brand']]]), $d);
        $html = ts_body($aura, Transport::Html);
        t_contains('id="today-body"', $html);
        t_contains('Aura overdue', $html);
        t_not_contains('Merc overdue', $html);
        t_true(preg_match('/<button(?=[^>]*aria-label="Aura Spa, 1 job")(?=[^>]*aria-pressed="true")[^>]*>/', $html) === 1, 'Aura pressed');
        t_true(preg_match('/<button(?=[^>]*aria-label="All brands, 2 jobs")(?=[^>]*aria-pressed="false")[^>]*>/', $html) === 1, 'All not pressed');
        // a single section keeps the filter too (the 60 second refresh)
        t_not_contains('Merc overdue', ts_body(bh_ds($d, $kim, 'GET', '/today/sections/overdue', ['today' => ['brand' => $c['aura_brand']]])));
        // a brand the Designer has no job on, or garbage, shows everything
        foreach (['brand_nope', "' OR 1=1 --", OD_HOSTILE] as $bad) {
            $b = ts_body(bh_ds($d, $kim, 'GET', '/today/body', ['today' => ['brand' => $bad]]));
            t_contains('Merc overdue', $b, 'all for ' . $bad);
            t_not_contains('<img src=x', $b);
        }
        // the page also takes ?brand= (bookmarkable) and seeds the signal
        $page = ts_body(od_page($d, $kim, '/today', ['brand' => $c['merc_brand']]));
        t_contains('&quot;brand&quot;:&quot;' . $c['merc_brand'] . '&quot;', $page);
        t_not_contains('Aura overdue', $page);
        // owners get the row too, from their own jobs
        t_contains('id="today-brands"', ts_body(od_page($d, bh_session($d, $p['am']), '/today')));
    },

    'filters: assigned-only roles see only their brands and campaigns and no Owner filter; others see all' => function (): void {
        [$d, $p, $c] = od_world();
        $j1 = od_send($d, $c['merc'], $p['am'], 'Merc job', [['social-static', 1, 'Instagram']], ['traffic' => $p['traffic']]);
        $d->db->exec('UPDATE assets SET assigned_to = :u WHERE job_id = :a', ['u' => $p['designer'], 'a' => $j1]);
        foreach (['/jobs', '/jobs/board'] as $path) {
            $kim = ts_body(od_page($d, bh_session($d, $p['designer']), $path));
            t_contains('The Meridian Collection', $kim, $path);
            t_not_contains('Aura Spa', $kim, $path . ': no other brand in the lists');
            t_not_contains('Winter Glow', $kim, $path . ': no other campaign');
            t_not_contains('id="jf-owner"', $kim, $path . ': no Owner filter');
            $dev = ts_body(od_page($d, bh_session($d, $p['dev']), $path));
            t_not_contains('The Meridian Collection', $dev, $path . ': the Developer has no jobs');
            $amy = ts_body(od_page($d, bh_session($d, $p['am']), $path));
            t_contains('Aura Spa', $amy, $path);
            t_contains('id="jf-owner"', $amy, $path);
            $tr = ts_body(od_page($d, bh_session($d, $p['traffic']), $path));
            t_contains('Winter Glow', $tr, $path . ': Traffic sees every job');
        }
    },

    'producer: test result only, and back to checking with a reason, on own jobs' => function (): void {
        [$d, $p, $jobId] = sx_world(1);
        $prd = ts_user($d, 'producer_taylor', Role::Producer);
        $d->db->exec("INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, 'Producer')", ['id' => bin2hex(random_bytes(16)), 'j' => $jobId, 'u' => $prd]);
        $sol = bh_session($d, $p['social']);
        [$a1] = sx_assets($d, $jobId);
        $pid = sx_add($d, $sol, $a1, 'instagram');
        $taylor = bh_session($d, $prd);
        $page = ts_body(od_page($d, $taylor, '/social/jobs/' . $jobId));
        t_contains('pub-' . $pid . '-test_result', $page);
        // ticks everything client-side; only test_result is kept
        $all = ['copy' => true, 'image' => true, 'link' => true, 'hashtags' => true, 'test_result' => true, 'test_result_note' => 'UTM ok'];
        sx_ok(bh_ds($d, $taylor, 'PATCH', '/social/publications/' . $pid . '/checklist', sx_sig($d, $pid, ['cl' => $all])));
        $cl = $d->publications->get($pid)->checklist;
        t_eq(1, $cl->tickedCount());
        t_eq('UTM ok', $cl->entry(\App\Domain\ChecklistItem::TestResult)->note);
        t_contains('data-toast="error"', ts_body(bh_ds($d, $taylor, 'POST', '/social/publications/' . $pid . '/ready', sx_sig($d, $pid))), 'not Ready');
        // Social finishes; Producer sends it back with a reason
        sx_tick_all($d, $sol, $pid);
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/ready', sx_sig($d, $pid)));
        t_eq('ready_to_schedule', sx_stage($d, $jobId));
        t_contains('Say why', ts_body(bh_ds($d, $taylor, 'POST', '/social/publications/' . $pid . '/recheck', sx_sig($d, $pid))));
        $back = ts_body(bh_ds($d, $taylor, 'POST', '/social/publications/' . $pid . '/recheck', sx_sig($d, $pid, ['reason' => 'Test link 404s'])));
        t_contains('back to checking', $back);
        t_eq('checking', $d->publications->get($pid)->status->value);
        t_eq('approved_client', sx_stage($d, $jobId), 'job moves back');
        $row = $d->activity->listByVerb($jobId, 'publication_rechecked')[0];
        t_eq($prd, $row->actorId);
        t_true(in_array($p['social'], $row->data['recipients'], true) && in_array($p['am'], $row->data['recipients'], true), 'Social and the AM');
        // another Producer (not on the job) may not
        $other = bh_session($d, ts_user($d, 'producer_two', Role::Producer));
        t_contains('data-toast="error"', ts_body(bh_ds($d, $other, 'PATCH', '/social/publications/' . $pid . '/checklist', sx_sig($d, $pid))));
    },

    'demo role tasks: COO in demo mode only; up to 3 open sent jobs; idempotent; says when a role has nobody' => function (): void {
        [$d, $p, $c] = od_world();
        $jobs = [];
        foreach (['One', 'Two', 'Three', 'Four'] as $t) {
            $jobs[] = od_send($d, $c['merc'], $p['am'], $t, [['social-static', 1, 'Instagram']], ['traffic' => $p['traffic']]);
        }
        $coo = bh_session($d, $p['coo']);
        t_contains('demo mode', ts_body(bh_ds($d, $coo, 'POST', '/admin/system/demo-role-tasks')), 'refused outside demo mode');
        t_not_contains('Add demo role tasks', ts_body(od_page($d, $coo, '/admin/system')));
        file_put_contents($d->config->demoFlagPath(), '1');   // temp data dir of this test only
        t_contains('Add demo role tasks', ts_body(od_page($d, $coo, '/admin/system')));
        t_contains('data-toast="error"', ts_body(bh_ds($d, bh_session($d, $p['ecd']), 'POST', '/admin/system/demo-role-tasks')), 'ECD refused');
        $d->users->setActive($p['seo'], false, null, $d->clock->now());
        $first = ts_body(bh_ds($d, $coo, 'POST', '/admin/system/demo-role-tasks'), Transport::Html);
        t_contains('No active SEO user exists', $first);
        $count = static fn (string $job): int => (int) $d->db->scalar("SELECT COUNT(*) FROM assets WHERE job_id = :j AND template_id IN ('utm-links', 'campaign-hashtags', 'asset-test-report')", ['j' => $job]);
        t_eq([3, 3, 3, 0], array_map($count, $jobs), 'first three jobs by number');
        $assigned = $d->db->query("SELECT template_id, assigned_to FROM assets WHERE job_id = :j AND template_id IN ('utm-links', 'campaign-hashtags', 'asset-test-report') ORDER BY sort_order", ['j' => $jobs[0]]);
        t_eq([['utm-links', $p['dev']], ['campaign-hashtags', null], ['asset-test-report', $p['producer']]],
            array_map(static fn (array $r): array => [(string) $r['template_id'], $r['assigned_to'] !== null ? (string) $r['assigned_to'] : null], $assigned));
        t_eq($p['dev'], $d->assignments->team($jobs[0])->userFor(Role::Developer), 'empty slot filled');
        t_eq(3, count($d->db->query("SELECT 1 FROM activity WHERE verb = 'demo_role_tasks_added' AND actor_id = :u", ['u' => $p['coo']])));
        $again = ts_body(bh_ds($d, $coo, 'POST', '/admin/system/demo-role-tasks'));
        t_contains('Nothing to add', $again);
        t_eq([3, 3, 3, 0], array_map($count, $jobs), 'idempotent');
        t_eq(3, count($d->db->query("SELECT 1 FROM activity WHERE verb = 'demo_role_tasks_added'")));
        // the Developer now has work on My day and the brand row
        t_contains('id="today-brands"', ts_body(od_page($d, bh_session($d, $p['dev']), '/today')));
        unlink($d->config->demoFlagPath());
    },
    'overrides: legacy jobs past draft (no sent brief) are covered too; drafts are not' => function (): void {
        require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';
        [$d, $p, $c] = od_world();
        $legacy = jf_job($d, 'MERC-500', $c['merc'], 'Legacy job', ['status' => 'In Progress', 'asset' => $p['designer'], 'asset_status' => 'In Progress']);
        $tr = bh_session($d, $p['traffic']);
        t_eq(200, od_page($d, $tr, '/jobs/' . $legacy . '/assets')->status());
        $asset = $d->assets->listByJob($legacy)[0];
        t_contains('Logged as an override', ts_body(bh_ds($d, $tr, 'POST', '/jobs/' . $legacy . '/assets/override',
            ['ova' => ['asset_id' => $asset->id, 'from' => 'In Progress', 'to' => 'Done', 'reason' => 'Delivered by email']])));
        t_eq('Done', $d->assets->listByJob($legacy)[0]->status);
        $coo = bh_session($d, $p['coo']);
        $draft = ts_body(bh_ds($d, bh_session($d, $p['am']), 'POST', '/briefs', ['nb' => ['campaign_id' => $c['merc'], 'title' => 'Draft']]));
        preg_match('#/jobs/([0-9a-f]{32})/brief#', $draft, $m);
        t_eq(403, od_page($d, $coo, '/jobs/' . $m[1] . '/assets')->status(), 'a draft has no assets page');
    },
];

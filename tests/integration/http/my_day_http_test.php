<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\Types\ActivityEntry;

require_once dirname(__DIR__, 2) . '/support/brief_http.php';

const MDH_HOSTILE = '<script>alert("x")</script>\'"&{{x}}';

return [
    'my day: empty state says what to do next, nav has no badge' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $s = bh_session($d, $p['am']);
        $resp = (ts_app($s))(ts_request('GET', '/today'), $d);
        t_eq(200, $resp->status());
        $html = ts_body($resp);
        foreach (['id="today-section-strip"', 'id="today-section-overdue"', 'id="today-section-due-soon"', 'id="today-section-waiting"', 'id="today-section-changed"'] as $id) {
            t_contains($id, $html);
        }
        t_contains('No overdue jobs.', $html);
        t_contains('Create a brief', $html);
        t_contains('Nothing has changed since you last looked.', $html);
        t_contains('Good morning, Am_amy', $html);
        t_not_contains('data-nav-count', $html);
        t_not_contains('Mark all seen', $html);
        // the 60 second refresh asks for the whole body (brand row and every section) with the CSRF header
        t_contains('data-on-interval__duration.60s', $html);
        t_contains('/slash301pm/today/body', $html);
        t_contains('id="today-body"', $html);
        t_contains('X-CSRF-Token', $html);
    },
    'my day: sections, nav badge, section patches and mark all seen' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $d->db->exec("UPDATE briefs SET due_date = '2026-10-05' WHERE job_id = :j", ['j' => $jobId]);
        $d->db->exec("UPDATE jobs SET stage = 'in_progress', status = 'In Progress' WHERE id = :j", ['j' => $jobId]);
        $d->db->exec("UPDATE briefs SET sent_at = '2026-10-01 08:00:00', version_major = 1, version_minor = 0 WHERE job_id = :j", ['j' => $jobId]);
        $d->db->txImmediate(function ($tx) use ($d, $jobId, $p): void {
            $d->activity->append($tx, new ActivityEntry($jobId, $p['traffic'], 'brief_updated', 'job', $jobId, ['recipients' => [$p['am']]]), new DateTimeImmutable('2026-10-09 06:00:00 UTC'));
        });
        $s = bh_session($d, $p['am']);
        $html = ts_body((ts_app($s))(ts_request('GET', '/today'), $d));
        t_contains('Overdue 4 days', $html);
        t_contains('Launch', $html);
        t_contains('/slash301pm/jobs/' . $jobId . '/brief', $html);
        t_contains('Traffic_morgan', $html);
        t_contains('sent a brief update', $html);
        t_contains('data-nav-count="1"', $html);
        t_contains('Mark all seen', $html);
        // one section per request, patched by id, in both transports
        $sec = ts_ds_request('GET', '/today/sections/overdue', $s);
        $sse = ts_body((ts_app($s))($sec, $d));
        t_contains('event: datastar-patch-elements', $sse);
        t_contains('id="today-section-overdue"', $sse);
        $raw = ts_body((ts_app($s))($sec, $d), Transport::Html);
        t_true(str_starts_with(ltrim($raw), '<div') || str_starts_with(ltrim($raw), '<'), 'html transport body is the card');
        t_contains('id="today-section-overdue"', $raw);
        t_eq(404, (ts_app($s))(ts_ds_request('GET', '/today/sections/nope', $s), $d)->status());
        // mark all seen empties the changed section
        $seen = bh_ds($d, $s, 'POST', '/today/seen');
        t_contains('id="today-section-changed"', ts_body($seen));
        t_contains('Nothing has changed since you last looked.', ts_body($seen, Transport::Html));
        t_true($d->myDay->seenAt($p['am']) !== null);
        t_not_contains('sent a brief update', ts_body((ts_app($s))(ts_request('GET', '/today'), $d)));
        // CSRF is required for the write
        $nocsrf = (ts_app($s))(ts_ds_request('POST', '/today/seen', $s, [], false), $d);
        t_contains('toast', ts_body($nocsrf));
    },
    'my day: hostile strings are escaped everywhere' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $h = MDH_HOSTILE;
        $d->db->exec('UPDATE brands SET name = :n', ['n' => 'Brand ' . $h]);
        $d->db->exec('UPDATE campaigns SET name = :n', ['n' => 'Camp ' . $h]);
        $d->db->exec('UPDATE users SET name = :n WHERE id <> :am', ['n' => 'Person ' . $h, 'am' => $p['am']]);
        $d->db->exec('UPDATE users SET name = :n WHERE id = :am', ['n' => $h . ' Amy', 'am' => $p['am']]);
        $d->db->exec("UPDATE briefs SET title = :t, due_date = '2026-10-01' WHERE job_id = :j", ['t' => 'Title ' . $h, 'j' => $jobId]);
        $d->db->exec("UPDATE jobs SET stage = 'waiting', waiting_on = 'am', waiting_reason = :r WHERE id = :j", ['r' => 'Reason ' . $h, 'j' => $jobId]);
        $d->db->txImmediate(function ($tx) use ($d, $jobId, $p, $h): void {
            $d->activity->append($tx, new ActivityEntry($jobId, $p['traffic'], 'weird_' . $h, 'job', $jobId, ['recipients' => [$p['am']]]), new DateTimeImmutable('2026-10-09 06:00:00 UTC'));
        });
        $s = bh_session($d, $p['am']);
        $pages = [ts_body((ts_app($s))(ts_request('GET', '/today'), $d))];
        foreach (['strip', 'overdue', 'due-soon', 'waiting', 'changed'] as $k) {
            $pages[] = ts_body((ts_app($s))(ts_ds_request('GET', '/today/sections/' . $k, $s), $d));
            $pages[] = ts_body((ts_app($s))(ts_ds_request('GET', '/today/sections/' . $k, $s), $d), Transport::Html);
        }
        foreach ($pages as $i => $html) {
            t_not_contains($h, $html, "page $i raw hostile string");
            t_not_contains('<script>alert', $html, "page $i script injection");
        }
        t_contains('Title &lt;script&gt;', $pages[0]);
        t_eq(2, substr_count(strtolower($pages[0]), '<script'), 'only the shell scripts (theme.js, datastar.js)');
    },
    'my day: a user without a session is sent to log in' => function (): void {
        $d = ts_deps();
        $s = new App\Http\MemorySession(['csrf_token' => 'x']);
        $resp = (ts_app($s))(ts_request('GET', '/today'), $d);
        t_true($resp->status() === 302 || $resp->status() === 303, 'redirects');
        $resp = (ts_app($s))(ts_request('GET', '/today/sections/overdue'), $d);
        t_true($resp->status() !== 200 || !str_contains(ts_body($resp), 'today-section'), 'no data without login');
    },
];

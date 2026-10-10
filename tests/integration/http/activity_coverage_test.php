<?php
declare(strict_types=1);

use App\Domain\JobQuery;
use App\Domain\Role;
use App\Http\Deps;
use App\Http\MemorySession;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

/**
 * Audit trail completeness (docs/audit-trail.md). Every POST, PUT, PATCH and
 * DELETE route in app/routes.php is either:
 * - COVERED: a case below runs it through the full Kernel and checks that an
 *   activity row with the expected verb and the session actor was written (or,
 *   for autosave edits, brought up to date) by that request, or
 * - EXEMPT: listed in AC_EXEMPT with the reason it writes no activity.
 * A new write route fails this test until it is added to one of the two.
 */
const AC_EXEMPT = [
    'POST /today/seen' => 'Personal "last seen" stamp for My day (user_seen); changes no shared data.',
    'POST /login' => 'Sign-in. Failures are recorded in login_attempts for the rate limit; a success only starts a session.',
    'POST /logout' => 'Ends the session only.',
    'POST /demo-login' => 'Demo mode only (owner switch); swaps the session user and writes nothing.',
    'POST /admin/system/migrate' => 'Recorded in schema_migrations (version, sha256, applied_at) with a backup in data/backups.',
    'POST /views' => 'Saved views are personal UI state (filters, sort, columns); no job data changes.',
    'PATCH /views/{id}' => 'Saved views are personal UI state (filters, sort, columns); no job data changes.',
    'DELETE /views/{id}' => 'Saved views are personal UI state (filters, sort, columns); no job data changes.',
    'POST /system/spike/method' => 'Diagnostics page (COO/ECD): echoes the method, writes nothing.',
    'PUT /system/spike/method' => 'Diagnostics page (COO/ECD): echoes the method, writes nothing.',
    'PATCH /system/spike/method' => 'Diagnostics page (COO/ECD): echoes the method, writes nothing.',
    'DELETE /system/spike/method' => 'Diagnostics page (COO/ECD): echoes the method, writes nothing.',
];

/** @return list<string> every write route pattern in app/routes.php */
function ac_write_routes(): array
{
    $out = [];
    foreach (require dirname(__DIR__, 3) . '/app/routes.php' as $entry) {
        $pattern = $entry[0];
        $method = strtoupper((string) strtok($pattern, ' '));
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $out[] = $pattern;
        }
    }
    return $out;
}

/** id => data_json of every activity row. @return array<string,string> */
function ac_snapshot(Deps $d): array
{
    $out = [];
    foreach ($d->db->query('SELECT id, data_json FROM activity') as $r) {
        $out[(string) $r['id']] = (string) ($r['data_json'] ?? '');
    }
    return $out;
}

/**
 * Run $do and return "verb by actor" for every activity row it added or
 * brought up to date. @return list<string>
 */
function ac_written(Deps $d, callable $do): array
{
    $before = ac_snapshot($d);
    $do();
    $out = [];
    foreach ($d->db->query('SELECT id, verb, actor_id, data_json FROM activity ORDER BY rowid') as $r) {
        $id = (string) $r['id'];
        if (!isset($before[$id]) || $before[$id] !== (string) ($r['data_json'] ?? '')) {
            $out[] = $r['verb'] . ' by ' . $r['actor_id'];
        }
    }
    return $out;
}

function ac_ds(Deps $d, MemorySession $s, string $method, string $path, array $signals = []): string
{
    return ts_body(bh_ds($d, $s, $method, $path, $signals));
}

/**
 * Covered routes, run in order on one seeded world.
 * @return array<string,array{0:string,1:string,2:callable}> pattern => [expected verb, actor key, request]
 */
function ac_cases(Deps $d, array $c, array $p, string $jobId, string $legacyJob): array
{
    $amy = bh_session($d, $p['am']);
    $ben = bh_session($d, $p['am2']);
    $coo = bh_session($d, $p['coo']);
    $briefId = static fn (): string => $d->briefs->getByJob($jobId)->id;
    $lines = static fn (): array => $d->briefAssets->listByBrief($briefId());
    $rv = static fn (): int => $d->briefs->getByJob($jobId)->rowVersion;
    $line = static fn (int $i, array $f): array => ['dl' => ['ln_' . $lines()[$i]->id => $f + ['template_id' => 'social-static', 'label' => 'Post', 'qty' => 1, 'channel' => 'Instagram', 'size_format' => '1080x1350', 'specs' => '']]];
    $q = JobQuery::defaults('board')->toSignalState();
    return [
        'POST /briefs' => ['job_created', 'am', static fn () => ac_ds($d, $amy, 'POST', '/briefs', ['nb' => ['campaign_id' => $c['campaign'], 'title' => 'Second']])],
        'PATCH /jobs/{id}/brief' => ['brief_edited', 'am', static fn () => ac_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => [
            'title' => 'Launch', 'due_date' => '2026-10-20', 'creative_direction' => 'Warm and gold.', 'row_version' => $rv()]])],
        'POST /jobs/{id}/brief/assets' => ['deliverable_added', 'am', static function () use ($d, $amy, $jobId): void {
            ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]);
            ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]);
        }],
        'PATCH /jobs/{id}/brief/assets/{aid}' => ['deliverable_updated', 'am', static fn () => ac_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . $lines()[0]->id, $line(0, ['qty' => 2]))],
        'POST /jobs/{id}/brief/assets/order' => ['deliverables_reordered', 'am', static fn () => ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets/order', ['reorder' => ['line_id' => $lines()[1]->id, 'dir' => 'up']])],
        'DELETE /jobs/{id}/brief/assets/{aid}' => ['deliverable_removed', 'am', static fn () => ac_ds($d, $amy, 'DELETE', '/jobs/' . $jobId . '/brief/assets/' . $lines()[1]->id)],
        'POST /jobs/{id}/assignments/{role}' => ['assigned_to_job', 'am', static fn () => ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]])],
        'POST /jobs/{id}/brief/send' => ['brief_sent', 'am', static fn () => ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/send')],
        'PATCH /jobs/{id}/fields/{field}' => ['brief_edited', 'coo', static fn () => ac_ds($d, $coo, 'PATCH', '/jobs/' . $jobId . '/fields/title', ['edit' => [
            'value' => 'Launch v2', 'row_version' => $d->jobs->get($jobId)->rowVersion, 'brief_rv' => $rv()]])],
        'POST /jobs/{id}/brief/update' => ['brief_updated', 'am', static fn () => ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'patch', 'note' => 'New title']])],
        'POST /jobs/{id}/transition' => ['job_waiting', 'am', static fn () => ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'Images']])],
        'POST /jobs/{id}/move' => ['job_resumed', 'am', static fn () => ac_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/move', ['move' => [
            'to' => 'briefed', 'row_version' => $d->jobs->get($jobId)->rowVersion, 'reason' => '', 'waiting_on' => '', 'page' => 'board', 'sheet' => false, 'job_id' => $jobId], 'q' => $q])],
        'POST /jobs/{id}/claim-am' => ['assigned_to_job', 'am2', static fn () => ac_ds($d, $ben, 'POST', '/jobs/' . $legacyJob . '/claim-am')],
        'POST /campaigns' => ['campaign_created', 'am', static fn () => ac_ds($d, $amy, 'POST', '/campaigns', ['nc' => ['brand_id' => $c['brand'], 'name' => 'Spring Menu', 'description' => '']])],
        'POST /admin/users' => ['user_created', 'coo', static fn () => ac_ds($d, $coo, 'POST', '/admin/users', ['new_user' => [
            'name' => 'New Person', 'username' => 'new.person', 'email' => '', 'role' => 'AM', 'password' => 'a long first password']])],
        'POST /admin/users/{id}/reset' => ['password_reset', 'coo', static fn () => ac_ds($d, $coo, 'POST', '/admin/users/' . $p['designer'] . '/reset')],
        'POST /admin/users/{id}/deactivate' => ['user_deactivated', 'coo', static fn () => ac_ds($d, $coo, 'POST', '/admin/users/' . $p['designer'] . '/deactivate')],
        'POST /admin/users/{id}/activate' => ['user_activated', 'coo', static fn () => ac_ds($d, $coo, 'POST', '/admin/users/' . $p['designer'] . '/activate')],
        'POST /account/password' => ['password_changed', 'coo', static fn () => ac_ds($d, $coo, 'POST', '/account/password', ['pw' => [
            'current' => 'correct horse battery', 'new' => 'another long password', 'confirm' => 'another long password']])],
    ];
}

return [
    'audit trail: every write route is covered by an activity check or exempt with a reason' => function (): void {
        [$d, $c, $p, $jobId] = bh_world();
        $legacyJob = jf_job($d, 'MERC-090', $c['campaign'], 'Legacy job without AM', ['status' => 'In Progress']);
        $cases = ac_cases($d, $c, $p, $jobId, $legacyJob);
        $routes = ac_write_routes();
        foreach ($routes as $pattern) {
            $covered = isset($cases[$pattern]);
            $exempt = isset(AC_EXEMPT[$pattern]);
            t_true($covered || $exempt, "write route $pattern has no activity check and no exemption (add it to ac_cases or AC_EXEMPT, and to docs/audit-trail.md)");
            t_true(!($covered && $exempt), "$pattern is both covered and exempt");
        }
        foreach (array_merge(array_keys($cases), array_keys(AC_EXEMPT)) as $pattern) {
            t_true(in_array($pattern, $routes, true), "stale entry: $pattern is not a write route in app/routes.php");
        }
        foreach (AC_EXEMPT as $pattern => $why) {
            t_true(strlen($why) > 10, "exemption for $pattern needs a reason");
        }
        foreach ($cases as $pattern => [$verb, $actor, $do]) {
            $written = ac_written($d, $do);
            t_true(in_array($verb . ' by ' . $p[$actor], $written, true),
                "$pattern: expected an activity row '$verb' by the session user; written: " . (implode(', ', $written) ?: 'nothing'));
        }
    },
    'audit trail: docs/audit-trail.md lists every write route' => function (): void {
        $doc = (string) file_get_contents(dirname(__DIR__, 3) . '/docs/audit-trail.md');
        foreach (ac_write_routes() as $pattern) {
            t_contains('`' . $pattern . '`', $doc, 'docs/audit-trail.md');
        }
    },
    'audit trail: autosave bursts share one row; another person or an older row starts a new one' => function (): void {
        [$d, , $p, $jobId] = bh_world();
        $amy = bh_session($d, $p['am']);
        $save = static function (string $title) use ($d, $amy, $jobId): void {
            ac_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => $title, 'row_version' => $d->briefs->getByJob($jobId)->rowVersion]]);
        };
        $save('One');
        $save('Two');
        $d->db->exec("UPDATE briefs SET due_date = '2026-10-20' WHERE job_id = :j", ['j' => $jobId]);
        ac_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['creative_direction' => 'x', 'row_version' => $d->briefs->getByJob($jobId)->rowVersion]]);
        $rows = $d->activity->listByVerb($jobId, 'brief_edited');
        t_eq(1, count($rows), 'one row for the burst');
        t_eq(3, $rows[0]->data['edits']);
        t_eq(['title', 'creative_direction'], $rows[0]->data['fields']);
        // a different editor gets a row of their own
        $coo = bh_session($d, $p['coo']);
        ac_ds($d, $coo, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'Three', 'row_version' => $d->briefs->getByJob($jobId)->rowVersion]]);
        t_eq(2, count($d->activity->listByVerb($jobId, 'brief_edited')));
        // older than the window: new row even for the same person
        $d->db->exec("UPDATE activity SET created_at = '2026-10-08 00:00:00'");
        $save('Four');
        t_eq(3, count($d->activity->listByVerb($jobId, 'brief_edited')));
        // admin writes have no job and never reach My day (no user ids in data)
        $u = ts_user($d, 'someone', Role::Designer);
        ac_ds($d, bh_session($d, $p['coo']), 'POST', '/admin/users/' . $u . '/reset');
        $last = $d->activity->latest();
        t_eq('password_reset', $last?->verb);
        t_eq(null, $last?->jobId);
        t_eq($u, $last?->entityId);
        t_not_contains($u, json_encode($last?->data, JSON_THROW_ON_ERROR));
    },
];

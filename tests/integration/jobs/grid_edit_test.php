<?php
declare(strict_types=1);

use App\Config\Transport;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

/** PATCH one grid cell as $user with the row versions the grid currently shows. */
function ge_patch($d, string $userId, string $jobId, string $field, string $value, array $extra = [], ?int $rv = null, ?int $brv = null): string
{
    $job = $d->jobs->get($jobId);
    $brief = $d->briefs->getByJob($jobId);
    $edit = ['value' => $value, 'row_version' => $rv ?? $job->rowVersion, 'brief_rv' => $brv ?? $brief->rowVersion] + $extra;
    return ts_body(bh_ds($d, bh_session($d, $userId), 'PATCH', '/jobs/' . $jobId . '/fields/' . $field, ['edit' => $edit, 'q' => ['cols' => ['job_number', 'title', 'stage', 'traffic', 'due']]]));
}

return [
    'grid edit: title and due date on a draft write the brief and the legacy columns together' => function (): void {
        [$d, $p, , $j] = jf_world();
        $id = $j['created_no_am'];
        $body = ge_patch($d, $p['am'], $id, 'title', '  Menu cards v2  ');
        t_contains('id="row-' . $id . '"', $body);
        t_contains('Menu cards v2', $body);
        t_contains('Saved.', $body);
        t_eq('Menu cards v2', $d->briefs->getByJob($id)->title);
        t_eq('Menu cards v2', $d->jobs->get($id)->title, 'draft: mirrored to jobs');
        $body = ge_patch($d, $p['am'], $id, 'due_date', '2026-10-28');
        t_contains('28 Oct 2026', $body);
        t_eq('2026-10-28', $d->briefs->getByJob($id)->dueDate);
        t_eq('2026-10-28', $d->jobs->get($id)->deliveryDate, 'legacy delivery_date follows');
        ge_patch($d, $p['am'], $id, 'hours_estimate', '7.5');
        t_eq(7.5, $d->briefs->getByJob($id)->hoursEstimate);
    },
    'grid edit: on a sent brief the edit goes to the working copy only' => function (): void {
        [$d, $p, , $j] = jf_world();
        $id = $j['mine_overdue'];
        t_true($d->briefs->getByJob($id)->isSent());
        $body = ge_patch($d, $p['am'], $id, 'due_date', '2026-10-30');
        t_contains('Saved to the working copy', $body);
        t_eq('2026-10-30', $d->briefs->getByJob($id)->dueDate);
        t_eq('2026-10-05', $d->jobs->get($id)->deliveryDate, 'legacy keeps the sent date until Send update');
        t_true($d->briefs->getByJob($id)->hasUnsentChanges);
        t_eq(1, count($d->briefs->versions($d->briefs->getByJob($id)->id)), 'the imported 1.0.0 baseline was captured first');
        // editing it back clears the unsent flag (reconcile)
        ge_patch($d, $p['am'], $id, 'due_date', '2026-10-05');
        t_true(!$d->briefs->getByJob($id)->hasUnsentChanges);
    },
    'grid edit: a legacy write in between gives the fresh row and a conflict toast' => function (): void {
        [$d, $p, , $j] = jf_world();
        $id = $j['created_no_am'];
        $rv = $d->jobs->get($id)->rowVersion;
        $brv = $d->briefs->getByJob($id)->rowVersion;
        // legacy update_job: plain UPDATE (the 0002 trigger bumps row_version)
        $d->db->exec("UPDATE jobs SET title = 'Changed in legacy' WHERE id = :id", ['id' => $id]);
        $body = ge_patch($d, $p['am'], $id, 'title', 'Mine', [], $rv, $brv);
        t_contains('changed by someone else', $body);
        t_contains('id="row-' . $id . '"', $body, 'the fresh row comes back');
        t_eq('Changed in legacy', $d->jobs->get($id)->title, 'nothing overwritten');
        // a brief-editor save in another tab (brief row_version) also counts
        $rv = $d->jobs->get($id)->rowVersion;
        $d->db->exec('UPDATE briefs SET row_version = row_version + 1 WHERE job_id = :id', ['id' => $id]);
        t_contains('changed by someone else', ge_patch($d, $p['am'], $id, 'title', 'Mine', [], $rv, $brv));
    },
    'grid edit: forbidden, invalid and unknown fields change nothing (table)' => function (): void {
        [$d, $p, $c, $j] = jf_world();
        $cases = [
            ['another AM\'s job', $p['am'], $j['other_am'], 'title', 'Hijack', 'You cannot change the title'],
            ['Traffic is outside the beta (BetaGate)', $p['traffic'], $j['mine_overdue'], 'title', 'Hijack', '/legacy/'],
            ['COO may not pick a Designer as Traffic', $p['coo'], $j['mine_overdue'], 'traffic', $p['designer'], 'Only a Traffic'],
            ['waiting reason while not waiting', $p['am'], $j['mine_overdue'], 'waiting_reason', 'x', 'Only a waiting job'],
            ['closed job', $p['am'], $j['done'], 'title', 'x', 'can no longer change'],
            ['empty title', $p['am'], $j['mine_today'], 'title', '   ', 'cannot be empty'],
            ['bad date', $p['am'], $j['mine_today'], 'due_date', '2026-02-30', 'real dates'],
            ['bad hours', $p['am'], $j['mine_today'], 'hours_estimate', 'lots', 'must be a number'],
            ['campaign of another brand', $p['am'], $j['mine_today'], 'campaign_id', $c['aura'], 'same brand'],
            ['unknown field', $p['am'], $j['mine_today'], 'status', 'Done', 'cannot be edited here'],
            ['budget is not a grid field', $p['am'], $j['mine_today'], 'budget', '1', 'cannot be edited here'],
            ['Traffic slot with a non-Traffic user', $p['am'], $j['mine_today'], 'traffic', $p['designer'], 'Only a Traffic'],
        ];
        foreach ($cases as [$name, $who, $id, $field, $value, $want]) {
            $before = [$d->jobs->get($id), $d->briefs->getByJob($id)];
            $body = ge_patch($d, $who, $id, $field, $value);
            t_contains($want, $body, $name);
            t_not_contains('id="row-', $body, $name . ': no row patch, the editor stays open');
            t_eq($before[0]->rowVersion, $d->jobs->get($id)->rowVersion, $name . ': job unchanged');
            t_eq($before[1]->rowVersion, $d->briefs->getByJob($id)->rowVersion, $name . ': brief unchanged');
        }
        // the editor itself is refused too
        t_contains('You cannot change the title', ts_body(bh_ds($d, bh_session($d, $p['am']), 'GET', '/jobs/' . $j['other_am'] . '/cells/title/edit')));
        t_contains('<input type="date"', ts_body(bh_ds($d, bh_session($d, $p['am']), 'GET', '/jobs/' . $j['mine_today'] . '/cells/due_date/edit')));
    },
    'grid edit: waiting fields, campaign within the brand, Traffic and AM slots' => function (): void {
        [$d, $p, $c, $j] = jf_world();
        $id = $j['mine_today'];
        bh_ds($d, bh_session($d, $p['am']), 'POST', '/jobs/' . $id . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'Logo files']]);
        t_eq('waiting', $d->jobs->get($id)->stage->value);
        t_contains('<select', ts_body(bh_ds($d, bh_session($d, $p['am']), 'GET', '/jobs/' . $id . '/cells/waiting_reason/edit')));
        $body = ge_patch($d, $p['am'], $id, 'waiting_reason', 'Logo files and fonts', ['waiting_on' => 'third_party']);
        t_contains('on third party: Logo files and fonts', $body);
        $job = $d->jobs->get($id);
        t_eq('third_party', $job->waitingOn->value);
        t_eq('Logo files and fonts', $job->waitingReason);
        t_eq(1, count($d->activity->listByVerb($id, 'job_waiting_updated')));
        // the COO may edit any job's waiting reason (matrix Y)
        ge_patch($d, $p['coo'], $id, 'waiting_reason', 'Fonts only');
        t_eq('Fonts only', $d->jobs->get($id)->waitingReason);
        // campaign within the brand
        ge_patch($d, $p['am'], $id, 'campaign_id', $c['merc2']);
        t_eq($c['merc2'], $d->briefs->getByJob($id)->campaignId);
        // slots through AssignmentStore
        ge_patch($d, $p['am'], $id, 'traffic', $p['traffic2']);
        t_eq($p['traffic2'], $d->assignments->team($id)->userFor(App\Domain\Role::Traffic));
        ge_patch($d, $p['am'], $id, 'traffic', '');
        t_eq(null, $d->assignments->team($id)->userFor(App\Domain\Role::Traffic));
        // html transport: row + toast is one outer patch kind
        $s = bh_session($d, $p['am']);
        $rv = $d->jobs->get($id)->rowVersion;
        ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $id . '/fields/waiting_on', ['edit' => ['value' => 'client', 'row_version' => $rv, 'brief_rv' => 0]]), Transport::Html);
        t_eq('client', $d->jobs->get($id)->waitingOn->value);
    },
];

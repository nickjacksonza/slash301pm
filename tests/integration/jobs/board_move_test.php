<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\JobQuery;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

/** POST /jobs/{id}/move from the board (default board stages) or the grid. */
function bm_move($d, string $userId, string $jobId, string $to, array $o = [], Transport $t = Transport::Sse): string
{
    $page = $o['page'] ?? 'board';
    $move = ['to' => $to, 'row_version' => $o['rv'] ?? $d->jobs->get($jobId)->rowVersion, 'reason' => $o['reason'] ?? '', 'waiting_on' => $o['on'] ?? '',
        'page' => $page, 'sheet' => $o['sheet'] ?? false, 'job_id' => $jobId];
    $q = JobQuery::defaults($page)->toSignalState();
    return ts_body(bh_ds($d, bh_session($d, $userId), 'POST', '/jobs/' . $jobId . '/move', ['move' => $move, 'q' => $q]), $t);
}

return [
    'board move: allowed moves change the stage and re-render both columns (table)' => function (): void {
        [$d, $p, , $j] = jf_world();
        $id = $j['mine_overdue'];
        $body = bm_move($d, $p['am'], $id, 'waiting', ['reason' => 'Hero image', 'on' => 'client']);
        t_contains('In progress to Waiting', $body);
        t_contains('id="col-in_progress"', $body);
        t_contains('id="col-waiting"', $body);
        t_contains('id="card-' . $id . '"', $body);
        $job = $d->jobs->get($id);
        t_eq('waiting', $job->stage->value);
        t_eq('Waiting', $job->status, 'legacy status written');
        t_eq('Hero image', $job->waitingReason);
        $act = $d->activity->listByVerb($id, 'job_waiting');
        t_eq(1, count($act));
        t_eq('board', $act[0]->data['via']);
        // resume only to the stored stage
        t_contains('resumes to in progress', bm_move($d, $p['am'], $id, 'briefed'));
        t_eq('waiting', $d->jobs->get($id)->stage->value);
        t_contains('Waiting to In progress', bm_move($d, $p['am'], $id, 'in_progress'));
        t_eq('in_progress', $d->jobs->get($id)->stage->value);
        // hold, then cancel from hold, then archive (closed columns hidden: only the from column comes back)
        bm_move($d, $p['am'], $id, 'on_hold', ['reason' => 'Paused by client']);
        t_eq('on_hold', $d->jobs->get($id)->stage->value);
        $body = bm_move($d, $p['am'], $id, 'cancelled', ['reason' => 'Launch dropped']);
        t_eq('cancelled', $d->jobs->get($id)->stage->value);
        t_contains('id="col-on_hold"', $body);
        t_not_contains('id="col-cancelled"', $body, 'a hidden column is not patched');
        bm_move($d, $p['am'], $id, 'archived');
        t_eq('archived', $d->jobs->get($id)->stage->value);
    },
    'board move: refused moves change nothing and snap back with a toast (table)' => function (): void {
        [$d, $p, , $j] = jf_world();
        $cases = [
            ['draft to briefed goes through Send', $p['am'], $j['created_no_am'], 'briefed', [], 'goes to Traffic through Send'],
            ['no named move', $p['am'], $j['mine_overdue'], 'done', [], 'cannot move from in progress to done'],
            ['reason required', $p['am'], $j['mine_overdue'], 'waiting', ['on' => 'client'], 'Say what the job is waiting for'],
            ['waiting on required', $p['am'], $j['mine_overdue'], 'waiting', ['reason' => 'x'], 'Choose who the job is waiting on'],
            ['someone else\'s job', $p['am'], $j['other_am'], 'on_hold', ['reason' => 'x'], 'You cannot put on hold'],
            ['not available yet (approve internally)', $p['coo'], $j['aura_review'], 'approved_internal', [], 'not available yet'],
            ['unknown stage', $p['am'], $j['mine_overdue'], 'Done; DROP', [], 'Unknown stage'],
            ['stale row_version', $p['am'], $j['mine_overdue'], 'on_hold', ['reason' => 'x', 'rv' => 0], 'changed by someone else'],
        ];
        foreach ($cases as [$name, $who, $id, $to, $o, $want]) {
            $before = $d->jobs->get($id);
            $body = bm_move($d, $who, $id, $to, $o);
            t_contains($want, $body, $name);
            t_contains('id="col-' . $before->stage->value . '"', $body, $name . ': the from column is re-rendered');
            $after = $d->jobs->get($id);
            t_eq($before->stage, $after->stage, $name . ': stage unchanged');
            t_eq($before->rowVersion, $after->rowVersion, $name . ': row unchanged');
        }
        $send = bm_move($d, $p['am'], $j['created_no_am'], 'briefed');
        t_contains('/slash301pm/jobs/' . $j['created_no_am'] . '/brief', $send, 'the refusal links to the brief');
        t_contains('Open the brief', $send);
    },
    'board move: a legacy write between drag and drop is a conflict' => function (): void {
        [$d, $p, , $j] = jf_world();
        $id = $j['mine_today'];
        $rv = $d->jobs->get($id)->rowVersion;
        $d->db->exec("UPDATE jobs SET status = 'In Review' WHERE id = :id", ['id' => $id]);
        $body = bm_move($d, $p['am'], $id, 'on_hold', ['reason' => 'x', 'rv' => $rv]);
        t_contains('changed by someone else', $body);
        t_eq('in_review', $d->jobs->get($id)->stage->value);
        t_contains('id="col-in_review"', $body, 'the card shows up where it really is');
    },
    'board move: from the grid page or the sheet the row and the sheet come back' => function (): void {
        [$d, $p, , $j] = jf_world();
        $id = $j['mine_overdue'];
        $body = bm_move($d, $p['am'], $id, 'on_hold', ['reason' => 'x', 'page' => 'jobs', 'sheet' => true]);
        t_contains('id="row-' . $id . '"', $body);
        t_contains('id="sheet"', $body);
        t_not_contains('id="col-', $body);
        // html transport: row + sheet + toast are all outer patches by id
        $html = bm_move($d, $p['am'], $id, 'in_progress', ['page' => 'jobs', 'sheet' => true], Transport::Html);
        t_contains('id="row-' . $id . '"', $html);
        t_eq('in_progress', $d->jobs->get($id)->stage->value);
        bm_move($d, $p['am'], $id, 'on_hold', ['reason' => 'x'], Transport::Html);
        t_eq('on_hold', $d->jobs->get($id)->stage->value);
    },
];

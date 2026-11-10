<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/support/review_fx.php';

use App\Domain\JobQuery;
use App\Http\Deps;

/**
 * Owner decision 2026-10-13: a rejection on a client-approved job (stage
 * approved_client, or a Social stage after it) reopens the job. It moves back
 * to in_progress through Transitions (send back, with the rejection as the
 * reason), clears the CD and ECD approvals and the ready and sent stamps as
 * every rejection does, mirrors the legacy status to 'In Progress' and logs
 * job_send_back. A paused job that would resume to a client-approved stage
 * refuses the rejection until it is resumed.
 */

/** A one-post job walked through the review, sent to the client and approved by the client in legacy. @return array{0:Deps,1:array<string,string>,2:string,3:string} */
function rr_client_approved(): array
{
    [$d, $p, $job] = rx_world(1);
    [$a1] = rx_posts($d, $job);
    $cd = bh_session($d, $p['cd']);
    sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
    sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.jpg'], true));
    sx_ok(rx_rev($d, $cd, $job, $a1, 'approved'));
    sx_ok(rx_job($d, $cd, $job, 'approve'));
    sx_ok(rx_job($d, bh_session($d, $p['ecd']), $job, 'ecd-approve'));
    sx_ok(rx_job($d, bh_session($d, $p['am']), $job, 'ready'));
    sx_ok(rx_job($d, bh_session($d, $p['am']), $job, 'send'));
    sx_legacy_approve($d, $job);
    t_eq('approved_client', rx_stage($d, $job));
    return [$d, $p, $job, $a1];
}

/** A board drop as $userId; returns the response body. */
function rr_drop(Deps $d, string $userId, string $jobId, string $to, string $reason = ''): string
{
    $move = ['to' => $to, 'row_version' => $d->jobs->get($jobId)->rowVersion, 'reason' => $reason, 'waiting_on' => $to === 'waiting' ? 'client' : '',
        'page' => 'board', 'sheet' => false, 'job_id' => $jobId];
    return ts_body(bh_ds($d, bh_session($d, $userId), 'POST', '/jobs/' . $jobId . '/move', ['move' => $move, 'q' => JobQuery::defaults('board')->toSignalState()]));
}

return [
    'reviews: a rejection reopens a client-approved job, clears the approvals and mirrors the legacy status' => function (): void {
        [$d, $p, $job, $a1] = rr_client_approved();
        t_true($d->reviews->jobReview($job)->sentToClient(), 'sent before');
        // The board does not reopen it by drag: that is a rejection on the review page.
        $body = rr_drop($d, $p['am'], $job, 'in_progress', 'Client changed their mind');
        t_contains('data-toast="error"', $body);
        t_contains('/review', $body);
        t_eq('approved_client', rx_stage($d, $job), 'the drag changed nothing');

        $body = ts_body(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'rejected_with_feedback', 'copy', 'Client wants the date in the first line.'));
        t_contains('job approvals were cleared', $body);
        t_eq('in_progress', rx_stage($d, $job));
        t_eq('In Progress', sx_status($d, $job), 'legacy status mirror');
        t_eq('In Progress', (string) $d->db->scalar('SELECT status FROM assets WHERE id = :a', ['a' => $a1]), 'legacy asset status');
        $jr = $d->reviews->jobReview($job);
        t_true(!$jr->cdApproved() && !$jr->ecdApproved() && !$jr->readyForClient() && !$jr->sentToClient() && $jr->ecdRequestedAt === null, 'approvals and stamps cleared');
        $rej = $d->activity->listByVerb($job, 'asset_part_rejected');
        t_eq(true, $rej[count($rej) - 1]->data['cleared_approvals']);
        t_eq([$p['copy']], rx_to($d, $job, 'asset_part_rejected'), 'the copy maker is told');
        $moves = $d->activity->listByVerb($job, 'job_send_back');
        t_eq(1, count($moves), 'one stage row');
        $m = $moves[0];
        t_eq(['approved_client', 'in_progress', 'send_back', 'review', $p['cd']], [$m->data['from'], $m->data['to'], $m->data['action'], $m->data['via'], $m->actorId]);
        t_contains('Client wants the date', (string) $m->data['reason']);
        // The reopened job runs the review again from round 2.
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Opens 12 October.'], true));
        t_eq(2, $d->reviews->asset($job, $a1)->round);
        t_eq('in_review', rx_stage($d, $job));
    },

    'reviews: a rejection reopens a job in a Social stage too' => function (): void {
        [$d, $p, $job, $a1] = rr_client_approved();
        $d->db->exec("UPDATE jobs SET stage = 'scheduled', row_version = row_version + 1 WHERE id = :j", ['j' => $job]);
        sx_ok(rx_rev($d, bh_session($d, $p['ecd']), $job, $a1, 'rejected', 'media'));
        t_eq('in_progress', rx_stage($d, $job));
        t_eq('In Progress', sx_status($d, $job));
        t_eq('scheduled', $d->activity->listByVerb($job, 'job_send_back')[0]->data['from']);
        t_true(!$d->reviews->jobReview($job)->internallyApproved());
    },

    'reviews: an approval on a client-approved job moves nothing' => function (): void {
        [$d, $p, $job, $a1] = rr_client_approved();
        // Reopen the copy by rejecting it, then put the job back to approved_client as legacy would after a new client approval.
        sx_ok(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'rejected', 'copy'));
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'New copy']));
        $d->db->exec("UPDATE jobs SET status = 'Approved (External)' WHERE id = :j", ['j' => $job]);
        t_eq('approved_client', rx_stage($d, $job));
        sx_ok(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'approved', 'copy'));
        t_eq('approved_client', rx_stage($d, $job), 'only a rejection reopens');
    },

    'reviews: a paused job that resumes to Approved (client) refuses a rejection until it is resumed' => function (): void {
        [$d, $p, $job, $a1] = rr_client_approved();
        t_true(!str_contains(rr_drop($d, $p['am'], $job, 'on_hold', 'Client travelling'), 'data-toast="error"'), 'put on hold');
        t_eq('on_hold', rx_stage($d, $job));
        rx_err(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'rejected', 'copy'), 'Resume the job');
        t_eq('approved', $d->reviews->asset($job, $a1)->copyState->value, 'nothing changed');
        t_true($d->reviews->jobReview($job)->sentToClient(), 'stamps kept');
        t_true(!str_contains(rr_drop($d, $p['am'], $job, 'approved_client'), 'data-toast="error"'), 'resumed');
        t_eq('approved_client', rx_stage($d, $job));
        sx_ok(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'rejected', 'copy'));
        t_eq('in_progress', rx_stage($d, $job));
    },
];

<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/support/review_fx.php';

use App\Domain\JobQuery;

/** Pause or resume a job from the board as $userId ($to: waiting | on_hold | resume target). */
function rp_move(\App\Http\Deps $d, string $userId, string $jobId, string $to, string $reason = ''): void
{
    $move = ['to' => $to, 'row_version' => $d->jobs->get($jobId)->rowVersion, 'reason' => $reason, 'waiting_on' => $to === 'waiting' ? 'client' : '',
        'page' => 'board', 'sheet' => false, 'job_id' => $jobId];
    sx_ok(bh_ds($d, bh_session($d, $userId), 'POST', '/jobs/' . $jobId . '/move', ['move' => $move, 'q' => JobQuery::defaults('board')->toSignalState()]));
}

/**
 * Reviews on a paused job. The ECD approval is what moves a job to Approved
 * (internal), and a rejection is what sends it back to In progress; neither
 * stage move can run while the job waits or is on hold, so:
 * - the ECD approval is refused until the job is resumed (otherwise the job
 *   resumes to its old stage with both approvals recorded and nothing can
 *   ever move it to Approved (internal): the board refuses that drag);
 * - a rejection is refused while the job would resume to In review,
 *   Approved (internal) or (owner decision 2026-10-13) a client-approved
 *   stage (otherwise it resumes there with a rejected post, and the legacy
 *   app sees 'Approved (Internal)' and allows approve_client); the
 *   client-approved case is in review_reopen_test.php.
 */
return [
    'reviews: the ECD approval waits until a paused job is resumed' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        $cd = bh_session($d, $p['cd']);
        $ecd = bh_session($d, $p['ecd']);
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
        sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.jpg'], true));
        sx_ok(rx_rev($d, $cd, $job, $a1, 'approved'));
        sx_ok(rx_job($d, $cd, $job, 'approve'));
        t_eq('in_review', rx_stage($d, $job));
        rp_move($d, $p['am'], $job, 'waiting', 'Client call');
        t_eq('waiting', rx_stage($d, $job));
        $page = ts_body((ts_app($ecd))(ts_request('GET', '/jobs/' . $job . '/review'), $d));
        t_true(!str_contains($page, 'Approve job (ECD)'), 'no ECD approval button on a paused job');
        rx_err(rx_job($d, $ecd, $job, 'ecd-approve'), 'Resume the job');
        t_true(!$d->reviews->jobReview($job)->ecdApproved(), 'nothing recorded');
        // Resumed, the ECD approval moves the job on as usual.
        rp_move($d, $p['am'], $job, 'in_review');
        t_eq('in_review', rx_stage($d, $job));
        sx_ok(rx_job($d, $ecd, $job, 'ecd-approve'));
        t_eq('approved_internal', rx_stage($d, $job));
        sx_ok(rx_job($d, bh_session($d, $p['am']), $job, 'ready'));
    },

    'reviews: a rejection waits until a paused, internally approved job is resumed' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        $cd = bh_session($d, $p['cd']);
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
        sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.jpg'], true));
        sx_ok(rx_rev($d, $cd, $job, $a1, 'approved'));
        sx_ok(rx_job($d, $cd, $job, 'approve'));
        sx_ok(rx_job($d, bh_session($d, $p['ecd']), $job, 'ecd-approve'));
        t_eq('approved_internal', rx_stage($d, $job));
        rp_move($d, $p['am'], $job, 'waiting', 'Waiting on the client');
        rx_err(rx_rev($d, $cd, $job, $a1, 'rejected', 'media'), 'Resume the job');
        t_eq('approved', $d->reviews->asset($job, $a1)->mediaState->value, 'nothing changed');
        t_true($d->reviews->jobReview($job)->internallyApproved(), 'approvals kept');
        // Resumed to Approved (internal), the rejection sends it back to work.
        rp_move($d, $p['am'], $job, 'approved_internal');
        t_eq('approved_internal', rx_stage($d, $job));
        sx_ok(rx_rev($d, $cd, $job, $a1, 'rejected', 'media'));
        t_eq('in_progress', rx_stage($d, $job));
        t_eq('In Progress', sx_status($d, $job), 'legacy status mirror');
    },

    'reviews: a paused job that resumes to In progress still takes rejections (it keeps its stage)' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
        t_eq('in_progress', rx_stage($d, $job));
        rp_move($d, $p['am'], $job, 'on_hold', 'Budget');
        sx_ok(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'rejected_with_feedback', 'copy', 'Shorter'));
        t_eq('on_hold', rx_stage($d, $job));
        t_eq('rejected_with_feedback', $d->reviews->asset($job, $a1)->copyState->value);
    },
];

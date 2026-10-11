<?php
declare(strict_types=1);

use App\Domain\Role;

require_once dirname(__DIR__, 2) . '/support/review_fx.php';

/**
 * Internal reviews end to end through the Kernel (owner spec 2026-10), on a
 * migrated temp database: hand in, Request review, part rejections routed to
 * the part's maker, rounds, the CD and ECD approvals, ready for client and
 * sent to client, reassignment, the round limit, and the legacy client
 * approval afterwards. Every activity row carries data.recipients.
 */
return [
    'reviews: the whole flow, from hand-in to sent to the client, then legacy approve_client' => function (): void {
        [$d, $p, $job] = rx_world(2);
        [$a1, $a2] = rx_posts($d, $job);
        $copy = bh_session($d, $p['copy']);
        $des = bh_session($d, $p['designer']);
        $cd = bh_session($d, $p['cd']);
        $ecd = bh_session($d, $p['ecd']);
        $am = bh_session($d, $p['am']);
        t_eq('briefed', rx_stage($d, $job));

        // 1. The Copywriter hands in the copy of post 1: round 1, the job starts, the asset counts as started.
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => "Line one\nLine two", 'hashtags' => '#open #gold', 'link_url' => 'https://example.com/open']));
        $x = $d->reviews->asset($job, $a1);
        t_eq(1, $x->round);
        t_eq('submitted', $x->copyState->value);
        t_eq('missing', $x->mediaState->value);
        t_eq($p['copy'], $x->copyMakerId);
        t_eq('in_progress', rx_stage($d, $job));
        t_eq('In Progress', (string) $d->db->scalar('SELECT status FROM assets WHERE id = :a', ['a' => $a1]), 'legacy asset status');
        t_eq([], rx_to($d, $job, 'asset_submitted'), 'a plain hand-in notifies nobody');
        // The Copywriter's form never writes the media, whatever is sent.
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => "Line one\nLine two!", 'media_url' => 'https://evil.example.com/x.png']));
        t_eq('', $d->reviews->asset($job, $a1)->mediaUrl, 'copywriter cannot hand in media');

        // 2. The Designer hands in the media and clicks Request review: the CD is told, the job is in review.
        sx_ok(rx_sub($d, $des, $job, $a1, ['media_url' => 'https://cdn.example.com/post1.png'], true));
        t_eq([$p['cd']], rx_to($d, $job, 'review_requested'));
        t_eq('in_review', rx_stage($d, $job));
        t_eq('In Review', (string) $d->db->scalar('SELECT status FROM jobs WHERE id = :j', ['j' => $job]), 'legacy status mirror');
        t_eq('image', $d->reviews->asset($job, $a1)->mediaKind->value);

        // 3. Post 2 is handed in by both makers without Request review: the last part completes the job and tells the CD once.
        sx_ok(rx_sub($d, $copy, $job, $a2, ['copy_text' => 'Second post']));
        $before = count($d->activity->listByVerb($job, 'review_requested'));
        sx_ok(rx_sub($d, $des, $job, $a2, ['media_url' => 'https://www.youtube.com/watch?v=abc']));
        $rows = $d->activity->listByVerb($job, 'review_requested');
        t_eq($before + 1, count($rows), 'every post handed in: one notice');
        t_eq('job', $rows[count($rows) - 1]->data['scope']);
        t_eq([$p['cd']], rx_to($d, $job, 'review_requested'));
        t_eq('video', $d->reviews->asset($job, $a2)->mediaKind->value);

        // The review page shows both posts as social posts, in the owner's order.
        $page = ts_body((ts_app($cd))(ts_request('GET', '/jobs/' . $job . '/review'), $d));
        t_contains('Post 1 of 2', $page);
        t_contains('Post 2 of 2', $page);
        t_contains('Line one', $page);
        t_contains('Play video', $page);
        t_contains('src="https://cdn.example.com/post1.png"', $page);
        $card = substr($page, (int) strpos($page, 'id="preview-'));
        $order = [strpos($card, 'The Meridian Collection'), strpos($card, '>Media<'), strpos($card, '>Copy<'), strpos($card, '>Hashtags<'), strpos($card, '>Link<')];
        t_true(!in_array(false, $order, true) && $order[0] < $order[1] && $order[1] < $order[2] && $order[2] < $order[3] && $order[3] < $order[4], 'brand, then media, copy, hashtags, link: ' . json_encode($order));
        t_contains('Approve', $page);
        t_contains('Reject with feedback', $page);
        t_contains('src="/slash301pm/public/js/review.js', $page);

        // 4. The CD rejects the copy only of post 1, with feedback: the Copywriter is told, the media stays waiting, the job goes back to work.
        $des1 = $d->reviews->asset($job, $a1)->mediaState->value;
        rx_err(rx_rev($d, $cd, $job, $a1, 'rejected_with_feedback', 'copy', ''), 'Write the feedback');
        sx_ok(rx_rev($d, $cd, $job, $a1, 'rejected_with_feedback', 'copy', 'Shorter, and mention the date.'));
        $x = $d->reviews->asset($job, $a1);
        t_eq('rejected_with_feedback', $x->copyState->value);
        t_eq($des1, $x->mediaState->value, 'the other part is untouched');
        t_eq([$p['copy']], rx_to($d, $job, 'asset_part_rejected'), 'copy rejection goes to the copy maker only');
        t_eq('in_progress', rx_stage($d, $job));
        t_eq('1.0.0', (string) $d->db->scalar('SELECT brief_version FROM asset_reviews WHERE asset_id = :a', ['a' => $a1]), 'the sent brief version is recorded');
        $work = ts_body((ts_app($copy))(ts_request('GET', '/jobs/' . $job . '/work'), $d));
        t_contains('Shorter, and mention the date.', $work, 'previous feedback on the hand-in page');

        // 5. Round 2: the Copywriter hands the copy in again; the approved or waiting media is carried forward.
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => 'Opens 12 October.'], true));
        $x = $d->reviews->asset($job, $a1);
        t_eq(2, $x->round);
        t_eq('submitted', $x->copyState->value);
        t_eq('https://cdn.example.com/post1.png', $x->mediaUrl, 'media carried into round 2');
        t_eq('in_review', rx_stage($d, $job));
        t_eq(2, $d->reviews->jobReview($job)->jobRound, 'job round follows the highest post round');

        // 6. The CD approves everything; a Designer and the AM cannot decide.
        rx_err(rx_rev($d, $des, $job, $a1, 'approved'), 'review its posts');
        rx_err(rx_rev($d, $am, $job, $a1, 'approved'), 'review its posts');
        rx_err(rx_job($d, $cd, $job, 'approve'), 'Approve every post');
        sx_ok(rx_rev($d, $cd, $job, $a1, 'approved'));
        sx_ok(rx_rev($d, $cd, $job, $a2, 'approved'));
        t_eq('Approved (Internal)', (string) $d->db->scalar('SELECT status FROM assets WHERE id = :a', ['a' => $a2]), 'legacy asset status');
        rx_err(rx_rev($d, $cd, $job, $a2, 'approved'), 'Already approved');

        // 7. The CD approves the job: every ECD is told. The ECD approves: the AM and the CD are told, the job is approved (internal).
        rx_err(rx_job($d, $ecd, $job, 'ecd-approve'), 'The CD approves the job first');
        sx_ok(rx_job($d, $cd, $job, 'approve'));
        t_eq([$p['ecd']], rx_to($d, $job, 'job_cd_approved'));
        t_eq('in_review', rx_stage($d, $job));
        rx_err(rx_job($d, $cd, $job, 'ecd-approve'), 'Only the ECD');
        sx_ok(rx_job($d, $ecd, $job, 'ecd-approve'));
        t_eq([$p['am'], $p['cd']], rx_to($d, $job, 'job_ecd_approved'));
        t_eq('approved_internal', rx_stage($d, $job));
        t_eq('Approved (Internal)', sx_status($d, $job), 'what legacy approve_client requires');

        // 8. Ready for client, then sent to client (by hand), by the AM; Traffic is told.
        rx_err(rx_job($d, $am, $job, 'send'), 'Mark the job ready');
        rx_err(rx_job($d, bh_session($d, $p['designer']), $job, 'ready'), 'get it ready');
        sx_ok(rx_job($d, $am, $job, 'ready'));
        t_eq([$p['traffic']], rx_to($d, $job, 'job_ready_for_client'));
        sx_ok(rx_job($d, $am, $job, 'send'));
        t_eq([$p['traffic']], rx_to($d, $job, 'job_sent_to_client'));
        $jr = $d->reviews->jobReview($job);
        t_true($jr->sentToClient() && $jr->readyForClient() && $jr->internallyApproved());
        t_eq($p['am'], $jr->sentToClientBy, 'the actor comes from the session');

        // 9. Legacy approve_client still works on top.
        sx_legacy_approve($d, $job);
        t_eq('approved_client', rx_stage($d, $job));
        // Every review activity row was written by the session user and carries recipients.
        foreach ($d->db->query("SELECT verb, data_json FROM activity WHERE job_id = :j AND verb IN ('asset_submitted', 'review_requested', 'asset_part_approved', 'asset_part_rejected', 'job_cd_approved', 'job_ecd_approved', 'job_ready_for_client', 'job_sent_to_client')", ['j' => $job]) as $r) {
            $data = json_decode((string) $r['data_json'], true);
            t_true(is_array($data) && array_key_exists('recipients', $data), $r['verb'] . ' has data.recipients');
        }
    },

    'reviews: a rejection after the ECD approval clears both approvals and sends the job back' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        $cd = bh_session($d, $p['cd']);
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
        sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.jpg']));
        sx_ok(rx_rev($d, $cd, $job, $a1, 'approved'));
        sx_ok(rx_job($d, $cd, $job, 'approve'));
        sx_ok(rx_job($d, bh_session($d, $p['ecd']), $job, 'ecd-approve'));
        sx_ok(rx_job($d, bh_session($d, $p['traffic']), $job, 'ready'));
        t_eq('approved_internal', rx_stage($d, $job));
        // Reject the media (red, no feedback): only the Designer is told; the copy stays approved.
        sx_ok(rx_rev($d, $cd, $job, $a1, 'rejected', 'media'));
        t_eq([$p['designer']], rx_to($d, $job, 'asset_part_rejected'));
        $jr = $d->reviews->jobReview($job);
        t_true(!$jr->cdApproved() && !$jr->ecdApproved() && !$jr->readyForClient(), 'approvals cleared');
        t_eq(true, $d->activity->listByVerb($job, 'asset_part_rejected')[0]->data['cleared_approvals']);
        t_eq('in_progress', rx_stage($d, $job));
        t_eq('In Progress', sx_status($d, $job));
        $x = $d->reviews->asset($job, $a1);
        t_eq('approved', $x->copyState->value);
        t_eq('rejected', $x->mediaState->value);
        // The approved copy is frozen; the Designer hands in new media in round 2.
        rx_err(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Changed']), 'approved');
        sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/b.jpg']));
        t_eq(2, $d->reviews->asset($job, $a1)->round);
        t_eq('in_review', rx_stage($d, $job), 'every post handed in again');
    },

    'reviews: reassignment to another CD, the ECD, and the client only after ECD approval' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        $cd = bh_session($d, $p['cd']);
        $cd2 = bh_session($d, $p['cd2']);
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
        rx_err(rx_rev($d, $cd2, $job, $a1, 'approved'), 'review its posts');
        rx_err(rx_job($d, $cd, $job, 'reassign', $p['client']), 'after the ECD');
        rx_err(rx_job($d, $cd, $job, 'reassign', $p['designer']), 'another CD');
        rx_err(rx_job($d, bh_session($d, $p['am']), $job, 'reassign', $p['cd2']), 'reassign');
        sx_ok(rx_job($d, $cd, $job, 'reassign', $p['cd2']));
        t_eq([$p['cd2']], rx_to($d, $job, 'review_reassigned'));
        t_eq($p['cd2'], $d->reviews->jobReview($job)->assigneeId);
        // The new reviewer is told about the next Request review, and may decide.
        sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.png'], true));
        t_eq([$p['cd2']], rx_to($d, $job, 'review_requested'));
        sx_ok(rx_rev($d, $cd2, $job, $a1, 'approved'));
        sx_ok(rx_job($d, $cd2, $job, 'reassign', $p['ecd']));
        t_eq([$p['ecd']], rx_to($d, $job, 'review_reassigned'));
        sx_ok(rx_job($d, $cd, $job, 'approve'));
        sx_ok(rx_job($d, bh_session($d, $p['ecd']), $job, 'ecd-approve'));
        // After the ECD approval the review may go to a client contact of the brand (no notification: clients cannot sign in yet).
        sx_ok(rx_job($d, $cd, $job, 'reassign', $p['client']));
        t_eq([], rx_to($d, $job, 'review_reassigned'));
        t_eq('Client', $d->reviews->jobReview($job)->assigneeRole?->value);
    },

    'reviews: the AM is warned when a post reaches the round after the limit (default 3, setting on /admin/system)' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        $copy = bh_session($d, $p['copy']);
        $cd = bh_session($d, $p['cd']);
        t_eq(3, $d->settings->reviewRoundLimit(), 'seeded default');
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => 'v1']));
        for ($round = 2; $round <= 4; $round++) {
            sx_ok(rx_rev($d, $cd, $job, $a1, 'rejected_with_feedback', 'copy', 'Again'));
            $body = ts_body(rx_sub($d, $copy, $job, $a1, ['copy_text' => 'v' . $round]));
            t_eq($round, $d->reviews->asset($job, $a1)->round);
            t_eq($round === 4 ? 1 : 0, count($d->activity->listByVerb($job, 'round_limit_warning')), "warning count at round $round");
            if ($round === 4) {
                t_contains('passed the review round limit', $body);
            }
        }
        t_eq([$p['am']], rx_to($d, $job, 'round_limit_warning'));
        // The AM sees it on My day; the Copywriter saw "Changes requested" while it was rejected.
        $today = ts_body((ts_app(bh_session($d, $p['am'])))(ts_request('GET', '/today'), $d));
        t_contains('Past the round limit', $today);
        t_contains('Round 4 (limit 3)', $today);
        // The COO turns the limit to 5: no warning at round 5.
        $coo = bh_session($d, $p['coo']);
        rx_err(bh_ds($d, $coo, 'POST', '/admin/system/review-round-limit', ['rl' => ['limit' => 'abc']]), 'whole number');
        rx_err(bh_ds($d, bh_session($d, $p['am']), 'POST', '/admin/system/review-round-limit', ['rl' => ['limit' => '9']]), 'Only the COO or the ECD');
        sx_ok(bh_ds($d, $coo, 'POST', '/admin/system/review-round-limit', ['rl' => ['limit' => '5']]));
        t_eq(5, $d->settings->reviewRoundLimit());
        sx_ok(rx_rev($d, $cd, $job, $a1, 'rejected_with_feedback', 'copy', 'Once more'));
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => 'v5']));
        t_eq(1, count($d->activity->listByVerb($job, 'round_limit_warning')), 'no new warning at round 5 with limit 5');
    },

    'reviews: stale row versions, wrong makers, cancelled posts and bad links are refused' => function (): void {
        [$d, $p, $job] = rx_world(2);
        [$a1, $a2] = rx_posts($d, $job);
        $copy = bh_session($d, $p['copy']);
        $cd = bh_session($d, $p['cd']);
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => 'One']));
        t_contains('Changed by someone else', ts_body(rx_sub($d, $copy, $job, $a1, ['copy_text' => 'Two', 'rv' => 0])));
        t_contains('Changed by someone else', ts_body(rx_rev($d, $cd, $job, $a1, 'approved', 'copy', '', 99)));
        t_contains('Changed by someone else', ts_body(rx_job($d, $cd, $job, 'request-ecd', '', 77)));
        rx_err(rx_sub($d, $copy, $job, $a1, ['link_url' => 'javascript:alert(1)']), 'https://');
        rx_err(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'javascript:alert(1)//.png']), 'media link');
        rx_err(rx_sub($d, bh_session($d, $p['am']), $job, $a1, ['copy_text' => 'AM copy']), 'hand it in');
        rx_err(rx_sub($d, bh_session($d, $p['cd2']), $job, $a1, ['copy_text' => 'x']), 'not yours to make');
        rx_err(rx_rev($d, $cd, $job, $a2, 'approved'), 'not been handed in');
        t_contains('Choose Approve', ts_body(rx_rev($d, $cd, $job, $a1, 'maybe')));
        // A cancelled post is gone for everyone.
        $d->db->exec("UPDATE assets SET status = 'Cancelled' WHERE id = :a", ['a' => $a2]);
        t_contains('no longer exists', ts_body(rx_sub($d, $copy, $job, $a2, ['copy_text' => 'x'])));
        t_true(!str_contains(ts_body((ts_app($cd))(ts_request('GET', '/jobs/' . $job . '/review'), $d)), 'Post 2 of'), 'cancelled posts are not listed');
        // Request ECD review early: every ECD is told.
        sx_ok(rx_job($d, $cd, $job, 'request-ecd'));
        t_eq([$p['ecd']], rx_to($d, $job, 'job_review_requested'));
        t_eq('in_review', rx_stage($d, $job));
        // Clients never see the review; a Designer not on the job neither.
        $other = ts_user($d, 'design_tariq', Role::Designer);
        t_eq(404, (ts_app(bh_session($d, $other)))(ts_request('GET', '/jobs/' . $job . '/review'), $d)->status());
    },

    'reviews: My day lists and the nav badge per role' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        $get = static fn (string $who, string $path): string => ts_body((ts_app(bh_session($d, $p[$who])))(ts_request('GET', $path), $d));
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy'], false));
        sx_ok(rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.png'], true));
        $cdDay = $get('cd', '/today');
        t_contains('Waiting for my review', $cdDay);
        t_contains('1 post waiting', $cdDay);
        t_contains('data-nav-count=', $cdDay, 'the CD has a nav badge');
        sx_ok(rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'rejected_with_feedback', 'copy', 'Tighter'));
        $copyDay = $get('copy', '/today');
        t_contains('Changes requested', $copyDay);
        t_contains('Change the copy (round 1)', $copyDay);
        t_contains('/jobs/' . $job . '/work', $copyDay);
        t_not_contains('Change the copy', $get('designer', '/today'), 'the media maker is not asked to change the copy');
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy 2']));
        sx_ok(rx_job($d, bh_session($d, $p['cd']), $job, 'request-ecd'));
        $ecdDay = $get('ecd', '/today');
        t_contains('Jobs waiting for ECD approval', $ecdDay);
        t_contains('The CD asked for your review', $ecdDay);
        t_contains('id="today-section-reviews"', ts_body(bh_ds($d, bh_session($d, $p['ecd']), 'GET', '/today/reviews')));
        // Traffic gets no review section until a job of theirs is approved by the CD and the ECD ("Ready for the client").
        t_not_contains('today-section-reviews', $get('traffic', '/today'));
    },
];

<?php
declare(strict_types=1);

use App\Domain\Stage;

require_once dirname(__DIR__, 2) . '/support/social_fx.php';

/**
 * Social publishing end to end through the Kernel on a migrated temp DB:
 * legacy client approval -> queue -> checklist -> ready (AM told) ->
 * scheduled -> live with link -> promoted -> job live, legacy status still
 * 'Approved (External)' -> reopen with a reason (SOC-2, SOC-3, SOC-4).
 */
return [
    'social flow: legacy approval puts the job in the queue; posts walk ready, scheduled, live; the job follows' => function (): void {
        [$d, $p, $jobId] = sx_world(2, true, false);
        $sol = bh_session($d, $p['social']);
        // Before client approval: not in the queue, and the job page is refused (SOC-5).
        t_not_contains('Grand Opening Social', ts_body(bh_ds($d, $sol, 'GET', '/social/list', ['sq' => ['tab' => 'check']])));
        t_eq(403, (ts_app($sol))(ts_request('GET', '/social/jobs/' . $jobId), $d)->status());
        sx_legacy_approve($d, $jobId);
        t_eq('approved_client', sx_stage($d, $jobId), 'the 0002 trigger maps Approved (External) to approved_client');

        $list = ts_body(bh_ds($d, $sol, 'GET', '/social/list', ['sq' => ['tab' => 'check']]));
        t_contains('Grand Opening Social', $list);
        t_contains('Suggested: Instagram', $list);
        $page = (ts_app($sol))(ts_request('GET', '/social/jobs/' . $jobId), $d);
        t_eq(200, $page->status());
        t_contains('Mark whole brief ready', ts_body($page));

        [$a1, $a2] = sx_assets($d, $jobId);
        $ig1 = sx_add($d, $sol, $a1, 'instagram');
        $fb1 = sx_add($d, $sol, $a1, 'facebook');
        $ig2 = sx_add($d, $sol, $a2, 'instagram');
        // Asset 1 Instagram ready on its own (SOC-4): nothing else moves.
        $refused = ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $ig1 . '/ready', sx_sig($d, $ig1)));
        t_contains('Tick every item first', $refused);
        sx_tick_all($d, $sol, $ig1);
        t_contains('Ready to schedule', ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $ig1 . '/ready', sx_sig($d, $ig1))));
        t_eq('ready_to_schedule', $d->publications->get($ig1)->status->value);
        t_eq('approved_client', sx_stage($d, $jobId), 'the job waits for every social asset');
        $ready = $d->activity->listByVerb($jobId, 'publication_ready');
        t_eq(1, count($ready));
        t_eq([$p['am']], $ready[0]->data['recipients'], 'N34 goes to the AM');
        t_eq($p['social'], $ready[0]->actorId);

        // The rest through "Mark whole brief ready": refused until every checklist is complete.
        t_contains('still needs', ts_body(bh_ds($d, $sol, 'POST', '/social/jobs/' . $jobId . '/ready')));
        sx_tick_all($d, $sol, $fb1);
        sx_tick_all($d, $sol, $ig2);
        t_contains('Every post is Ready to schedule', ts_body(bh_ds($d, $sol, 'POST', '/social/jobs/' . $jobId . '/ready')));
        t_eq('ready_to_schedule', sx_stage($d, $jobId));
        t_eq('Approved (External)', sx_status($d, $jobId));

        // Scheduled: needs a time; the job moves when every post is scheduled.
        t_contains('Enter the date and time', ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $ig1 . '/scheduled', sx_sig($d, $ig1))));
        foreach ([$ig1, $fb1, $ig2] as $i => $pid) {
            sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/scheduled', sx_sig($d, $pid, ['scheduled_at' => '2026-10-1' . ($i + 2) . 'T09:30'])));
        }
        t_eq('2026-10-12 09:30', $d->publications->get($ig1)->scheduledAt);
        t_eq('scheduled', sx_stage($d, $jobId));
        t_eq([$p['am']], $d->activity->listByVerb($jobId, 'publication_scheduled')[0]->data['recipients'], 'N35');

        // Live: a link per platform; javascript: is refused.
        t_contains('must be a web address', ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $ig1 . '/live', sx_sig($d, $ig1, ['live_url' => 'javascript:alert(1)']))));
        t_eq('scheduled', $d->publications->get($ig1)->status->value);
        foreach ([$ig1, $fb1, $ig2] as $i => $pid) {
            sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/live', sx_sig($d, $pid, ['live_url' => 'https://www.instagram.com/p/post' . $i . '/'])));
        }
        t_eq('https://www.instagram.com/p/post0/', $d->publications->get($ig1)->liveUrl);
        t_eq('live', sx_stage($d, $jobId));
        t_eq('Approved (External)', sx_status($d, $jobId), 'legacy still sees Approved (External)');
        t_eq(1, count($d->activity->listByVerb($jobId, 'job_go_live')), 'the job move is logged through the transition path');

        // Promoted on Instagram only (N37).
        sx_ok(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $ig1 . '/promoted', sx_sig($d, $ig1, ['promoted' => true, 'promoted_note' => 'Boosted 7 days'])));
        t_eq(true, $d->publications->get($ig1)->promoted);
        t_eq('Boosted 7 days', $d->publications->get($ig1)->promotedNote);
        t_eq(false, $d->publications->get($fb1)->promoted);
        t_eq([$p['am']], $d->activity->listByVerb($jobId, 'publication_promoted')[0]->data['recipients']);

        // Reopen with a reason: the post and the job go back one step; Social slot and AM told (N39).
        t_contains('Say why', ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $fb1 . '/reopen', sx_sig($d, $fb1))));
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $fb1 . '/reopen', sx_sig($d, $fb1, ['reason' => 'Wrong link pasted'])));
        t_eq('scheduled', $d->publications->get($fb1)->status->value);
        t_eq('scheduled', sx_stage($d, $jobId));
        t_eq('Approved (External)', sx_status($d, $jobId));
        $re = $d->activity->listByVerb($jobId, 'publication_reopened')[0];
        t_eq('Wrong link pasted', $re->data['reason']);
        t_eq([$p['am']], $re->data['recipients'], 'the actor (Social) is never told about their own change');
        $back = $d->activity->listByVerb($jobId, 'job_social_step_back');
        t_eq(1, count($back));
        t_eq('Wrong link pasted', $back[0]->data['reason']);

        // Live again; then a COO archive of one post leaves the job live.
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $fb1 . '/live', sx_sig($d, $fb1, ['live_url' => 'https://facebook.com/p/1'])));
        t_eq('live', sx_stage($d, $jobId));
        $coo = bh_session($d, $p['coo']);
        sx_ok(bh_ds($d, $coo, 'POST', '/social/publications/' . $fb1 . '/archive', sx_sig($d, $fb1, ['reason' => 'Client asked to take it down'])));
        t_eq('archived', $d->publications->get($fb1)->status->value);
        t_eq('live', sx_stage($d, $jobId));
        // Live tab lists both assets; archived tab lists the archived one.
        $live = ts_body(bh_ds($d, $sol, 'GET', '/social/list', ['sq' => ['tab' => 'live']]));
        t_contains('sq-tab-live', $live);
        t_eq(Stage::Live->value, sx_stage($d, $jobId));
    },

    'social flow: row_version conflicts answer with the latest and a warning, and change nothing' => function (): void {
        [$d, $p, $jobId] = sx_world(1);
        $sol = bh_session($d, $p['social']);
        $coo = bh_session($d, $p['coo']);
        [$a1] = sx_assets($d, $jobId);
        $pid = sx_add($d, $sol, $a1, 'instagram');
        $stale = sx_sig($d, $pid, ['cl' => ['copy' => true]]);
        sx_tick_all($d, $coo, $pid);
        $body = ts_body(bh_ds($d, $sol, 'PATCH', '/social/publications/' . $pid . '/checklist', $stale));
        t_contains('Changed by someone else', $body);
        t_contains('id="pub-' . $pid . '"', $body, 'the card is re-rendered');
        t_eq(5, $d->publications->get($pid)->checklist->tickedCount(), 'the newer write stands');
        $old = sx_sig($d, $pid);
        sx_ok(bh_ds($d, $coo, 'POST', '/social/publications/' . $pid . '/ready', sx_sig($d, $pid)));
        t_contains('Changed by someone else', ts_body(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/ready', $old)));
        t_eq(1, count($d->activity->listByVerb($jobId, 'publication_ready')));
    },

    'social flow: Social slot set after send by the AM writes social_assigned; the slot holder sees My day Social' => function (): void {
        [$d, $p, $jobId] = sx_world(1, false);
        $amy = bh_session($d, $p['am']);
        $body = ts_body(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/assignments/social', ['team_social' => ['value' => $p['social']]]));
        t_not_contains('data-toast="error"', $body);
        $rows = $d->activity->listByVerb($jobId, 'social_assigned');
        t_eq(1, count($rows));
        t_eq([$p['social']], $rows[0]->data['recipients']);
        t_eq($p['am'], $rows[0]->actorId);
        // From the job sheet: answers with the sheet.
        $trf = bh_session($d, $p['traffic']);
        $sheet = ts_body(bh_ds($d, $trf, 'POST', '/jobs/' . $jobId . '/assignments/social', ['sheet_social' => ['value' => $p['social2']]]));
        t_contains('id="sheet"', $sheet);
        t_eq($p['social2'], (string) $d->db->scalar("SELECT user_id FROM job_assignments WHERE job_id = :j AND role_on_job = 'Social'", ['j' => $jobId]));
        // A Designer cannot.
        $kim = bh_session($d, $p['designer']);
        $no = ts_body(bh_ds($d, $kim, 'POST', '/jobs/' . $jobId . '/assignments/social', ['team_social' => ['value' => $p['social']]]));
        t_contains('data-toast="error"', $no);
        t_eq($p['social2'], (string) $d->db->scalar("SELECT user_id FROM job_assignments WHERE job_id = :j AND role_on_job = 'Social'", ['j' => $jobId]));
        // My day: the Social user sees the Social section with the job to check.
        $sam = bh_session($d, $p['social2']);
        $today = ts_body((ts_app($sam))(ts_request('GET', '/today'), $d));
        t_contains('today-section-social', $today);
        t_contains('Approved, to check', $today);
        t_contains('1 asset to check', $today);
        // The AM does not get the Social section.
        t_not_contains('today-section-social', ts_body((ts_app($amy))(ts_request('GET', '/today'), $d)));
    },

    'social flow: My day lists scheduled today and posts past their time without a link' => function (): void {
        [$d, $p, $jobId] = sx_world(1);
        $sol = bh_session($d, $p['social']);
        [$a1] = sx_assets($d, $jobId);
        $ig = sx_add($d, $sol, $a1, 'instagram');
        $fb = sx_add($d, $sol, $a1, 'facebook');
        foreach ([$ig, $fb] as $pid) {
            sx_tick_all($d, $sol, $pid);
            sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $pid . '/ready', sx_sig($d, $pid)));
        }
        // The fixed clock is 2026-10-09 09:00 (SAST 11:00).
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $ig . '/scheduled', sx_sig($d, $ig, ['scheduled_at' => '2026-10-09T16:00'])));
        sx_ok(bh_ds($d, $sol, 'POST', '/social/publications/' . $fb . '/scheduled', sx_sig($d, $fb, ['scheduled_at' => '2026-10-08T08:00'])));
        $section = ts_body(bh_ds($d, $sol, 'GET', '/today/social'));
        t_contains('Scheduled today <span class="font-normal text-muted-foreground">(1)', $section);
        t_contains('Live without a link <span class="font-normal text-muted-foreground">(1)', $section);
        t_contains('Due 8 Oct 2026, 08:00', $section);
    },
];

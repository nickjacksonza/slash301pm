<?php
declare(strict_types=1);

use App\Domain\BumpLevel;
use App\Domain\Role;
use App\Domain\Signals\BriefSignals;
use App\Domain\Stage;
use App\Domain\Types\BriefLineInput;
use App\Domain\AssetTemplates;
use App\Http\BriefState;

require_once dirname(__DIR__, 2) . '/support/brief_db.php';

return [
    'brief flow: draft, autosave, deliverables, Traffic, send 1.0.0, edit, update 1.1.0, versions and diff' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $am = $d->users->findById($p['am']);
        $now = $d->clock->now();

        // 1. create draft: number from the counter, brief 0.1.0, creator in the AM slot, default tasks
        $jobId = $d->jobs->createDraft($d->campaigns->get($c['campaign']), 'Grand Opening Social', $am, $now);
        $job = $d->jobs->get($jobId);
        t_eq('MERC-001', $job->jobNumber);
        t_eq(Stage::Draft, $job->stage);
        t_eq('Inbox', $job->status);
        t_eq($p['am'], $job->amUserId);
        $s = BriefState::load($d, $jobId);
        t_eq('0.1.0', $s->brief->version->format());
        t_eq($p['am'], $s->brief->createdBy);
        t_eq($p['am'], $s->team->userFor(Role::AM));
        t_eq(2, (int) $d->db->scalar('SELECT COUNT(*) FROM tasks WHERE job_id = :j', ['j' => $jobId]));
        t_eq(1, count($d->activity->listByVerb($jobId, 'job_created')));

        // 2. autosave: draft fields are mirrored to the legacy columns
        $sig = BriefSignals::fromSignals(['brief' => ['title' => 'Grand Opening Social Launch', 'due_date' => '2026-10-20', 'creative_direction' => "Warm.\nGold accents.",
            'mandatories_text' => "Logo\nT&Cs", 'references_text' => 'Mood | https://example.com/m', 'budget' => '25000', 'hours_estimate' => '30']]);
        t_true($sig->errors->isEmpty());
        t_true($d->briefs->save($s->brief, $sig->patch, $s->baseline, $p['am'], $now));
        $job = $d->jobs->get($jobId);
        t_eq('Grand Opening Social Launch', $job->title);
        $legacy = $d->db->one('SELECT delivery_date, creative_direction, hours_estimate, brief_date FROM jobs WHERE id = :j', ['j' => $jobId]);
        t_eq(['2026-10-20', "Warm.\nGold accents.", 30.0, '2026-10-09'], array_values($legacy));
        t_true($job->rowVersion > 1, 'row_version bumped');

        // 3. deliverables and Traffic; send is blocked until Traffic is assigned
        $s = BriefState::load($d, $jobId);
        $static = new BriefLineInput('social-static', 'Social Post (Static)', 3, 'Instagram', '1080x1350', 'Safe zones', false, null);
        $line1 = $d->briefAssets->add($s->brief, $static, $s->baseline, $p['am'], $now);
        $s = BriefState::load($d, $jobId);
        $line2 = $d->briefAssets->add($s->brief, BriefLineInput::fromTemplate(AssetTemplates::find('email-copy')), $s->baseline, $p['am'], $now);
        $s = BriefState::load($d, $jobId);
        t_eq(['traffic'], array_keys($s->sendPlan(null, '', $now)->errors->errors));
        t_eq('', $d->assignments->set($jobId, Role::Traffic, $p['traffic'], $p['am'], $now));
        t_eq('Only a Traffic can hold the Traffic slot.', $d->assignments->set($jobId, Role::Traffic, $p['designer'], $p['am'], $now));
        t_eq('', $d->assignments->set($jobId, Role::Designer, $p['designer'], $p['am'], $now));
        t_eq($p['designer'], $d->db->scalar("SELECT assigned_to FROM tasks WHERE job_id = :j AND type = 'media'", ['j' => $jobId]), 'media task follows the Designer slot');
        $assigned = $d->activity->listByVerb($jobId, 'assigned_to_job');
        t_eq(2, count($assigned));
        t_eq([$p['traffic']], $assigned[0]->data['recipients']);

        // 4. send 1.0.0
        $s = BriefState::load($d, $jobId);
        $plan = $s->sendPlan(null, '', $now);
        t_true($plan->errors->isEmpty(), $plan->errors->first());
        $res = $d->briefs->send($plan->plan, $s->baseline, $s->job->campaignId, $p['am'], $now);
        t_true($res['ok']);
        t_eq(4, count($res['created']));
        $job = $d->jobs->get($jobId);
        t_eq(Stage::Briefed, $job->stage);
        t_eq('To Do', $job->status);
        $names = array_map(static fn ($a) => $a->name, $d->assets->listByJob($jobId));
        t_eq([
            'MERC-001-TheMeridia-GrandOpeningLon-SocialPostStatic1-v1-20261009_1080x1350',
            'MERC-001-TheMeridia-GrandOpeningLon-SocialPostStatic2-v1-20261009_1080x1350',
            'MERC-001-TheMeridia-GrandOpeningLon-SocialPostStatic3-v1-20261009_1080x1350',
            'MERC-001-TheMeridia-GrandOpeningLon-EmailCopy-v1-20261009',
        ], $names);
        t_eq(['Inbox'], array_values(array_unique(array_map(static fn ($a) => $a->status, $d->assets->listByJob($jobId)))));
        $sent = $d->activity->listByVerb($jobId, 'brief_sent');
        t_eq(1, count($sent));
        t_eq('1.0.0', $sent[0]->data['version']);
        t_eq($p['am'], $sent[0]->actorId);
        t_eq([$p['traffic'], $p['designer']], $sent[0]->data['recipients']);
        $s = BriefState::load($d, $jobId);
        t_eq('1.0.0', $s->brief->version->format());
        t_true(!$s->brief->hasUnsentChanges);

        // 5. edit after send: working copy only; legacy columns keep the sent values
        $edit = BriefSignals::fromSignals(['brief' => ['due_date' => '2026-10-25', 'title' => 'Renamed in working copy']]);
        t_true($d->briefs->save($s->brief, $edit->patch, $s->baseline, $p['am'], $now));
        t_eq('2026-10-20', $d->db->scalar('SELECT delivery_date FROM jobs WHERE id = :j', ['j' => $jobId]), 'legacy sees the sent version');
        t_eq('Grand Opening Social Launch', $d->jobs->get($jobId)->title);
        $s = BriefState::load($d, $jobId);
        t_true($s->brief->hasUnsentChanges);
        // qty 3 -> 5 on the statics; one email asset is started in legacy, then the email line is removed
        $d->db->exec("UPDATE assets SET status = 'In Progress' WHERE brief_asset_id = :l", ['l' => $line2]);
        t_true($d->briefAssets->update($s->brief, $line1, new BriefLineInput('social-static', 'Social Post (Static)', 5, 'Instagram', '1080x1350', 'Safe zones', false, null), $s->baseline, $p['am'], $now));
        $s = BriefState::load($d, $jobId);
        t_true($d->briefAssets->remove($s->brief, $line2, $s->baseline, $p['am'], $now));

        // 6. send update: suggested minor, refused without a note, then 1.1.0
        $s = BriefState::load($d, $jobId);
        $sugg = App\Domain\BriefDiff::suggestBump($s->diff(), count($s->lastSent->lines));
        t_eq(['major', 'Half or more of the deliverables were removed.'], [$sugg->level->value, $sugg->reason], 'one of two lines removed suggests major; the AM may pick minor');
        t_eq(['note'], array_keys($s->sendPlan(BumpLevel::Minor, '', $now)->errors->errors));
        $plan = $s->sendPlan(BumpLevel::Minor, 'Two more statics, new date, email dropped', $now);
        t_true($plan->errors->isEmpty(), $plan->errors->first());
        $res = $d->briefs->send($plan->plan, $s->baseline, $s->job->campaignId, $p['am'], $now);
        t_true($res['ok']);
        t_eq(2, count($res['created']), 'qty up adds two');
        t_eq([], $res['cancelled'], 'the started email asset is kept');
        $job = $d->jobs->get($jobId);
        t_eq(Stage::Briefed, $job->stage, 'an update keeps the stage');
        t_eq('2026-10-25', $d->db->scalar('SELECT delivery_date FROM jobs WHERE id = :j', ['j' => $jobId]));
        t_eq('Renamed in working copy', $job->title);
        $conflicts = $d->activity->listByVerb($jobId, 'started_asset_conflict');
        t_eq(1, count($conflicts));
        $upd = $d->activity->listByVerb($jobId, 'brief_updated');
        t_eq('1.1.0', $upd[0]->data['version']);
        t_eq('Two more statics, new date, email dropped', $upd[0]->data['note']);
        $names = array_map(static fn ($a) => $a->name, $d->assets->listByJob($jobId));
        t_contains('SocialPostStatic5', implode(',', $names));

        // 7. versions and diff
        $versions = $d->briefs->versions($s->brief->id);
        t_eq(['1.0.0', '1.1.0'], array_map(static fn ($v) => $v->version->format(), $versions));
        t_eq(['initial', 'minor'], array_map(static fn ($v) => $v->bumpLevel, $versions));
        $diff = $versions[1]->diff;
        t_eq(['title', 'due_date'], array_map(static fn ($f) => $f->field, $diff->fields));
        t_eq(['changed', 'removed'], array_map(static fn ($l) => $l->kind, $diff->lines));
        t_eq(null, $versions[0]->diff);
        t_eq('Grand Opening Social Launch', $versions[0]->snapshot->title, 'creatives keep reading what was sent');
        t_eq($d->briefs->latestVersion($s->brief->id)->version->format(), '1.1.0');

        // 8. qty down cancels unstarted assets only
        $s = BriefState::load($d, $jobId);
        t_true($d->briefAssets->update($s->brief, $line1, new BriefLineInput('social-static', 'Social Post (Static)', 2, 'Instagram', '1080x1350', 'Safe zones', false, null), $s->baseline, $p['am'], $now));
        $s = BriefState::load($d, $jobId);
        $res = $d->briefs->send($s->sendPlan(BumpLevel::Minor, 'Back to two', $now)->plan, $s->baseline, $s->job->campaignId, $p['am'], $now);
        t_eq(3, count($res['cancelled']));
        t_eq(1, count($d->activity->listByVerb($jobId, 'deliverable_cancelled')));
        t_eq(2, (int) $d->db->scalar("SELECT COUNT(*) FROM assets WHERE brief_asset_id = :l AND status <> 'Cancelled'", ['l' => $line1]));
    },
    'brief flow: row_version conflicts refuse stale writes' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $am = $d->users->findById($p['am']);
        $now = $d->clock->now();
        $jobId = $d->jobs->createDraft($d->campaigns->get($c['campaign']), 'Conflict test', $am, $now);
        $stale = BriefState::load($d, $jobId);
        t_true($d->briefs->save($stale->brief, BriefSignals::fromSignals(['brief' => ['title' => 'First']])->patch, null, $p['am'], $now));
        t_true(!$d->briefs->save($stale->brief, BriefSignals::fromSignals(['brief' => ['title' => 'Second']])->patch, null, $p['am'], $now), 'stale brief row_version');
        t_eq('First', $d->briefs->getByJob($jobId)->title);
        // a legacy write between load and send makes the send refuse
        $d->assignments->set($jobId, Role::Traffic, $p['traffic'], $p['am'], $now);
        $fresh = BriefState::load($d, $jobId);
        $d->briefs->save($fresh->brief, BriefSignals::fromSignals(['brief' => ['due_date' => '2026-10-20', 'creative_direction' => 'x']])->patch, null, $p['am'], $now);
        $fresh = BriefState::load($d, $jobId);
        $d->briefAssets->add($fresh->brief, BriefLineInput::fromTemplate(null), null, $p['am'], $now);
        $fresh = BriefState::load($d, $jobId);
        $plan = $fresh->sendPlan(null, '', $now);
        t_true($plan->errors->isEmpty(), $plan->errors->first());
        $d->db->exec("UPDATE jobs SET sort_order = 5 WHERE id = :j", ['j' => $jobId]);   // legacy write: trigger bumps row_version
        $res = $d->briefs->send($plan->plan, null, $fresh->job->campaignId, $p['am'], $now);
        t_true(!$res['ok']);
        t_eq(Stage::Draft, $d->jobs->get($jobId)->stage);
        t_eq(0, count($d->briefs->versions($fresh->brief->id)));
        // transition with a stale job row is refused too
        $job = $d->jobs->get($jobId);
        $o = App\Domain\Transitions::plan($job->stage, null, new App\Domain\Types\TransitionRequest(App\Domain\JobAction::Hold, null, 'Paused'), false);
        $d->db->exec("UPDATE jobs SET sort_order = 6 WHERE id = :j", ['j' => $jobId]);
        t_true(!$d->jobs->applyTransition($job, $o, $p['am'], $now, 'job_on_hold', []));
        t_true($d->jobs->applyTransition($d->jobs->get($jobId), $o, $p['am'], $now, 'job_on_hold', []));
        $held = $d->jobs->get($jobId);
        t_eq([Stage::OnHold, 'On Hold', Stage::Draft], [$held->stage, $held->status, $held->resumeStage]);
    },
    'brief flow: transitions write stage, legacy status, resume stage and activity' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $now = $d->clock->now();
        $jobId = $d->jobs->createDraft($d->campaigns->get($c['campaign']), 'Moves', $d->users->findById($p['am']), $now);
        $d->db->exec("UPDATE jobs SET status = 'Today' WHERE id = :j", ['j' => $jobId]);   // legacy: Today -> in_progress
        $job = $d->jobs->get($jobId);
        t_eq(Stage::InProgress, $job->stage);
        $wait = App\Domain\Transitions::plan($job->stage, $job->resumeStage, new App\Domain\Types\TransitionRequest(App\Domain\JobAction::Wait, App\Domain\WaitingOn::Client, 'Hero image'), false);
        t_true($d->jobs->applyTransition($job, $wait, $p['am'], $now, 'job_waiting', ['recipients' => []]));
        $job = $d->jobs->get($jobId);
        t_eq([Stage::Waiting, 'Waiting', Stage::InProgress, App\Domain\WaitingOn::Client, 'Hero image'], [$job->stage, $job->status, $job->resumeStage, $job->waitingOn, $job->waitingReason]);
        $resume = App\Domain\Transitions::plan($job->stage, $job->resumeStage, new App\Domain\Types\TransitionRequest(App\Domain\JobAction::Resume), false);
        t_true($d->jobs->applyTransition($job, $resume, $p['am'], $now, 'job_resumed', []));
        $job = $d->jobs->get($jobId);
        t_eq([Stage::InProgress, 'In Progress', null, null, ''], [$job->stage, $job->status, $job->resumeStage, $job->waitingOn, $job->waitingReason]);
        $w = $d->activity->listByVerb($jobId, 'job_waiting');
        t_eq(['in_progress', 'waiting', 'client', 'Hero image'], [$w[0]->data['from'], $w[0]->data['to'], $w[0]->data['waiting_on'], $w[0]->data['reason']]);
    },
    'claim AM: only when the slot is empty' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $now = $d->clock->now();
        $jobId = $d->jobs->createDraft($d->campaigns->get($c['campaign']), 'By the COO', $d->users->findById($p['coo']), $now);
        t_eq(null, $d->jobs->get($jobId)->amUserId, 'COO creator takes no slot');
        t_true($d->jobs->claimAm($jobId, $p['am'], $now));
        t_true(!$d->jobs->claimAm($jobId, $p['am2'], $now));
        t_eq($p['am'], $d->jobs->get($jobId)->amUserId);
        t_eq($p['am'], $d->assignments->team($jobId)->userFor(Role::AM));
        $owned = array_map(static fn ($i) => $i->jobId, $d->jobs->listOwnedBy($p['am'], $now));
        t_eq([$jobId], $owned);
        t_eq([], $d->jobs->listWithoutAm());
    },
    'job numbers: sequential allocations continue after legacy numbers and skip taken ones' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $now = $d->clock->now();
        $d->db->exec("INSERT INTO jobs (id, job_number, campaign_id, title, status) VALUES ('old7', 'MERC-007', :c, 'Old', 'Done')", ['c' => $c['campaign']]);
        $am = $d->users->findById($p['am']);
        $camp = $d->campaigns->get($c['campaign']);
        $a = $d->jobs->get($d->jobs->createDraft($camp, 'A', $am, $now))->jobNumber;
        // a legacy insert using the old COUNT method takes MERC-009 behind the counter's back
        $d->db->exec("INSERT INTO jobs (id, job_number, campaign_id, title, status) VALUES ('old9', 'MERC-009', :c, 'Old', 'Inbox')", ['c' => $c['campaign']]);
        $b = $d->jobs->get($d->jobs->createDraft($camp, 'B', $am, $now))->jobNumber;
        $c2 = $d->jobs->get($d->jobs->createDraft($camp, 'C', $am, $now))->jobNumber;
        t_eq(['MERC-008', 'MERC-010', 'MERC-011'], [$a, $b, $c2]);
    },
    'job numbers: two processes allocating at once never collide' => function (): void {
        $d = ts_deps();
        $c = bd_seed_campaign($d);
        $p = bd_seed_people($d);
        $script = dirname(__DIR__, 2) . '/support/alloc_numbers.php';
        $procs = [];
        for ($i = 0; $i < 2; $i++) {
            $cmd = [PHP_BINARY, $script, $d->config->dbPath, $c['campaign'], $p['am'], '15'];
            $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[$i . 'p'] = $pipes;
        }
        foreach ([0, 1] as $i) {
            $out = stream_get_contents($procs[$i . 'p'][1]);
            $err = stream_get_contents($procs[$i . 'p'][2]);
            t_eq(0, proc_close($procs[$i]), 'child exit: ' . $err);
            t_eq("ok\n", $out);
        }
        $numbers = array_map(static fn ($r) => (string) $r['job_number'], $d->db->query("SELECT job_number FROM jobs WHERE job_number LIKE 'MERC-%' ORDER BY job_number"));
        t_eq(30, count($numbers));
        t_eq(30, count(array_unique($numbers)));
        t_eq('MERC-001', $numbers[0]);
        t_eq('MERC-030', $numbers[29]);
        t_eq(31, (int) $d->db->scalar("SELECT next FROM job_counters WHERE prefix = 'MERC'"));
    },
];

<?php
declare(strict_types=1);

use App\Domain\ChecklistItem;
use App\Domain\JobAction;
use App\Domain\Notifications;
use App\Domain\Platform;
use App\Domain\Policy;
use App\Domain\PublicationAction;
use App\Domain\PublicationChecklist;
use App\Domain\PublicationRules;
use App\Domain\PublicationStatus;
use App\Domain\Role;
use App\Domain\SocialPolicy;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobAccess;
use App\Domain\Types\Publication;
use App\Domain\Types\SocialAssetState;
use App\Domain\Types\TransitionRequest;
use App\Domain\Types\User;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

function sc_pub(PublicationStatus $s, ?PublicationChecklist $c = null, ?string $at = null, string $url = ''): Publication
{
    return new Publication('p1', 'a1', 'j1', Platform::Instagram, $s, $at, $url, false, null, '', $c ?? PublicationChecklist::empty(), null, '', 2);
}

/** @param list<string> $statuses */
function sc_asset(string $id, array $statuses): SocialAssetState
{
    $out = [];
    foreach ($statuses as $s) {
        $out[] = PublicationStatus::from($s);
    }
    return new SocialAssetState($id, $out);
}

return [
    'platform: channels map to platforms, unknown text gives none' => function (): void {
        $cases = [
            ['Instagram', ['instagram']], ['IG', ['instagram']], ['FB, IG', ['facebook', 'instagram']], ['Facebook / Instagram Reels', ['facebook', 'instagram']],
            ['LinkedIn', ['linkedin']], ['Twitter', ['x']], ['X', ['x']], ['Google Business Profile', ['google_business']], ['GBP', ['google_business']],
            ['YouTube Shorts', ['youtube']], ['TikTok', ['tiktok']], ['Print', []], ['', []], ['Google Ads', []], ['Email', []],
        ];
        foreach ($cases as [$in, $want]) {
            $got = [];
            foreach (Platform::allFromChannel($in) as $p) {
                $got[] = $p->value;
            }
            t_eq($want, $got, $in);
        }
        t_eq(7, count(Platform::cases()));
        t_eq('Google Business', Platform::GoogleBusiness->label());
    },

    'social assets: social templates or social channels, never cancelled' => function (): void {
        $cases = [
            ['social-static', '', 'Inbox', true], ['social-story', '', 'Done', true], [null, 'Instagram', 'Done', true],
            ['print-ad', 'Print', 'Done', false], ['email-template', '', 'Inbox', false], ['social-static', '', 'Cancelled', false], [null, '', 'Done', false],
        ];
        foreach ($cases as [$tpl, $channel, $status, $want]) {
            t_eq($want, PublicationRules::isSocialAsset($tpl, $channel, $status), var_export([$tpl, $channel, $status], true));
        }
    },

    'checklist: empty, round trip, complete only with all five, malformed JSON is unticked' => function (): void {
        $c = PublicationChecklist::empty();
        t_eq(false, $c->isComplete());
        t_eq('Copy, Image, Link, Hashtags, Test result', $c->missingText());
        $c = $c->with(ChecklistItem::Copy, true, 'Approved v3')->with(ChecklistItem::Hashtags, true, '#open');
        $back = PublicationChecklist::fromJson($c->toJson());
        t_eq($c->toJson(), $back->toJson());
        t_eq(2, $back->tickedCount());
        t_eq('Approved v3', $back->entry(ChecklistItem::Copy)->note);
        t_eq(true, $back->allTicked()->isComplete());
        t_eq('#open', $back->allTicked()->entry(ChecklistItem::Hashtags)->note, 'tick all keeps notes');
        foreach ([null, '', 'nope', '[]', '{"copy":"yes"}', '{"copy":{"ok":"true"}}'] as $bad) {
            t_eq(0, PublicationChecklist::fromJson($bad)->tickedCount(), var_export($bad, true));
        }
        t_eq('', PublicationChecklist::noteProblem('Fine <b>'));
        t_true(PublicationChecklist::noteProblem("two\nlines") !== '');
        t_true(PublicationChecklist::noteProblem(str_repeat('x', 501)) !== '');
    },

    'scheduled_at and live link validation' => function (): void {
        $cases = [
            ['2026-10-12T09:30', '2026-10-12 09:30'], ['2026-10-12 09:30', '2026-10-12 09:30'], ['2026-10-12T09:30:15', '2026-10-12 09:30'],
            ['', null], ['2026-02-30T09:00', false], ['2026-10-12', false], ['12/10/2026 09:00', false], ['2026-10-12T24:00', false], ['1999-01-01T00:00', false],
            ["2026-10-12T09:30\n", '2026-10-12 09:30'], ['2026-10-12T09:30<script>', false],
        ];
        foreach ($cases as [$in, $want]) {
            t_eq($want, PublicationRules::normaliseScheduledAt($in), var_export($in, true));
        }
        foreach (['https://www.instagram.com/p/abc/', 'http://fb.com/x?y=1'] as $ok) {
            t_eq('', PublicationRules::liveUrlProblem($ok), $ok);
        }
        foreach (['', 'javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html,<b>x</b>', 'www.instagram.com/p/abc', 'https://x.com/a b', 'https://x.com/"onmouseover=', 'ftp://x.com/a', '//evil.com'] as $bad) {
            t_true(PublicationRules::liveUrlProblem($bad) !== '', 'rejected: ' . $bad);
        }
    },

    'publication moves: table of from, action, inputs and result' => function (): void {
        $full = PublicationChecklist::empty()->allTicked();
        $half = PublicationChecklist::empty()->with(ChecklistItem::Copy, true, '');
        // [status, checklist, scheduledAt stored, liveUrl stored, action, scheduled input, url input, reason, want status or error field]
        $cases = [
            ['checking', $full, null, '', 'ready', '', '', '', 'ready_to_schedule'],
            ['checking', $half, null, '', 'ready', '', '', '', 'err:checklist'],
            ['ready_to_schedule', $full, null, '', 'ready', '', '', '', 'err:status'],
            ['ready_to_schedule', $full, null, '', 'schedule', '2026-10-12T09:30', '', '', 'scheduled'],
            ['ready_to_schedule', $full, '2026-10-12 09:30', '', 'schedule', '', '', '', 'scheduled'],
            ['ready_to_schedule', $full, null, '', 'schedule', '', '', '', 'err:scheduled_at'],
            ['ready_to_schedule', $full, null, '', 'schedule', 'tomorrow', '', '', 'err:scheduled_at'],
            ['checking', $full, null, '', 'schedule', '2026-10-12T09:30', '', '', 'err:status'],
            ['scheduled', $full, '2026-10-12 09:30', '', 'go_live', '', 'https://instagram.com/p/1', '', 'live'],
            ['scheduled', $full, '2026-10-12 09:30', '', 'go_live', '', '', '', 'err:live_url'],
            ['scheduled', $full, '2026-10-12 09:30', '', 'go_live', '', 'javascript:alert(1)', '', 'err:live_url'],
            ['ready_to_schedule', $full, null, '', 'go_live', '', 'https://instagram.com/p/1', '', 'err:status'],
            ['live', $full, '2026-10-12 09:30', 'https://x.com/1', 'archive', '', '', 'Client pulled it', 'archived'],
            ['live', $full, '2026-10-12 09:30', 'https://x.com/1', 'archive', '', '', '  ', 'err:reason'],
            ['archived', $full, null, '', 'archive', '', '', 'again', 'err:status'],
            ['live', $full, '2026-10-12 09:30', 'https://x.com/1', 'reopen', '', '', 'Wrong link', 'scheduled'],
            ['scheduled', $full, '2026-10-12 09:30', '', 'reopen', '', '', 'Date moved', 'ready_to_schedule'],
            ['ready_to_schedule', $full, null, '', 'reopen', '', '', 'Image swap', 'checking'],
            ['ready_to_schedule', $full, null, '', 'reopen', '', '', '', 'err:reason'],
            ['checking', $full, null, '', 'reopen', '', '', 'x', 'err:status'],
            ['archived', $full, null, '', 'reopen', '', '', 'x', 'err:status'],
            ['live', $full, null, 'https://x.com/1', 'reopen', '', '', "multi\nline", 'err:reason'],
        ];
        foreach ($cases as $i => [$st, $cl, $at, $url, $act, $inAt, $inUrl, $reason, $want]) {
            $p = new Publication('p1', 'a1', 'j1', Platform::Instagram, PublicationStatus::from($st), $at, $url, false, null, '', $cl, null, '', 1);
            $o = PublicationRules::plan($p, PublicationAction::from($act), $inAt, $inUrl, $reason);
            if (str_starts_with($want, 'err:')) {
                t_eq(false, $o->ok(), "case $i should fail");
                t_true(isset($o->errors->errors[substr($want, 4)]), "case $i error field " . $want . ' got ' . json_encode($o->errors->errors));
            } else {
                t_eq(true, $o->ok(), "case $i: " . $o->errors->first());
                t_eq($want, $o->to?->value, "case $i");
            }
        }
        $o = PublicationRules::plan(sc_pub(PublicationStatus::ReadyToSchedule), PublicationAction::Schedule, '2026-10-12T09:30', '', '');
        t_eq('2026-10-12 09:30', $o->scheduledAt, 'normalised value is written');
    },

    'asset and job targets follow the lowest non-archived status' => function (): void {
        $cases = [
            [[], null],
            [[sc_asset('a', [])], Stage::ApprovedClient],
            [[sc_asset('a', ['checking'])], Stage::ApprovedClient],
            [[sc_asset('a', ['ready_to_schedule'])], Stage::ReadyToSchedule],
            [[sc_asset('a', ['ready_to_schedule']), sc_asset('b', [])], Stage::ApprovedClient],
            [[sc_asset('a', ['live', 'scheduled']), sc_asset('b', ['live'])], Stage::Scheduled],
            [[sc_asset('a', ['live', 'archived']), sc_asset('b', ['live'])], Stage::Live],
            [[sc_asset('a', ['archived']), sc_asset('b', ['scheduled'])], Stage::Scheduled],
            [[sc_asset('a', ['archived'])], null],
        ];
        foreach ($cases as $i => [$assets, $want]) {
            t_eq($want, PublicationRules::jobTarget($assets), "case $i");
        }
        t_eq(null, PublicationRules::assetStatus([PublicationStatus::Archived]));
        t_eq(PublicationStatus::Checking, PublicationRules::assetStatus([]));
    },

    'job steps: forward one named move at a time, back only on reopen, only in the Social window' => function (): void {
        $cases = [
            [Stage::ApprovedClient, Stage::ReadyToSchedule, false, ['ready_to_schedule']],
            [Stage::ApprovedClient, Stage::Live, false, ['ready_to_schedule', 'schedule', 'go_live']],
            [Stage::ReadyToSchedule, Stage::Scheduled, false, ['schedule']],
            [Stage::Live, Stage::Live, false, []],
            [Stage::Live, Stage::Scheduled, false, []],
            [Stage::Live, Stage::Scheduled, true, ['social_step_back']],
            [Stage::Scheduled, Stage::ApprovedClient, true, ['social_step_back', 'social_step_back']],
            [Stage::ApprovedInternal, Stage::ReadyToSchedule, false, []],
            [Stage::Done, Stage::Live, false, []],
            [Stage::Waiting, Stage::Live, false, []],
            [Stage::ApprovedClient, null, false, []],
        ];
        foreach ($cases as $i => [$from, $to, $back, $want]) {
            $got = [];
            foreach (PublicationRules::jobSteps($from, $to, $back) as $a) {
                $got[] = $a->value;
            }
            t_eq($want, $got, "case $i");
        }
        // Every step is a real Transitions move that lands where the next one starts.
        $stage = Stage::ApprovedClient;
        foreach (PublicationRules::jobSteps(Stage::ApprovedClient, Stage::Live, false) as $a) {
            $o = Transitions::plan($stage, null, new TransitionRequest($a), true);
            t_true($o->ok(), $o->errors->first());
            $stage = $o->to;
        }
        t_eq(Stage::Live, $stage);
        $back = Transitions::plan(Stage::Live, null, new TransitionRequest(JobAction::SocialStepBack, null, 'Link was wrong'), true);
        t_eq(Stage::Scheduled, $back->to);
        t_eq('Approved (External)', $back->to->toLegacy('Approved (External)'), 'legacy mirror stays Approved (External)');
        t_eq(false, Transitions::plan(Stage::Live, null, new TransitionRequest(JobAction::SocialStepBack), true)->ok(), 'reason required');
        t_eq(false, Transitions::plan(Stage::ApprovedClient, null, new TransitionRequest(JobAction::SocialStepBack, null, 'x'), true)->ok());
        t_eq(null, Transitions::matrixId(JobAction::SocialStepBack, Stage::Live), 'no matrix row: Policy never allows it from the board');
        t_eq(false, Policy::canTransition(bf_user(Role::COO), bf_access('live'), JobAction::SocialStepBack)->allowed);
    },

    'scheduled today and overdue for a link' => function (): void {
        $now = new DateTimeImmutable('2026-10-12 10:00:00');
        t_true(PublicationRules::isScheduledOn('2026-10-12 09:30', '2026-10-12'));
        t_true(!PublicationRules::isScheduledOn('2026-10-13 09:30', '2026-10-12'));
        t_true(!PublicationRules::isScheduledOn(null, '2026-10-12'));
        t_true(PublicationRules::isOverdueForLink(sc_pub(PublicationStatus::Scheduled, null, '2026-10-12 09:30'), $now));
        t_true(!PublicationRules::isOverdueForLink(sc_pub(PublicationStatus::Scheduled, null, '2026-10-12 11:30'), $now));
        t_true(!PublicationRules::isOverdueForLink(sc_pub(PublicationStatus::Live, null, '2026-10-12 09:30', 'https://x.com/1'), $now));
        t_true(!PublicationRules::isOverdueForLink(sc_pub(PublicationStatus::ReadyToSchedule, null, '2026-10-01 09:30'), $now));
    },

    'social recipients: AM (or creator) plus PM and Producer on ready; Social plus AM on reopen; never the actor' => function (): void {
        $team = bf_team(['AM' => 'am1', 'PM' => 'pm1', 'Producer' => 'prd1', 'Social' => 'soc1', 'Traffic' => 'tr1']);
        $cases = [
            [Notifications::PUBLICATION_READY, $team, null, 'soc1', ['am1', 'pm1', 'prd1']],
            [Notifications::PUBLICATION_SCHEDULED, $team, null, 'soc1', ['am1']],
            [Notifications::PUBLICATION_LIVE, $team, null, 'soc1', ['am1']],
            [Notifications::PUBLICATION_PROMOTED, $team, null, 'soc1', ['am1']],
            [Notifications::PUBLICATION_REOPENED, $team, null, 'coo1', ['am1', 'soc1']],
            [Notifications::PUBLICATION_REOPENED, $team, null, 'soc1', ['am1']],
            [Notifications::PUBLICATION_LIVE, bf_team(['Social' => 'soc1']), 'creator1', 'soc1', ['creator1']],
            [Notifications::PUBLICATION_LIVE, bf_team([]), null, 'soc1', []],
            [Notifications::PUBLICATION_READY, $team, null, 'am1', ['pm1', 'prd1']],
            ['unknown', $team, null, 'soc1', []],
        ];
        foreach ($cases as $i => [$ev, $t, $creator, $actor, $want]) {
            t_eq($want, Notifications::socialRecipients($ev, $t, $creator, $actor), "case $i $ev");
        }
    },

    'social policy agrees with the social_* rows of policy-matrix.json' => function (): void {
        $m = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/roles/policy-matrix.json'), true);
        $fns = [
            'social_view_queue' => static fn (User $u, JobAccess $a) => SocialPolicy::canViewJob($u, $a),
            'social_edit_checklist' => static fn (User $u, JobAccess $a) => SocialPolicy::canEditChecklist($u, $a),
            'social_set_ready_to_schedule' => static fn (User $u, JobAccess $a) => SocialPolicy::canSetReadyToSchedule($u, $a),
            'social_set_scheduled' => static fn (User $u, JobAccess $a) => SocialPolicy::canSetScheduled($u, $a),
            'social_set_live' => static fn (User $u, JobAccess $a) => SocialPolicy::canSetLive($u, $a),
            'social_edit_live_link' => static fn (User $u, JobAccess $a) => SocialPolicy::canEditLiveLink($u, $a),
            'social_set_promoted' => static fn (User $u, JobAccess $a) => SocialPolicy::canSetPromoted($u, $a),
            'social_archive_post' => static fn (User $u, JobAccess $a) => SocialPolicy::canArchive($u, $a),
            'social_set_checking' => static fn (User $u, JobAccess $a) => SocialPolicy::canSetChecking($u, $a),
            'social_edit_test_result' => static fn (User $u, JobAccess $a) => SocialPolicy::canEditTestResult($u, $a),
        ];
        $seen = [];
        $checked = 0;
        foreach ($m['actions'] as $action) {
            $id = (string) $action['id'];
            if (!str_starts_with($id, 'social_')) {
                continue;
            }
            t_true(isset($fns[$id]), 'SocialPolicy implements ' . $id);
            $seen[] = $id;
            foreach ($m['roles'] as $roleName) {
                $role = Role::from($roleName);
                $cell = (string) $action['decisions'][$roleName];
                t_eq($cell, SocialPolicy::rule($id, $role)->value, "$id cell for $roleName");
                foreach (['unrelated', 'assigned', 'asset_assigned', 'creator'] as $rel) {
                    $o = [];
                    if ($rel === 'assigned') {
                        $o['assignments'] = [new Assignment($role, 'actor', 'Actor', $role)];
                    } elseif ($rel === 'asset_assigned') {
                        $o['assetAssigneeIds'] = ['actor'];
                    } elseif ($rel === 'creator') {
                        $o['creatorId'] = 'actor';
                    }
                    foreach (['approved_client', 'ready_to_schedule', 'scheduled', 'live'] as $stage) {
                        $want = match ($cell) {
                            'allow' => true,
                            'deny' => false,
                            'assigned' => $rel === 'assigned' || $rel === 'asset_assigned',
                            'assigned_or_creator' => $rel !== 'unrelated',
                            default => throw new TestFailure('unexpected cell ' . $cell),
                        };
                        $got = $fns[$id](bf_user($role, 'actor'), bf_access($stage, $o));
                        t_eq($want, $got->allowed, "$id: $roleName ($rel, $stage): " . $got->reason);
                        $checked++;
                    }
                }
            }
        }
        t_eq(array_keys($fns), $seen, 'every social_* row is covered, in order');
        t_true($checked >= 10 * 14 * 4 * 4, 'checked ' . $checked);
    },

    'social policy: nothing before client approval; writes stop at done; the AM never sets Live (SOC-5, SOC-6)' => function (): void {
        $soc = bf_user(Role::Social, 'soc1');
        $slot = ['assignments' => [new Assignment(Role::Social, 'soc1', 'Sol', Role::Social)]];
        foreach (['draft', 'briefed', 'in_progress', 'in_review', 'approved_internal', 'waiting', 'on_hold', 'cancelled', 'archived'] as $st) {
            t_eq(false, SocialPolicy::canViewJob($soc, bf_access($st, $slot))->allowed, "view $st");
            t_eq(false, SocialPolicy::canSetReadyToSchedule($soc, bf_access($st, $slot))->allowed, "ready $st");
        }
        t_eq(true, SocialPolicy::canViewJob($soc, bf_access('done', $slot))->allowed, 'done stays readable');
        t_eq(false, SocialPolicy::canEditLiveLink($soc, bf_access('done', $slot))->allowed, 'done is read only');
        $am = bf_user(Role::AM, 'am1');
        $own = ['assignments' => [new Assignment(Role::AM, 'am1', 'Amy', Role::AM)]];
        t_eq(true, SocialPolicy::canViewJob($am, bf_access('scheduled', $own))->allowed);
        t_eq(false, SocialPolicy::canSetLive($am, bf_access('scheduled', $own))->allowed);
        t_eq(false, SocialPolicy::canViewJob($am, bf_access('scheduled'))->allowed, 'not their job');
        t_eq(false, SocialPolicy::canViewJob(bf_user(Role::Designer), bf_access('scheduled'))->allowed);
        // A Social user holding another slot is not "assigned" for Social (the slot is Social's).
        $other = ['assignments' => [new Assignment(Role::Copywriter, 'soc1', 'Sol', Role::Social)]];
        t_eq(false, SocialPolicy::canSetLive($soc, bf_access('scheduled', $other))->allowed);
        t_eq(true, SocialPolicy::canReopen($soc, bf_access('live', $slot))->allowed);
        t_eq(true, SocialPolicy::canReopen(bf_user(Role::ECD), bf_access('live'))->allowed);
        t_eq(false, SocialPolicy::canReopen($am, bf_access('live', $own))->allowed);
    },

    'social policy: queue page roles and who fills the Social slot after send' => function (): void {
        $cases = [
            [Role::COO, true, true], [Role::ECD, true, true], [Role::Social, true, true], [Role::AM, true, false], [Role::PM, true, false],
            [Role::Producer, true, false], [Role::Traffic, false, false], [Role::Designer, false, false], [Role::CD, false, false], [Role::Client, false, false],
        ];
        foreach ($cases as [$role, $page, $whole]) {
            t_eq($page, SocialPolicy::canViewQueue(bf_user($role))->allowed, 'page ' . $role->value);
            t_eq($whole, SocialPolicy::seesWholeQueue(bf_user($role)), 'whole ' . $role->value);
        }
        $own = static fn (Role $r): array => ['assignments' => [new Assignment($r, 'actor', 'A', $r)]];
        $assign = [
            [Role::AM, 'briefed', $own(Role::AM), true], [Role::AM, 'briefed', [], false], [Role::AM, 'briefed', ['creatorId' => 'actor'], true],
            [Role::AM, 'draft', $own(Role::AM), false], [Role::PM, 'approved_client', $own(Role::PM), true], [Role::Producer, 'live', $own(Role::Producer), true],
            [Role::Traffic, 'in_progress', [], true], [Role::COO, 'scheduled', [], true], [Role::ECD, 'briefed', [], true],
            [Role::Social, 'approved_client', $own(Role::Social), false], [Role::Designer, 'briefed', [], false], [Role::COO, 'done', [], false],
            [Role::COO, 'cancelled', [], false],
        ];
        foreach ($assign as $i => [$role, $stage, $o, $want]) {
            t_eq($want, SocialPolicy::canAssignSocial(bf_user($role, 'actor'), bf_access($stage, $o))->allowed, "assign case $i " . $role->value . " $stage");
        }
    },
];

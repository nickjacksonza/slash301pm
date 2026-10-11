<?php
declare(strict_types=1);

use App\Domain\AssetOverride;
use App\Domain\AssetPlan;
use App\Domain\AssetStatus;
use App\Domain\AssetTemplates;
use App\Domain\BriefVersion;
use App\Domain\Links;
use App\Domain\MyDay;
use App\Domain\MyDayMode;
use App\Domain\Notifications;
use App\Domain\Platform;
use App\Domain\PublicationAction;
use App\Domain\PublicationChecklist;
use App\Domain\PublicationRules;
use App\Domain\PublicationStatus;
use App\Domain\Role;
use App\Domain\SocialPolicy;
use App\Domain\Stage;
use App\Domain\Types\Activity;
use App\Domain\Types\Assignment;
use App\Domain\Types\AssetNaming;
use App\Domain\Types\BriefLine;
use App\Domain\Types\MyDayChange;
use App\Domain\Types\MyDayJob;
use App\Domain\Types\Publication;
use App\Domain\Types\Team;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

/** Owner decisions 2026-10: brand logos and the My day brand row, overrides, role tasks. */
function od_job(string $id, string $brand, Stage $stage = Stage::InProgress, string $logo = ''): MyDayJob
{
    return new MyDayJob($id, strtoupper($id), 'T ' . $id, 'Camp', 'Brand ' . $brand, $stage, null, '', new BriefVersion(1, 0, 0), '2026-10-01 08:00:00', false,
        '2026-10-08', false, false, false, $brand, $logo);
}

return [
    'templates: the three role tasks and their default roles; the 18 legacy templates have none' => function (): void {
        $cases = [['utm-links', 'UTM link generation', Role::Developer], ['campaign-hashtags', 'Campaign hashtags', Role::SEO], ['asset-test-report', 'Asset test report', Role::Producer],
            ['social-static', 'Social Post (Static)', null], ['presentation', 'Presentation', null]];
        foreach ($cases as [$id, $name, $role]) {
            $t = AssetTemplates::find($id);
            t_true($t !== null, $id);
            t_eq($name, $t->name, $id);
            t_eq($role, $t->defaultRole, $id);
            t_true(!str_starts_with($id, 'social-') || $role === null, 'role tasks are never social assets');
        }
        t_eq(21, count(AssetTemplates::all()));
        t_eq(['utm-links', 'campaign-hashtags', 'asset-test-report'], array_map(static fn ($t) => $t->id, AssetTemplates::roleTasks()));
        // AssetPlan carries the default role to the store, which assigns the slot holder.
        $line = static fn (string $id, ?string $tpl): BriefLine => new BriefLine($id, 'b', 'j', $tpl, $tpl ?? 'Other', 1, '', '', '', false, null, 0);
        $plan = AssetPlan::plan([$line('l1', 'utm-links'), $line('l2', 'campaign-hashtags'), $line('l3', 'asset-test-report'), $line('l4', 'social-static'), $line('l5', null)], [],
            new AssetNaming('MERC-001', 'Meridian', 'Launch', '2026-10-20', new DateTimeImmutable('2026-10-09 09:00:00')), 7);
        t_eq([Role::Developer, Role::SEO, Role::Producer, null, null], array_map(static fn ($p) => $p->defaultRole, $plan->create));
        t_eq([7, 8, 9, 10, 11], array_map(static fn ($p) => $p->sortOrder, $plan->create), 'sortFrom');
    },

    'links: brand logos are https only' => function (): void {
        foreach (['https://example.com/logo.png', 'HTTPS://cdn.example.com/a/b.svg?v=2'] as $ok) {
            t_eq('', Links::logoUrlProblem($ok), $ok);
            t_true(Links::isHttpsUrl($ok), $ok);
        }
        t_eq('', Links::logoUrlProblem(''), 'empty clears the logo');
        foreach (['javascript:alert(1)', 'http://example.com/logo.png', 'data:image/png;base64,AAAA', '//evil.com/x.png', 'https://x.com/a b.png',
            'https://x.com/"onerror=1', 'https://' . str_repeat('a', 2001) . '.com', ' https://x.com/a.png', 'ftp://x.com/a.png'] as $bad) {
            t_true(Links::logoUrlProblem($bad) !== '', 'rejected: ' . substr($bad, 0, 40));
        }
        $b = \App\Domain\Types\Brand::fromRow(['id' => 'b', 'name' => 'B', 'prefix' => 'B', 'logo_url' => 'javascript:alert(1)']);
        t_eq('', $b->safeLogoUrl(), 'a hand-written bad row never renders');
    },

    'my day: brand row lists the brands of the shown jobs with counts, by name; the filter accepts only those' => function (): void {
        $jobs = [od_job('a', 'zed'), od_job('b', 'alp', Stage::InProgress, 'https://x.com/l.png'), od_job('c', 'alp'), od_job('d', ''),
            od_job('e', 'old', Stage::Done), od_job('f', 'drf', Stage::Draft)];
        $owner = MyDay::brands($jobs, MyDayMode::Owner);
        t_eq(['alp', 'drf', 'zed'], array_map(static fn ($b) => $b->id, $owner), 'closed jobs and jobs without a brand get no button');
        t_eq([2, 1, 1], array_map(static fn ($b) => $b->jobCount, $owner));
        t_eq('https://x.com/l.png', $owner[0]->logoUrl);
        $assigned = MyDay::brands($jobs, MyDayMode::Assigned);
        t_eq(['alp', 'zed'], array_map(static fn ($b) => $b->id, $assigned), 'assigned users never see drafts');
        t_eq('alp', MyDay::selectedBrand('alp', $assigned));
        t_eq('', MyDay::selectedBrand('drf', $assigned), 'a brand outside the row means All');
        t_eq('', MyDay::selectedBrand("x' OR 1", $assigned));
        t_eq(['b', 'c'], array_map(static fn ($j) => $j->jobId, MyDay::jobsOfBrand($jobs, 'alp')));
        t_eq(6, count(MyDay::jobsOfBrand($jobs, '')));
        $ch = static fn (string $id, string $brand): MyDayChange => new MyDayChange(new Activity($id, 'j', 'x', 'X', 'brief_updated', 'job', 'j', [], '2026-10-09 06:00:00'), 'J', 'T', true, $brand);
        t_eq(['1'], array_map(static fn ($c) => $c->activity->id, MyDay::changesOfBrand([$ch('1', 'alp'), $ch('2', 'zed'), $ch('3', '')], 'alp')));
        // The filtered build: only that brand's overdue jobs.
        $r = MyDay::buildAssigned('me', MyDayMode::Assigned, MyDay::jobsOfBrand($jobs, 'zed'), [], '2026-10-01 00:00:00', new DateTimeImmutable('2026-10-09 09:00:00 +02:00'));
        t_eq(['a'], array_map(static fn ($i) => $i->jobId, $r->overdue->items));
    },

    'overrides: reason, values, range; recipients assignee, AM or creator, CD, never the actor or a client' => function (): void {
        t_true(AssetOverride::reasonProblem('') !== '');
        t_true(AssetOverride::reasonProblem("two\nlines") !== '');
        t_true(AssetOverride::reasonProblem(str_repeat('x', 1001)) !== '');
        t_eq('', AssetOverride::reasonProblem('Crunch: client deadline <b>'));
        t_eq('', AssetOverride::assetProblem('Inbox', 'Done'));
        t_eq('', AssetOverride::assetProblem('', 'Approved (External)'));
        t_true(AssetOverride::assetProblem('Done', 'Done') !== '', 'same value');
        t_true(AssetOverride::assetProblem('Inbox', 'Hacked') !== '', 'unknown value');
        t_true(AssetOverride::assetProblem('Inbox', 'done') !== '', 'case matters (legacy values)');
        t_eq(16, count(AssetStatus::OVERRIDE_VALUES));
        t_eq('', AssetOverride::publicationProblem(PublicationStatus::Checking, PublicationStatus::Live));
        t_true(AssetOverride::publicationProblem(PublicationStatus::Live, PublicationStatus::Live) !== '');
        t_true(AssetOverride::publicationProblem(PublicationStatus::Live, null) !== '');
        $now = new DateTimeImmutable('2026-10-10 09:00:00 +02:00');
        $r = AssetOverride::range('', '', $now);
        t_eq(['2026-09-11', '2026-10-10', '2026-09-10 22:00:00', '2026-10-10 22:00:00'], [$r->fromDate, $r->toDate, $r->fromUtc, $r->toUtc]);
        $r = AssetOverride::range('2026-10-09', '2026-10-01', $now);
        t_eq(['2026-10-01', '2026-10-09'], [$r->fromDate, $r->toDate], 'reversed range is swapped');
        t_eq('2026-09-11', AssetOverride::range('nope', '2026-02-30', $now)->fromDate, 'bad values fall back');
        $team = new Team([new Assignment(Role::AM, 'am1', 'A', Role::AM), new Assignment(Role::CD, 'cd1', 'C', Role::CD), new Assignment(Role::Client, 'cl1', 'K', Role::Client)]);
        t_eq(['des1', 'am1', 'cd1'], Notifications::overrideRecipients($team, 'cr1', 'des1', 'tr1'));
        t_eq(['am1', 'cd1'], Notifications::overrideRecipients($team, null, 'tr1', 'tr1'), 'never the actor');
        t_eq(['cr1'], Notifications::overrideRecipients(new Team([]), 'cr1', null, 'tr1'), 'creator when nobody holds the AM slot');
        t_eq(['am1', 'cd1'], Notifications::overrideRecipients($team, null, 'cl1', 'tr1'), 'never a client slot');
    },

    'social: recheck goes back to checking from ready or scheduled only, with a reason; Producer rights on own jobs' => function (): void {
        $full = PublicationChecklist::empty()->allTicked();
        $cases = [['ready_to_schedule', 'Test failed', 'checking'], ['scheduled', 'Broken UTM', 'checking'], ['scheduled', '', 'err:reason'],
            ['checking', 'x', 'err:status'], ['live', 'x', 'err:status'], ['archived', 'x', 'err:status']];
        foreach ($cases as $i => [$st, $reason, $want]) {
            $p = new Publication('p1', 'a1', 'j1', Platform::Instagram, PublicationStatus::from($st), '2026-10-12 09:30', '', false, null, '', $full, null, '', 1);
            $o = PublicationRules::plan($p, PublicationAction::Recheck, '', '', $reason);
            if (str_starts_with($want, 'err:')) {
                t_true(isset($o->errors->errors[substr($want, 4)]), "case $i");
            } else {
                t_eq($want, $o->to?->value, "case $i");
            }
        }
        t_eq(Notifications::PUBLICATION_RECHECKED, PublicationAction::Recheck->verb());
        $team = new Team([new Assignment(Role::AM, 'am1', 'A', Role::AM), new Assignment(Role::Social, 'soc1', 'S', Role::Social)]);
        t_eq(['am1', 'soc1'], Notifications::socialRecipients(Notifications::PUBLICATION_RECHECKED, $team, null, 'prd1'));
        $prd = bf_user(Role::Producer, 'prd1');
        $mine = bf_access('scheduled', ['assignments' => [new Assignment(Role::Producer, 'prd1', 'P', Role::Producer)]]);
        $asset = bf_access('approved_client', ['assetAssigneeIds' => ['prd1']]);
        t_true(SocialPolicy::canSetChecking($prd, $mine)->allowed);
        t_true(SocialPolicy::canAct($prd, $mine, PublicationAction::Recheck)->allowed);
        t_true(SocialPolicy::canEditTestResult($prd, $asset)->allowed, 'an asset test report assigned to them');
        t_true(!SocialPolicy::canEditChecklist($prd, $mine)->allowed, 'not the whole checklist');
        t_true(!SocialPolicy::canSetReadyToSchedule($prd, $mine)->allowed);
        t_true(!SocialPolicy::canSetChecking($prd, bf_access('scheduled'))->allowed, 'not someone else\'s job');
        t_true(!SocialPolicy::canSetChecking($prd, bf_access('in_progress', ['assignments' => [new Assignment(Role::Producer, 'prd1', 'P', Role::Producer)]]))->allowed, 'only in the Social window');
        t_true(SocialPolicy::canEditTestResult(bf_user(Role::Social, 's'), bf_access('approved_client', ['assignments' => [new Assignment(Role::Social, 's', 'S', Role::Social)]]))->allowed);
        t_true(!SocialPolicy::canSetChecking(bf_user(Role::AM, 'am1'), bf_access('scheduled', ['creatorId' => 'am1']))->allowed, 'the AM does not');
    },
];

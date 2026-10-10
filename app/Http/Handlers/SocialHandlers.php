<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\ChecklistItem;
use App\Domain\Dates;
use App\Domain\Platform;
use App\Domain\PublicationAction;
use App\Domain\PublicationRules;
use App\Domain\PublicationStatus;
use App\Domain\Role;
use App\Domain\Signals\PublicationSignals;
use App\Domain\Signals\SignalInput;
use App\Domain\SocialPolicy;
use App\Domain\SocialTab;
use App\Domain\Types\JobAccess;
use App\Domain\Types\Publication;
use App\Domain\Types\SocialAsset;
use App\Domain\Types\SocialWrite;
use App\Domain\Types\User;
use App\Http\BriefState;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\ui\SelectOption;
use App\View\VM\BriefDocVM;
use App\View\VM\SocialDayItemVM;
use App\View\VM\SocialDayVM;
use App\View\VM\SocialJobVM;
use App\View\VM\SocialPermsVM;
use App\View\VM\SocialQueueVM;
use DateTimeZone;

/**
 * Social publishing (docs/roles.md 2.13):
 *   GET /social, GET /social/list           the queue (tabs, brand, platform, mine)
 *   GET /social/jobs/{id}                   one job's social assets and posts
 *   POST /social/jobs/{id}/ready            mark the whole brief Ready to schedule
 *   POST /social/assets/{aid}/platforms/{platform}, DELETE /social/publications/{pid}
 *   PATCH /social/publications/{pid}/{checklist|schedule|live-link|promoted}
 *   POST /social/publications/{pid}/{ready|scheduled|live|archive|reopen|recheck}
 * Every write checks SocialPolicy, the publication's row_version and the rules
 * in PublicationRules, then answers with the re-rendered asset section and job
 * header (outer patches by id, so transport=html works) and a toast. A refused
 * or stale write re-renders from the database too. Budget never appears.
 */
final class SocialHandlers
{
    // ---- reads --------------------------------------------------------------------

    public static function page(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $dec = SocialPolicy::canViewQueue($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $vm = self::queueVm($d, $u, $r->query('tab'), $r->query('brand'), $r->query('platform'), $r->query('mine') === '1');
        return Shell::page($r, $d, 'Social', 'social', page_social_queue($vm));
    }

    /** GET /social/list: sq.tab, sq.brand, sq.platform, sq.mine. Patches #social-list. */
    public static function list(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $dec = SocialPolicy::canViewQueue($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $q = SignalInput::obj($r->signals(), 'sq');
        $vm = self::queueVm($d, $u, SignalInput::str($q, 'tab'), SignalInput::str($q, 'brand'), SignalInput::str($q, 'platform'), SignalInput::bool($q, 'mine'));
        return Response::events(PatchElements::html(partial_social_list($vm)));
    }

    public static function job(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $page = SocialPolicy::canViewQueue($u);
        $access = $d->jobs->access($r->pathValue('id'));
        if (!$page->allowed || $access === null) {
            return $access === null ? Response::notFound() : Shell::deny($r, $page->reason);
        }
        $dec = SocialPolicy::canViewJob($u, $access);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $vm = self::jobVm($d, $u, $access);
        if ($vm === null) {
            return Response::notFound();
        }
        return Shell::page($r, $d, 'Social · ' . $vm->jobNumber, 'social', page_social_job($vm));
    }

    // ---- job-level write ------------------------------------------------------------

    public static function markReady(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $access = $d->jobs->access($r->pathValue('id'));
        if ($access === null || !SocialPolicy::canViewJob($u, $access)->allowed) {
            return Response::events(Toast::error('This job is not in the Social queue.'));
        }
        $dec = SocialPolicy::canSetReadyToSchedule($u, $access);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $w = $d->publications->markJobReady($access->jobId, $u->id, $d->clock->now());
        return self::replyJob($d, $u, $access->jobId, self::toastFor($w, 'Every post is Ready to schedule. The AM has been told.'));
    }

    // ---- platforms -------------------------------------------------------------------

    public static function addPlatform(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $assetId = $r->pathValue('aid');
        $jobId = $d->publications->assetJobId($assetId);
        $access = $jobId === null ? null : $d->jobs->access($jobId);
        if ($jobId === null || $access === null || !SocialPolicy::canViewJob($u, $access)->allowed) {
            return Response::events(Toast::error('This asset is not in the Social queue.'));
        }
        $dec = SocialPolicy::canEditChecklist($u, $access);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $platform = Platform::tryFrom($r->pathValue('platform'));
        if ($platform === null || $d->publications->asset($jobId, $assetId) === null) {
            return Response::events(Toast::error('Unknown platform or not a social asset.'));
        }
        $w = $d->publications->addPlatform($jobId, $assetId, $platform, $u->id, $d->clock->now());
        return self::replyAsset($d, $u, $access, $assetId, self::toastFor($w, $platform->label() . ' added. Work through its checklist.'));
    }

    public static function removePlatform(Request $r, Deps $d): Response
    {
        return self::withPublication($r, $d, static function (User $u, Publication $p, JobAccess $a, PublicationSignals $s) use ($r, $d): Response {
            $dec = SocialPolicy::canEditChecklist($u, $a);
            if (!$dec->allowed) {
                return Shell::deny($r, $dec->reason);
            }
            if ($p->status !== PublicationStatus::Checking) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error('Only a post still being checked can be removed. Archive it instead.'));
            }
            $w = $d->publications->removePlatform($p, $s->rowVersion, $u->id, $d->clock->now());
            return self::replyAsset($d, $u, $a, $p->assetId, self::toastFor($w, $p->platform->label() . ' removed.'));
        });
    }

    // ---- field edits -----------------------------------------------------------------

    public static function checklist(Request $r, Deps $d): Response
    {
        return self::withPublication($r, $d, static function (User $u, Publication $p, JobAccess $a, PublicationSignals $s) use ($r, $d): Response {
            $dec = SocialPolicy::canEditChecklist($u, $a);
            $checklist = $s->checklist;
            if (!$dec->allowed) {
                // The job's Producer may change the Test result item only (asset test reports); the rest is kept as stored.
                $only = SocialPolicy::canEditTestResult($u, $a);
                if (!$only->allowed) {
                    return Shell::deny($r, $dec->reason);
                }
                $item = ChecklistItem::TestResult;
                $checklist = $p->checklist->with($item, $s->checklist->entry($item)->ok, $s->checklist->entry($item)->note);
            }
            if (!PublicationRules::canEditChecklist($p->status)) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error('The checklist is locked once the post is Ready to schedule. Move it back a step to change it.'));
            }
            if ($s->noteProblem !== '') {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error($s->noteProblem));
            }
            $w = $d->publications->saveChecklist($p, $s->rowVersion, $checklist, $u->id, $d->clock->now());
            return self::replyAsset($d, $u, $a, $p->assetId, self::toastFor($w, 'Saved.'));
        });
    }

    public static function schedule(Request $r, Deps $d): Response
    {
        return self::withPublication($r, $d, static function (User $u, Publication $p, JobAccess $a, PublicationSignals $s) use ($r, $d): Response {
            $dec = SocialPolicy::canSetScheduled($u, $a);
            if (!$dec->allowed) {
                return Shell::deny($r, $dec->reason);
            }
            if (!PublicationRules::canEditScheduledAt($p->status)) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error('The scheduled time can change while the post is ready or scheduled.'));
            }
            $at = PublicationRules::normaliseScheduledAt($s->scheduledAt);
            if ($at === false || ($at === null && $p->status === PublicationStatus::Scheduled)) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error('Enter the scheduled time as a date and time.'));
            }
            $w = $d->publications->saveScheduledAt($p, $s->rowVersion, $at, $u->id, $d->clock->now());
            return self::replyAsset($d, $u, $a, $p->assetId, self::toastFor($w, 'Scheduled time saved.'));
        });
    }

    public static function liveLink(Request $r, Deps $d): Response
    {
        return self::withPublication($r, $d, static function (User $u, Publication $p, JobAccess $a, PublicationSignals $s) use ($r, $d): Response {
            $dec = SocialPolicy::canEditLiveLink($u, $a);
            if (!$dec->allowed) {
                return Shell::deny($r, $dec->reason);
            }
            if (!PublicationRules::canEditLiveFields($p->status)) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error('The live link is added once the post is scheduled.'));
            }
            if ($s->liveUrl !== '' || $p->status === PublicationStatus::Live) {
                $problem = PublicationRules::liveUrlProblem($s->liveUrl);
                if ($problem !== '') {
                    return self::replyAsset($d, $u, $a, $p->assetId, Toast::error($problem));
                }
            }
            $w = $d->publications->saveLiveUrl($p, $s->rowVersion, $s->liveUrl, $u->id, $d->clock->now());
            return self::replyAsset($d, $u, $a, $p->assetId, self::toastFor($w, $p->status === PublicationStatus::Live ? 'Live link changed. Social and the AM have been told.' : 'Live link saved.'));
        });
    }

    public static function promoted(Request $r, Deps $d): Response
    {
        return self::withPublication($r, $d, static function (User $u, Publication $p, JobAccess $a, PublicationSignals $s) use ($r, $d): Response {
            $dec = SocialPolicy::canSetPromoted($u, $a);
            if (!$dec->allowed) {
                return Shell::deny($r, $dec->reason);
            }
            if (!PublicationRules::canEditLiveFields($p->status)) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error('Promoted is ticked once the post is scheduled or live.'));
            }
            if ($s->noteProblem !== '') {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error($s->noteProblem));
            }
            $w = $d->publications->savePromoted($p, $s->rowVersion, $s->promoted, $s->promotedNote, $u->id, $d->clock->now());
            return self::replyAsset($d, $u, $a, $p->assetId, self::toastFor($w, $s->promoted ? 'Marked as promoted on ' . $p->platform->label() . '.' : 'Saved.'));
        });
    }

    // ---- status moves ------------------------------------------------------------------

    public static function ready(Request $r, Deps $d): Response
    {
        return self::move($r, $d, PublicationAction::Ready);
    }

    public static function scheduled(Request $r, Deps $d): Response
    {
        return self::move($r, $d, PublicationAction::Schedule);
    }

    public static function live(Request $r, Deps $d): Response
    {
        return self::move($r, $d, PublicationAction::GoLive);
    }

    public static function archive(Request $r, Deps $d): Response
    {
        return self::move($r, $d, PublicationAction::Archive);
    }

    public static function reopen(Request $r, Deps $d): Response
    {
        return self::move($r, $d, PublicationAction::Reopen);
    }

    /** POST /social/publications/{pid}/recheck: back to checking (the job's Producer, Social, COO, ECD). */
    public static function recheck(Request $r, Deps $d): Response
    {
        return self::move($r, $d, PublicationAction::Recheck);
    }

    private static function move(Request $r, Deps $d, PublicationAction $action): Response
    {
        return self::withPublication($r, $d, static function (User $u, Publication $p, JobAccess $a, PublicationSignals $s) use ($r, $d, $action): Response {
            $dec = SocialPolicy::canAct($u, $a, $action);
            if (!$dec->allowed) {
                return Shell::deny($r, $dec->reason);
            }
            if ($s->rowVersion !== $p->rowVersion) {
                return self::replyAsset($d, $u, $a, $p->assetId, self::staleToast());
            }
            $o = PublicationRules::plan($p, $action, $s->scheduledAt, $s->liveUrl, $s->reason);
            if (!$o->ok() || $o->to === null) {
                return self::replyAsset($d, $u, $a, $p->assetId, Toast::error($o->errors->first()));
            }
            $w = $d->publications->applyMove($p, $s->rowVersion, $o, $u->id, $d->clock->now());
            $msg = match ($action) {
                PublicationAction::Ready => $p->platform->label() . ' is Ready to schedule. The AM has been told.',
                PublicationAction::Schedule => $p->platform->label() . ' is Scheduled.',
                PublicationAction::GoLive => $p->platform->label() . ' is Live.',
                PublicationAction::Archive => $p->platform->label() . ' post archived.',
                PublicationAction::Reopen => $p->platform->label() . ' moved back to ' . strtolower($o->to->label()) . '.',
                PublicationAction::Recheck => $p->platform->label() . ' is back to checking. Social and the AM have been told.',
            };
            if ($w->jobMoved() && $w->jobTo !== null) {
                $msg .= ' The job is now ' . strtolower($w->jobTo->label()) . '.';
            }
            return self::replyAsset($d, $u, $a, $p->assetId, self::toastFor($w, $msg));
        });
    }

    // ---- shared -------------------------------------------------------------------------

    /**
     * Load the publication named in the path, its job access and the card's
     * signals; unknown or invisible publications answer as missing.
     * @param \Closure(User, Publication, JobAccess, PublicationSignals): Response $fn
     */
    private static function withPublication(Request $r, Deps $d, \Closure $fn): Response
    {
        $u = BriefHandlers::user($r);
        $pid = $r->pathValue('pid');
        $p = preg_match('/^[0-9a-f]{32}$/', $pid) === 1 ? $d->publications->get($pid) : null;
        $access = $p === null ? null : $d->jobs->access($p->jobId);
        if ($p === null || $access === null || !SocialPolicy::canViewJob($u, $access)->allowed) {
            return Response::events(Toast::error('This post no longer exists.'));
        }
        return $fn($u, $p, $access, PublicationSignals::fromSignals($r->signals(), $p->id));
    }

    private static function toastFor(SocialWrite $w, string $ok): Toast
    {
        if ($w->conflict) {
            return self::staleToast();
        }
        if (!$w->ok) {
            return Toast::error($w->error);
        }
        return Toast::ok($ok);
    }

    private static function staleToast(): Toast
    {
        return Toast::warn('Changed by someone else a moment ago. This shows the latest; make your change again.');
    }

    /** The asset section and the job header, re-read, plus the toast. */
    private static function replyAsset(Deps $d, User $u, JobAccess $a, string $assetId, Toast $t): Response
    {
        $fresh = $d->jobs->access($a->jobId) ?? $a;
        $asset = $d->publications->asset($a->jobId, $assetId);
        $vm = self::jobVm($d, $u, $fresh);
        $events = [];
        if ($vm !== null) {
            $events[] = PatchElements::html(partial_social_job_head($vm));
        }
        if ($asset !== null && $vm !== null) {
            $events[] = PatchElements::html(partial_social_asset($asset, $vm->perms));
        }
        $events[] = $t;
        return Response::events(...$events);
    }

    /** Every asset section and the header (after a job-level write). */
    private static function replyJob(Deps $d, User $u, string $jobId, Toast $t): Response
    {
        $access = $d->jobs->access($jobId);
        $vm = $access === null ? null : self::jobVm($d, $u, $access);
        $events = [];
        if ($vm !== null) {
            $events[] = PatchElements::html(partial_social_job_head($vm));
            foreach ($vm->assets as $asset) {
                $events[] = PatchElements::html(partial_social_asset($asset, $vm->perms));
            }
        }
        $events[] = $t;
        return Response::events(...$events);
    }

    public static function perms(User $u, JobAccess $a): SocialPermsVM
    {
        return new SocialPermsVM(
            SocialPolicy::canEditChecklist($u, $a)->allowed, SocialPolicy::canSetReadyToSchedule($u, $a)->allowed,
            SocialPolicy::canSetScheduled($u, $a)->allowed, SocialPolicy::canSetLive($u, $a)->allowed,
            SocialPolicy::canEditLiveLink($u, $a)->allowed, SocialPolicy::canSetPromoted($u, $a)->allowed,
            SocialPolicy::canArchive($u, $a)->allowed, SocialPolicy::canReopen($u, $a)->allowed,
            SocialPolicy::canEditTestResult($u, $a)->allowed, SocialPolicy::canSetChecking($u, $a)->allowed,
        );
    }

    private static function jobVm(Deps $d, User $u, JobAccess $a): ?SocialJobVM
    {
        $s = BriefState::load($d, $a->jobId);
        if ($s === null) {
            return null;
        }
        $assets = $d->publications->queue($a->jobId);
        $perms = self::perms($u, $s->access);
        $openChecks = false;
        foreach ($assets as $as) {
            foreach ($as->publications as $p) {
                $openChecks = $openChecks || $p->status === PublicationStatus::Checking;
            }
            $openChecks = $openChecks || $as->publications === [];
        }
        $doc = null;
        $versionUrl = '';
        $versionLabel = '';
        if ($s->lastSent !== null) {
            $versionLabel = $s->brief->version->label();
            $sentLine = $s->latest !== null ? 'Sent ' . fmt_when($s->latest->createdAt) . ($s->latest->createdByName !== '' ? ' by ' . $s->latest->createdByName : '') : '';
            // Read only, and never budget or hours on a Social page, whoever looks.
            $doc = new BriefDocVM($s->job->id, $s->job->jobNumber, $versionLabel, $sentLine, '', $s->lastSent, false, false, false);
            if ($s->latest !== null) {
                $versionUrl = url('/jobs/' . rawurlencode($s->job->id) . '/brief/versions/' . rawurlencode($s->latest->version->format()));
            }
        }
        $note = '';
        if (!PublicationRules::isSocialWindow($s->job->stage)) {
            $note = 'The job is ' . strtolower($s->job->stage->label()) . '; its posts are shown read only.';
        } elseif (!$perms->any()) {
            $note = $u->role === Role::Social
                ? 'You can see this job in the queue. Ask the AM or Traffic to put you in its Social slot to work on it.'
                : 'Read only: Social, the COO and the ECD work on the posts.';
        }
        $am = $s->team->holder(Role::AM);
        $social = $s->team->holder(Role::Social);
        $title = $s->lastSent !== null ? $s->lastSent->title : $s->job->title;
        return new SocialJobVM(
            $s->job->id, $s->job->jobNumber, $title !== '' ? $title : '(untitled)', $s->brandName(), $s->campaignName(), $s->job->stage,
            $am !== null ? $am->userName : '', $social !== null ? $social->userName : '',
            $s->lastSent !== null ? $s->lastSent->dueDate : $s->brief->dueDate, $s->lastSent !== null ? $s->lastSent->lastGoLive : $s->brief->lastGoLive,
            $versionLabel, $versionUrl, $doc, $perms, $assets,
            $perms->ready && $openChecks && $assets !== [] && PublicationRules::isSocialWindow($s->job->stage), $note,
        );
    }

    /** The queue for this user: whole for Social, COO, ECD; own jobs for AM, PM, Producer. */
    private static function queueVm(Deps $d, User $u, string $tab, string $brand, string $platform, bool $mine): SocialQueueVM
    {
        $whole = SocialPolicy::seesWholeQueue($u);
        $offerMine = $u->role === Role::Social || $u->role === Role::COO || $u->role === Role::ECD;
        $brandOk = $brand !== '' && $d->brands->get($brand) !== null ? $brand : '';
        $plat = Platform::tryFrom($platform);
        $rows = $d->publications->queue(null, $brandOk !== '' ? $brandOk : null, $whole ? null : $u->id, $mine && $offerMine ? $u->id : null);
        $want = SocialTab::tryFrom($tab) ?? SocialTab::Check;
        $counts = [];
        foreach (SocialTab::cases() as $t) {
            $counts[$t->value] = 0;
        }
        $shown = [];
        foreach ($rows as $a) {
            $t = SocialTab::forAsset($a->statuses(), $a->jobStage);
            if ($t === null || ($plat !== null && !self::hasPlatform($a, $plat, $t))) {
                continue;
            }
            $counts[$t->value]++;
            if ($t === $want) {
                $shown[] = $a;
            }
        }
        if ($want === SocialTab::Scheduled) {
            usort($shown, static fn (SocialAsset $x, SocialAsset $y): int => strcmp(self::firstScheduled($x), self::firstScheduled($y)) ?: strcmp($x->jobNumber, $y->jobNumber));
        } elseif ($want === SocialTab::Check || $want === SocialTab::Ready) {
            usort($shown, static fn (SocialAsset $x, SocialAsset $y): int => strcmp($x->jobDue ?? '9999', $y->jobDue ?? '9999') ?: strcmp($x->jobNumber, $y->jobNumber));
        }
        $brands = [new SelectOption('', 'All brands')];
        foreach ($d->brands->list() as $b) {
            $brands[] = new SelectOption($b->id, $b->name);
        }
        $platforms = [new SelectOption('', 'All platforms')];
        foreach (Platform::cases() as $p) {
            $platforms[] = new SelectOption($p->value, $p->label());
        }
        return new SocialQueueVM($want, $brandOk, $plat !== null ? $plat->value : '', $mine && $offerMine, $offerMine, $whole, $counts, $shown, $brands, $platforms);
    }

    private static function hasPlatform(SocialAsset $a, Platform $p, SocialTab $t): bool
    {
        foreach ($a->publications as $pub) {
            if ($pub->platform === $p && ($t === SocialTab::Archived || $pub->status !== PublicationStatus::Archived)) {
                return true;
            }
        }
        // An asset still to check with no platform yet matches when its channel names the platform.
        return $t === SocialTab::Check && $a->publications === [] && in_array($p, Platform::allFromChannel($a->channel), true);
    }

    private static function firstScheduled(SocialAsset $a): string
    {
        $min = '9999';
        foreach ($a->publications as $p) {
            if ($p->status === PublicationStatus::Scheduled && $p->scheduledAt !== null && $p->scheduledAt < $min) {
                $min = $p->scheduledAt;
            }
        }
        return $min;
    }

    // ---- My day -------------------------------------------------------------------------

    /**
     * The Social section of /today, or null when it does not apply (not a Social
     * user and no Social slot). Social users see every job awaiting checks;
     * anyone else only the jobs where they hold the Social slot.
     */
    public static function today(Deps $d, User $u): ?SocialDayVM
    {
        $isSocial = $u->role === Role::Social;
        if (!$isSocial && !$d->publications->holdsSocialSlot($u->id)) {
            return null;
        }
        $nowSast = $d->clock->now()->setTimezone(new DateTimeZone('Africa/Johannesburg'));
        $today = Dates::today($d->clock->now());
        $rows = $d->publications->queue(null, null, null, $isSocial ? null : $u->id);
        $checkJobs = [];
        $scheduled = [];
        $needLink = [];
        foreach ($rows as $a) {
            $href = url('/social/jobs/' . rawurlencode($a->jobId));
            if (SocialTab::forAsset($a->statuses(), $a->jobStage) === SocialTab::Check) {
                if (!isset($checkJobs[$a->jobId])) {
                    $checkJobs[$a->jobId] = ['n' => 0, 'a' => $a];
                }
                $checkJobs[$a->jobId]['n']++;
            }
            foreach ($a->publications as $p) {
                if ($p->status === PublicationStatus::Scheduled && PublicationRules::isScheduledOn($p->scheduledAt, $today)) {
                    $scheduled[] = new SocialDayItemVM($a->jobNumber, $a->assetName, $p->platform->label(), substr((string) $p->scheduledAt, 11, 5), $href);
                }
                if (PublicationRules::isOverdueForLink($p, $nowSast)) {
                    $needLink[] = new SocialDayItemVM($a->jobNumber, $a->assetName, $p->platform->label(),
                        $p->status === PublicationStatus::Live ? 'Live' : 'Due ' . fmt_scheduled($p->scheduledAt), $href);
                }
            }
        }
        $toCheck = [];
        foreach ($checkJobs as $c) {
            $a = $c['a'];
            $toCheck[] = new SocialDayItemVM($a->jobNumber, $a->jobTitle, $c['n'] === 1 ? '1 asset to check' : $c['n'] . ' assets to check',
                $a->jobDue !== null ? 'Due ' . fmt_date($a->jobDue) : '', url('/social/jobs/' . rawurlencode($a->jobId)));
        }
        return new SocialDayVM($toCheck, $scheduled, $needLink);
    }

    /** GET /today/social: the section, patched by id every 60 seconds. */
    public static function todaySection(Request $r, Deps $d): Response
    {
        $vm = self::today($d, BriefHandlers::user($r));
        return $vm === null ? Response::notFound() : Response::events(PatchElements::html(partial_today_social($vm)));
    }
}

<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\AssetStatus;
use App\Domain\Decision;
use App\Domain\JobReviewStatus;
use App\Domain\Links;
use App\Domain\MediaKind;
use App\Domain\PartState;
use App\Domain\Policy;
use App\Domain\PolicyRule;
use App\Domain\ReviewDayRules;
use App\Domain\ReviewPart;
use App\Domain\ReviewPolicy;
use App\Domain\ReviewRules;
use App\Domain\Role;
use App\Domain\Signals\JobReviewSignals;
use App\Domain\Signals\ReviewSignals;
use App\Domain\Signals\SubmissionSignals;
use App\Domain\Types\AssetReview;
use App\Domain\Types\JobAccess;
use App\Domain\Types\JobReview;
use App\Domain\Types\ReviewRecord;
use App\Domain\Types\ReviewWrite;
use App\Domain\Types\Team;
use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\ui\SelectOption;
use App\View\VM\PostPreviewVM;
use App\View\VM\ReviewCardVM;
use App\View\VM\ReviewDayVM;
use App\View\VM\ReviewHistoryVM;
use App\View\VM\ReviewJobBarVM;
use App\View\VM\ReviewPageVM;
use App\View\VM\WorkItemVM;
use App\View\VM\WorkPageVM;

/**
 * Internal reviews (owner spec 2026-10; docs/roles.md, the Reviews rows):
 *   GET  /jobs/{id}/work                     the posts this person makes, with hand-in forms
 *   POST /jobs/{id}/work/{aid}               save a hand-in (sub_<key>.*)
 *   POST /jobs/{id}/work/{aid}/request       save and Request review
 *   GET  /jobs/{id}/review                   every post as a social post card, job action bar
 *   POST /jobs/{id}/review/posts/{aid}       approve or reject parts (rv_<key>.{rv, part, decision, feedback})
 *   POST /jobs/{id}/review/{approve|request-ecd|ecd-approve|reassign|ready|send}  job steps (jr.{rv, to})
 *   GET  /today/reviews                      the Reviews section of My day
 * Every write checks ReviewPolicy and a row_version, then answers with the
 * re-rendered card or form, the job bar and a toast (outer patches by id, so
 * transport=html works). Refused or stale writes re-render from the database.
 */
final class ReviewHandlers
{
    // ---- review page --------------------------------------------------------------

    public static function page(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::access($d, $u, $r->pathValue('id'));
        if ($a === null) {
            return Response::notFound();
        }
        $dec = ReviewPolicy::canView($u, $a);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $vm = self::pageVm($d, $u, $a);
        if ($vm === null) {
            return Response::notFound();
        }
        return Shell::page($r, $d, 'Review · ' . $vm->jobNumber, 'jobs', page_review($vm));
    }

    /** POST /jobs/{id}/review/posts/{aid}: a decision on the copy, the media or both. */
    public static function decide(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::access($d, $u, $r->pathValue('id'));
        $x = $a === null ? null : self::post($d, $a->jobId, $r->pathValue('aid'));
        if ($a === null || $x === null || !ReviewPolicy::canView($u, $a)->allowed) {
            return Response::events(Toast::error('This post no longer exists.'));
        }
        $jr = $d->reviews->jobReview($a->jobId);
        $other = $d->reviews->otherReviewerExists($u->id);
        $s = ReviewSignals::fromSignals($r->signals(), $x->assetId);
        $dec = ReviewPolicy::canReview($u, $a, $jr, null, null, $other);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        if ($s->decision === null) {
            return self::replyReview($d, $u, $a, Toast::error('Choose Approve, Reject with feedback or Reject.'), $x->assetId);
        }
        if ($s->rowVersion !== $x->rowVersion) {
            return self::replyReview($d, $u, $a, self::staleToast(), $x->assetId);
        }
        $parts = ReviewRules::partsFor($s->part, $x);
        foreach ($parts as $p) {
            $pd = ReviewPolicy::canReview($u, $a, $jr, $x, $p, $other);
            if (!$pd->allowed) {
                return self::replyReview($d, $u, $a, Toast::error($pd->reason), $x->assetId);
            }
        }
        $problems = ReviewRules::reviewProblems($x, $parts, $s->decision, $s->feedback);
        if (!$problems->isEmpty()) {
            return Response::events(Toast::error($problems->first()));
        }
        $w = $d->reviews->review($x, $parts, $s->decision, $s->feedback, $u->id, $d->clock->now());
        $what = [];
        foreach ($parts as $p) {
            $what[] = strtolower($p->label());
        }
        $msg = $s->decision->isRejection()
            ? ucfirst(implode(' and ', $what)) . ' sent back to the maker.' . ($w->clearedApprovals ? ' The job approvals were cleared.' : '')
            : ucfirst(implode(' and ', $what)) . ' approved.';
        return self::replyReview($d, $u, $a, self::toastFor($w, $msg . self::movedText($w)), $x->assetId);
    }

    public static function approveJob(Request $r, Deps $d): Response
    {
        return self::jobStep($r, $d,
            static fn (User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st): Decision => ReviewPolicy::canApproveJob($u, $a, $jr, $st),
            static fn (Deps $d, JobAccess $a, int $rv, User $u, JobReviewSignals $s): ReviewWrite => $d->reviews->approveJobCd($a->jobId, $rv, $u->id, $d->clock->now()),
            'Job approved. The ECD has been told.');
    }

    public static function requestEcd(Request $r, Deps $d): Response
    {
        return self::jobStep($r, $d,
            static fn (User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st): Decision => ReviewPolicy::canRequestEcdReview($u, $a, $jr),
            static fn (Deps $d, JobAccess $a, int $rv, User $u, JobReviewSignals $s): ReviewWrite => $d->reviews->requestEcdReview($a->jobId, $rv, $u->id, $d->clock->now()),
            'The ECD has been asked to review the job.');
    }

    public static function ecdApprove(Request $r, Deps $d): Response
    {
        return self::jobStep($r, $d,
            static fn (User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st): Decision => ReviewPolicy::canApproveAsEcd($u, $a, $jr, $st),
            static fn (Deps $d, JobAccess $a, int $rv, User $u, JobReviewSignals $s): ReviewWrite => $d->reviews->approveJobEcd($a->jobId, $rv, $u->id, $d->clock->now()),
            'ECD approval recorded. The AM and the CD have been told.');
    }

    public static function reassign(Request $r, Deps $d): Response
    {
        return self::jobStep($r, $d,
            static fn (User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st): Decision => ReviewPolicy::canReassign($u, $a, $jr),
            static function (Deps $d, JobAccess $a, int $rv, User $u, JobReviewSignals $s): ReviewWrite {
                $target = $s->to === '' ? null : $d->users->findById($s->to);
                if ($target === null) {
                    return ReviewWrite::refused('Choose who reviews the job next.');
                }
                $why = ReviewPolicy::reassignTargetProblem($target, $a, $d->reviews->jobReview($a->jobId), $u->id);
                if ($why !== '') {
                    return ReviewWrite::refused($why);
                }
                return $d->reviews->reassign($a->jobId, $rv, $target, $u->id, $d->clock->now());
            },
            'Review reassigned.');
    }

    public static function markReady(Request $r, Deps $d): Response
    {
        return self::jobStep($r, $d,
            static fn (User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st): Decision => ReviewPolicy::canMarkReady($u, $a, $jr, $st),
            static fn (Deps $d, JobAccess $a, int $rv, User $u, JobReviewSignals $s): ReviewWrite => $d->reviews->markReady($a->jobId, $rv, $u->id, $d->clock->now()),
            'Ready for client review. Notifications sent.');
    }

    public static function sendToClient(Request $r, Deps $d): Response
    {
        return self::jobStep($r, $d,
            static fn (User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st): Decision => ReviewPolicy::canSendToClient($u, $a, $jr, $st),
            static fn (Deps $d, JobAccess $a, int $rv, User $u, JobReviewSignals $s): ReviewWrite => $d->reviews->sendToClient($a->jobId, $rv, $u->id, $d->clock->now()),
            'Recorded as sent to the client. Notifications sent.');
    }

    // ---- hand-in page -------------------------------------------------------------

    public static function work(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::access($d, $u, $r->pathValue('id'));
        if ($a === null) {
            return Response::notFound();
        }
        $dec = ReviewPolicy::canView($u, $a);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $vm = self::workVm($d, $u, $a);
        if ($vm === null) {
            return Response::notFound();
        }
        return Shell::page($r, $d, 'Hand in · ' . $vm->jobNumber, 'jobs', page_work($vm));
    }

    public static function save(Request $r, Deps $d): Response
    {
        return self::handIn($r, $d, false);
    }

    public static function requestReview(Request $r, Deps $d): Response
    {
        return self::handIn($r, $d, true);
    }

    // ---- My day --------------------------------------------------------------------

    /** The Reviews section of /today, or null when no list applies to this person. */
    public static function today(Deps $d, User $u): ?ReviewDayVM
    {
        $day = ReviewDayRules::build($u, $d->reviews->dayStates(), $d->reviews->ecdIds(), $d->settings->reviewRoundLimit());
        return $day->applies() ? new ReviewDayVM($day) : null;
    }

    /** Items needing this person (the nav badge). */
    public static function attention(Deps $d, User $u): int
    {
        return ReviewDayRules::build($u, $d->reviews->dayStates(), $d->reviews->ecdIds(), $d->settings->reviewRoundLimit())->count();
    }

    /** GET /today/reviews: the section, patched by id every 60 seconds. */
    public static function todaySection(Request $r, Deps $d): Response
    {
        $vm = self::today($d, BriefHandlers::user($r));
        return $vm === null ? Response::notFound() : Response::events(PatchElements::html(partial_today_reviews($vm)));
    }

    // ---- shared -------------------------------------------------------------------

    /** The job when it exists and this person may see it at all, else null (answered as missing). */
    private static function access(Deps $d, User $u, string $jobId): ?JobAccess
    {
        $a = $d->jobs->access($jobId);
        return $a === null || !Policy::canViewJob($u, $a)->allowed ? null : $a;
    }

    /** A non-cancelled post of this job, or null. */
    private static function post(Deps $d, string $jobId, string $assetId): ?AssetReview
    {
        if (preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $assetId) !== 1) {
            return null;
        }
        $x = $d->reviews->asset($jobId, $assetId);
        return $x === null || AssetStatus::isCancelled($x->assetStatus) ? null : $x;
    }

    private static function handIn(Request $r, Deps $d, bool $request): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::access($d, $u, $r->pathValue('id'));
        $x = $a === null ? null : self::post($d, $a->jobId, $r->pathValue('aid'));
        if ($a === null || $x === null || !ReviewPolicy::canView($u, $a)->allowed) {
            return Response::events(Toast::error('This post no longer exists.'));
        }
        $team = $d->assignments->team($a->jobId);
        $dec = ReviewPolicy::canSubmit($u, $a, $x, $team);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $in = SubmissionSignals::fromSignals($r->signals(), $x->assetId);
        if ($in->rowVersion !== $x->rowVersion) {
            return self::replyWork($d, $u, $a, $x->assetId, self::staleToast());
        }
        $plan = ReviewRules::planSubmission($x, $in, ReviewPolicy::submitParts($u, $a, $x, $team), $request);
        if (!$plan->ok()) {
            // The form keeps what was typed; only the reason comes back.
            return Response::events(Toast::error($plan->errors->first()));
        }
        $w = $d->reviews->submit($x, $plan, $u->id, $d->settings->reviewRoundLimit(), $d->clock->now());
        $msg = $request ? 'Review requested. The reviewer has been told.' : 'Saved (round ' . $plan->round . ').';
        if ($w->roundWarned) {
            $msg .= ' This post passed the review round limit, so the AM has been told.';
        }
        return self::replyWork($d, $u, $a, $x->assetId, self::toastFor($w, $msg . self::movedText($w)));
    }

    /**
     * Load the access, the job review, the posts and the job's state for a
     * job-level step, check it, check jr.rv, write, and answer with the bar,
     * the summary and every card.
     * @param \Closure(User, JobAccess, JobReview, JobReviewStatus): Decision $check
     * @param \Closure(Deps, JobAccess, int, User, JobReviewSignals): ReviewWrite $write
     */
    private static function jobStep(Request $r, Deps $d, \Closure $check, \Closure $write, string $ok): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::access($d, $u, $r->pathValue('id'));
        if ($a === null || !ReviewPolicy::canView($u, $a)->allowed) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $jr = $d->reviews->jobReview($a->jobId);
        $st = ReviewRules::aggregate($d->reviews->assets($a->jobId));
        $dec = $check($u, $a, $jr, $st);
        if (!$dec->allowed) {
            return self::replyReview($d, $u, $a, Toast::error($dec->reason), null);
        }
        $s = JobReviewSignals::fromSignals($r->signals());
        if ($s->rowVersion !== $jr->rowVersion) {
            return self::replyReview($d, $u, $a, self::staleToast(), null);
        }
        $w = $write($d, $a, $jr->rowVersion, $u, $s);
        return self::replyReview($d, $u, $a, self::toastFor($w, $ok . self::movedText($w)), null);
    }

    private static function toastFor(ReviewWrite $w, string $ok): Toast
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

    private static function movedText(ReviewWrite $w): string
    {
        return $w->ok && $w->jobMoved() && $w->jobTo !== null ? ' The job is now ' . strtolower($w->jobTo->label()) . '.' : '';
    }

    /** The job bar, the summary and one card ($assetId) or every card, re-read, plus the toast. */
    private static function replyReview(Deps $d, User $u, JobAccess $a, Toast $t, ?string $assetId): Response
    {
        $fresh = $d->jobs->access($a->jobId) ?? $a;
        $vm = self::pageVm($d, $u, $fresh);
        $events = [];
        if ($vm !== null) {
            $events[] = PatchElements::html(partial_review_summary($vm));
            $events[] = PatchElements::html(partial_review_jobbar($vm->bar));
            foreach ($vm->cards as $c) {
                if ($assetId === null || $c->key === ReviewSignals::key($assetId)) {
                    $events[] = PatchElements::html(partial_review_card($c));
                }
            }
        }
        $events[] = $t;
        return Response::events(...$events);
    }

    private static function replyWork(Deps $d, User $u, JobAccess $a, string $assetId, Toast $t): Response
    {
        $fresh = $d->jobs->access($a->jobId) ?? $a;
        $vm = self::workVm($d, $u, $fresh);
        $events = [];
        if ($vm !== null) {
            foreach ($vm->items as $it) {
                if ($it->key === ReviewSignals::key($assetId)) {
                    $events[] = PatchElements::html(partial_work_item($it));
                }
            }
        }
        $events[] = $t;
        return Response::events(...$events);
    }

    private static function pageVm(Deps $d, User $u, JobAccess $a): ?ReviewPageVM
    {
        $job = $d->jobs->get($a->jobId);
        if ($job === null) {
            return null;
        }
        $brief = $d->briefs->getByJob($a->jobId);
        $brand = $a->brandId !== null ? $d->brands->get($a->brandId) : null;
        $brandName = $brand !== null ? $brand->name : (string) $job->brandName;
        $logo = $brand !== null && Links::isHttpsUrl($brand->logoUrl) ? $brand->logoUrl : '';
        $team = $d->assignments->team($a->jobId);
        $jr = $d->reviews->jobReview($a->jobId);
        $history = $d->reviews->history($a->jobId);
        $other = $d->reviews->otherReviewerExists($u->id);
        $posts = [];
        foreach ($d->reviews->assets($a->jobId) as $x) {
            if (!AssetStatus::isCancelled($x->assetStatus)) {
                $posts[] = $x;
            }
        }
        $cards = [];
        $n = count($posts);
        foreach ($posts as $i => $x) {
            $cards[] = self::card($x, $i + 1, $n, $u, $a, $jr, $other, $team, $history[$x->assetId] ?? [], $brandName, $logo);
        }
        $st = ReviewRules::aggregate($posts);
        $mine = 0;
        foreach ($cards as $c) {
            $mine += $c->waitingForMe ? 1 : 0;
        }
        $id = rawurlencode($a->jobId);
        return new ReviewPageVM(
            $a->jobId, $job->jobNumber, $job->title !== '' ? $job->title : '(untitled)', $brandName, $a->stage,
            $brief !== null && $brief->isSent() ? $brief->version->label() : '', url('/jobs/' . $id . '/brief'),
            Policy::rule('submit_for_review', $u->role) !== PolicyRule::Deny ? url('/jobs/' . $id . '/work') : '',
            $cards, $mine > 0 ? 'mine' : 'all', self::bar($d, $u, $a, $jr, $st, ReviewRules::jobRound($posts)),
        );
    }

    /** @param list<ReviewRecord> $history */
    private static function card(AssetReview $x, int $i, int $n, User $u, JobAccess $a, JobReview $jr, bool $other, Team $team, array $history, string $brandName, string $logo): ReviewCardVM
    {
        $key = ReviewSignals::key($x->assetId);
        $choices = [];
        foreach (['both', 'copy', 'media'] as $choice) {
            if ($choice !== 'both' && !$x->needs(ReviewPart::from($choice))) {
                continue;
            }
            if ($choice === 'both' && count($x->requiredParts()) < 2) {
                continue;
            }
            $parts = ReviewRules::partsFor($choice, $x);
            $ok = $parts !== [];
            foreach ($parts as $p) {
                $ok = $ok && $x->state($p)->hasContent() && ReviewPolicy::canReview($u, $a, $jr, $x, $p, $other)->allowed;
            }
            if ($ok) {
                $choices[] = $choice;
            }
        }
        $base = ReviewPolicy::canReview($u, $a, $jr, $x, null, $other);
        $reason = '';
        $anyContent = false;
        foreach ($x->requiredParts() as $p) {
            $anyContent = $anyContent || $x->state($p)->hasContent();
        }
        if ($choices === []) {
            $reason = !$anyContent ? 'Nothing handed in yet.' : ($base->allowed ? 'You handed this in, so another CD or the ECD reviews it.' : $base->reason);
        }
        $default = in_array('both', $choices, true) ? 'both' : ($choices[0] ?? 'both');
        $handed = $x->submittedAt !== null ? 'Last handed in ' . fmt_when($x->submittedAt) . ($x->reviewRequestedAt !== null ? ' · review requested ' . fmt_when($x->reviewRequestedAt) : '') : 'Not handed in yet';
        return new ReviewCardVM(
            $key, $x->assetName, trim($x->lineLabel . ($x->channel !== '' ? ' · ' . $x->channel : ''), ' ·'), $i, $n, max(1, $x->round),
            self::preview($x, 'preview-' . $key, $brandName, $logo),
            $x->copyState, $x->needs(ReviewPart::Media) ? $x->mediaState : PartState::Missing, $x->needs(ReviewPart::Media),
            self::makerName($x, ReviewPart::Copy, $team), self::makerName($x, ReviewPart::Media, $team), $handed, $x->note,
            self::historyVms($history), $choices !== [], $reason, $choices, $default, $x->rowVersion,
            url('/jobs/' . rawurlencode($a->jobId) . '/review/posts/' . rawurlencode($x->assetId)),
            $choices !== [] && $x->waitingReview(), $x->anyRejected(), $x->hasSubmission(),
        );
    }

    public static function preview(AssetReview $x, string $domId, string $brandName, string $logo): PostPreviewVM
    {
        $kind = $x->mediaUrl === '' ? MediaKind::Link : MediaKind::fromUrl($x->mediaUrl);
        return new PostPreviewVM(
            $domId, $brandName, $logo, $x->mediaUrl, $kind, MediaKind::showsInline($x->mediaUrl, $kind), Links::isWebUrl($x->mediaUrl),
            $x->copyText, $x->hashtags, $x->linkUrl, Links::isHttpsUrl($x->linkUrl), $x->needs(ReviewPart::Media),
        );
    }

    /** Who made (or makes) a part: whoever handed it in, else the derived maker's name. */
    private static function makerName(AssetReview $x, ReviewPart $p, Team $team): string
    {
        if (!$x->needs($p)) {
            return '';
        }
        if ($x->makerName($p) !== '') {
            return $x->makerName($p);
        }
        $id = ReviewRules::derivedMaker($x, $p, $team);
        if ($id === null) {
            return '';
        }
        if ($id === $x->assigneeId) {
            return $x->assigneeName;
        }
        foreach ($team->assignments as $as) {
            if ($as->userId === $id) {
                return $as->userName;
            }
        }
        return '';
    }

    /** @param list<ReviewRecord> $history @return list<ReviewHistoryVM> */
    private static function historyVms(array $history): array
    {
        $out = [];
        foreach ($history as $h) {
            $out[] = new ReviewHistoryVM($h->part, $h->decision, $h->feedback, $h->reviewerName, fmt_when($h->reviewedAt), $h->round, $h->briefVersion);
        }
        return $out;
    }

    private static function bar(Deps $d, User $u, JobAccess $a, JobReview $jr, JobReviewStatus $st, int $round): ReviewJobBarVM
    {
        $facts = [];
        if ($jr->cdApproved()) {
            $facts[] = 'CD approved: ' . ($jr->cdApprovedByName !== '' ? $jr->cdApprovedByName : 'yes') . ', ' . fmt_when($jr->cdApprovedAt);
        } elseif ($jr->ecdRequestedAt !== null && !$jr->ecdApproved()) {
            $facts[] = 'ECD review requested ' . fmt_when($jr->ecdRequestedAt);
        }
        if ($jr->ecdApproved()) {
            $facts[] = 'ECD approved: ' . ($jr->ecdApprovedByName !== '' ? $jr->ecdApprovedByName : 'yes') . ', ' . fmt_when($jr->ecdApprovedAt);
        }
        if ($jr->assigneeId !== null) {
            $facts[] = 'Review handed to ' . ($jr->assigneeName !== '' ? $jr->assigneeName : 'someone') . ($jr->assigneeRole !== null ? ' (' . $jr->assigneeRole->value . ')' : '');
        }
        if ($jr->readyForClient()) {
            $facts[] = 'Ready for client review ' . fmt_when($jr->clientReadyAt);
        }
        if ($jr->sentToClient()) {
            $facts[] = 'Sent to the client ' . fmt_when($jr->sentToClientAt);
        }
        $canReassign = ReviewPolicy::canReassign($u, $a, $jr)->allowed;
        $targets = [];
        if ($canReassign) {
            foreach ($d->reviews->reassignCandidates($a->brandId) as $t) {
                if (ReviewPolicy::reassignTargetProblem($t, $a, $jr, $u->id) === '') {
                    $targets[] = new SelectOption($t->id, $t->name . ' (' . ($t->role === Role::Client ? 'client' : $t->role->value) . ')');
                }
            }
        }
        $canApprove = ReviewPolicy::canApproveJob($u, $a, $jr, $st)->allowed;
        $canEcd = ReviewPolicy::canApproveAsEcd($u, $a, $jr, $st)->allowed;
        $canReady = ReviewPolicy::canMarkReady($u, $a, $jr, $st)->allowed;
        $canSend = ReviewPolicy::canSendToClient($u, $a, $jr, $st)->allowed;
        // "Request ECD review" is for before the CD approves (approving notifies the ECD anyway).
        $canRequest = ReviewPolicy::canRequestEcdReview($u, $a, $jr)->allowed && !$jr->cdApproved() && $jr->ecdRequestedAt === null && $u->role !== Role::ECD;
        $hint = '';
        if ($st === JobReviewStatus::AllApproved && !$jr->cdApproved()) {
            $hint = 'Every post is approved. The CD approves the job next.';
        } elseif ($jr->cdApproved() && !$jr->ecdApproved()) {
            $hint = 'Waiting for the ECD to approve the job.';
        } elseif ($jr->internallyApproved() && !$jr->readyForClient()) {
            $hint = 'Approved by the CD and the ECD. Mark it ready for client review, then send it.';
        } elseif ($jr->readyForClient() && !$jr->sentToClient()) {
            $hint = 'Ready for client review. Send it to the client (by hand for now) and record it here.';
        }
        return new ReviewJobBarVM($a->jobId, $jr->rowVersion, $st->label(), $round, $facts, $canApprove, $canRequest, $canEcd,
            $canReassign && $targets !== [], $targets, $canReady, $canSend, $hint);
    }

    private static function workVm(Deps $d, User $u, JobAccess $a): ?WorkPageVM
    {
        $job = $d->jobs->get($a->jobId);
        if ($job === null) {
            return null;
        }
        $brief = $d->briefs->getByJob($a->jobId);
        $brand = $a->brandId !== null ? $d->brands->get($a->brandId) : null;
        $brandName = $brand !== null ? $brand->name : (string) $job->brandName;
        $logo = $brand !== null && Links::isHttpsUrl($brand->logoUrl) ? $brand->logoUrl : '';
        $team = $d->assignments->team($a->jobId);
        $history = $d->reviews->history($a->jobId);
        $items = [];
        $others = 0;
        $id = rawurlencode($a->jobId);
        foreach ($d->reviews->assets($a->jobId) as $x) {
            if (AssetStatus::isCancelled($x->assetStatus)) {
                continue;
            }
            $parts = ReviewPolicy::submitParts($u, $a, $x, $team);
            if ($parts === []) {
                $others++;
                continue;
            }
            $editCopy = in_array(ReviewPart::Copy, $parts, true) && $x->copyState !== PartState::Approved;
            $editMedia = in_array(ReviewPart::Media, $parts, true) && $x->mediaState !== PartState::Approved;
            $readOnly = !$editCopy && !$editMedia ? 'Approved. Nothing to change unless the reviewer sends it back.' : '';
            $key = ReviewSignals::key($x->assetId);
            $aid = rawurlencode($x->assetId);
            $items[] = new WorkItemVM(
                $key, $x->assetName, trim($x->lineLabel . ($x->channel !== '' ? ' · ' . $x->channel : ''), ' ·'), max(1, $x->round),
                $x->copyState, $x->needs(ReviewPart::Media) ? $x->mediaState : PartState::Missing, $x->needs(ReviewPart::Media), $editCopy, $editMedia,
                $x->copyText, $x->mediaUrl, $x->hashtags, $x->linkUrl, $x->note, $x->rowVersion,
                url('/jobs/' . $id . '/work/' . $aid), url('/jobs/' . $id . '/work/' . $aid . '/request'),
                self::historyVms($history[$x->assetId] ?? []), $readOnly, self::preview($x, 'work-preview-' . $key, $brandName, $logo),
                $x->reviewRequestedAt !== null,
            );
        }
        return new WorkPageVM(
            $a->jobId, $job->jobNumber, $job->title !== '' ? $job->title : '(untitled)', $brandName, $a->stage,
            $brief !== null && $brief->isSent() ? $brief->version->label() : '', url('/jobs/' . $id . '/brief'), url('/jobs/' . $id . '/review'),
            $items, $others,
        );
    }
}

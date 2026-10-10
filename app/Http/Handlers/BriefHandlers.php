<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Policy;
use App\Domain\Signals\BriefSignals;
use App\Domain\Signals\NewBriefSignals;
use App\Domain\Signals\SendSignals;
use App\Domain\Types\BriefListItem;
use App\Domain\Types\User;
use App\Http\BriefState;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Redirect;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\ui\SelectOption;
use App\View\VM\BriefVersionsVM;
use App\View\VM\MyBriefsVM;
use App\View\VM\VersionRowVM;

/**
 * Brief pages and actions. Reads: GET /briefs, /jobs/{id}/brief, /versions,
 * /versions/{v}, /print. Writes (Datastar): POST /briefs, PATCH /jobs/{id}/brief,
 * GET+POST /jobs/{id}/brief/send and /update. Every Datastar answer is outer
 * patches by id plus toasts, or one Redirect, so transport=html works too.
 */
final class BriefHandlers
{
    public static function mine(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        // "My briefs" is the brief owners' page (Traffic and creatives open sent briefs from their jobs).
        $dec = Policy::canCreateBrief($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $owned = $d->jobs->listOwnedBy($u->id, $d->clock->now());
        $drafts = [];
        $unsent = [];
        $sent = [];
        foreach ($owned as $it) {
            if (!$it->sent) {
                $drafts[] = $it;
            } elseif ($it->hasUnsentChanges) {
                $unsent[] = $it;
            } else {
                $sent[] = $it;
            }
        }
        usort($sent, static fn (BriefListItem $a, BriefListItem $b): int => strcmp((string) $b->sentAt, (string) $a->sentAt));
        $canClaim = in_array($u->role->value, ['AM', 'PM', 'Producer', 'COO', 'ECD'], true);
        $options = [];
        foreach ($d->campaigns->listAll() as $c) {
            $options[] = new SelectOption($c->id, $c->name, false, $c->brandName);
        }
        $vm = new MyBriefsVM($drafts, $unsent, array_slice($sent, 0, 30), $canClaim ? $d->jobs->listWithoutAm() : [], $options,
            Policy::canCreateBrief($u)->allowed, $canClaim);
        return Shell::page($r, $d, 'My briefs', 'briefs', page_my_briefs($vm));
    }

    /** POST /briefs: draft job + brief from a campaign, then open the editor. */
    public static function create(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $dec = Policy::canCreateBrief($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $in = NewBriefSignals::fromSignals($r->signals());
        $errors = $in->validate();
        if (!$errors->isEmpty()) {
            return Response::events(Toast::error($errors->first()));
        }
        $campaign = $d->campaigns->get($in->campaignId);
        if ($campaign === null) {
            return Response::events(Toast::error('That campaign does not exist.'));
        }
        $jobId = $d->jobs->createDraft($campaign, $in->title, $u, $d->clock->now());
        return Response::navigate($r->isDatastar(), url('/jobs/' . rawurlencode($jobId) . '/brief'));
    }

    public static function editor(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = BriefState::load($d, $r->pathValue('id'));
        if ($s === null) {
            return Response::notFound();
        }
        $mode = self::viewMode($u, $s);
        if ($mode === 'none') {
            return Response::notFound();
        }
        return Shell::page($r, $d, $s->job->jobNumber . ' brief', 'briefs', page_brief_editor(BriefView::editor($s, $u, $d, $mode === 'claim')));
    }

    /** PATCH /jobs/{id}/brief: autosave of brief.* (only the keys sent). */
    public static function autosave(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = self::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        $in = BriefSignals::fromSignals($r->signals());
        $patch = $in->patch;
        $errors = $in->errors;
        if (!Policy::canViewBudget($u, $s->access)->allowed) {
            $patch = $patch->without('budget');
        }
        if ($patch->has('campaign_id') && $patch->campaignId !== $s->brief->campaignId) {
            $c = $d->campaigns->get((string) $patch->campaignId);
            if ($c === null || ($s->campaign !== null && $c->brandId !== $s->campaign->brandId)) {
                $patch = $patch->without('campaign_id');
                $errors = $errors->with('campaign_id', 'Pick a campaign of the same brand.');
            }
        }
        // Someone else saved since this page loaded: show their version instead of overwriting it.
        if ($in->rowVersion > 0 && $in->rowVersion !== $s->brief->rowVersion && $s->brief->updatedBy !== null && $s->brief->updatedBy !== $u->id) {
            return self::conflict($s, $u, $d);
        }
        $now = $d->clock->now();
        if (!$d->briefs->save($s->brief, $patch, $s->baseline, $u->id, $now)) {
            $fresh = BriefState::load($d, $s->job->id);
            return $fresh === null ? Response::events(Toast::error('This brief no longer exists.')) : self::conflict($fresh, $u, $d);
        }
        $fresh = self::reconcile($d, $s->job->id);
        $events = [PatchElements::html(partial_brief_rail(BriefView::rail($fresh, $u, $d)))];
        if ($patch->has('title')) {
            $events[] = PatchElements::html(partial_brief_heading($fresh->brief->title));
        }
        if (!$errors->isEmpty()) {
            $events[] = Toast::error($errors->first());
        }
        return Response::events(...$events);
    }

    /** GET /jobs/{id}/brief/send: the send dialog body (what is missing, what will happen). */
    public static function sendDialog(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = self::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        return Response::events(PatchElements::html(partial_send_dialog_body(BriefView::sendDialog($s, $u, $d->clock->now()))));
    }

    /** POST /jobs/{id}/brief/send: first send, v1.0.0. */
    public static function send(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = self::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        $dec = Policy::canSendBrief($u, $s->access);
        if (!$dec->allowed) {
            return Response::events(Toast::error($dec->reason));
        }
        $now = $d->clock->now();
        $plan = $s->sendPlan(null, '', $now);
        if ($plan->plan === null) {
            return Response::events(Toast::error($plan->errors->first()));
        }
        $res = $d->briefs->send($plan->plan->withoutActor($u->id), $s->baseline, $s->job->campaignId, $u->id, $now);
        if (!$res['ok']) {
            return Response::events(Toast::warn('The job changed while you were sending. Reload the page and try again.'));
        }
        return Response::events(new Redirect(url('/jobs/' . rawurlencode($s->job->id) . '/brief')));
    }

    /** GET /jobs/{id}/brief/update: diff, suggested bump, change note. */
    public static function updateDialog(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = self::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        return Response::events(PatchElements::html(partial_send_dialog_body(BriefView::updateDialog($s, $u, $d->clock->now()))));
    }

    /** POST /jobs/{id}/brief/update: send.bump + send.note. Also re-sends a recalled brief. */
    public static function sendUpdate(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = self::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        $dec = Policy::canSendBriefUpdate($u, $s->access);
        if (!$dec->allowed) {
            return Response::events(Toast::error($dec->reason));
        }
        $in = SendSignals::fromSignals($r->signals());
        $now = $d->clock->now();
        $plan = $s->sendPlan($in->bump, $in->note, $now);
        if ($plan->plan === null) {
            return Response::events(Toast::error($plan->errors->first()));
        }
        $res = $d->briefs->send($plan->plan->withoutActor($u->id), $s->baseline, $s->job->campaignId, $u->id, $now);
        if (!$res['ok']) {
            return Response::events(Toast::warn('The job changed while you were sending. Reload the page and try again.'));
        }
        return Response::events(new Redirect(url('/jobs/' . rawurlencode($s->job->id) . '/brief')));
    }

    public static function versions(Request $r, Deps $d): Response
    {
        return self::versionsPage($r, $d, '');
    }

    public static function version(Request $r, Deps $d): Response
    {
        return self::versionsPage($r, $d, $r->pathValue('v'));
    }

    /** GET /jobs/{id}/brief/print[?v=1.2.0]: the latest sent version (or the chosen one); owners of a draft print the working copy. */
    public static function print(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $s = BriefState::load($d, $r->pathValue('id'));
        if ($s === null || self::viewMode($u, $s) === 'none') {
            return Response::notFound();
        }
        $want = $r->query('v');
        $doc = null;
        if ($want !== '') {
            $rec = $d->briefs->version($s->brief->id, $want);
            if ($rec === null || !Policy::canViewBriefSent($u, $s->access)->allowed) {
                return Response::notFound();
            }
            $doc = BriefView::doc($s, $rec->snapshot, $rec->version->label(), $rec, $u, false);
        } elseif ($s->lastSent !== null && !(Policy::canViewBriefDraft($u, $s->access)->allowed && $s->brief->hasUnsentChanges)) {
            $doc = BriefView::doc($s, $s->lastSent, $s->brief->version->label(), $s->latest, $u, false);
        } else {
            $doc = BriefView::doc($s, $s->current(), $s->brief->isSent() ? 'working copy' : 'draft', null, $u, true);
        }
        return Response::page(page_brief_print($doc, url('/jobs/' . rawurlencode($s->job->id) . '/brief')));
    }

    // ---- helpers ---------------------------------------------------------------

    /** Auth guarantees a user on every non-public route. */
    public static function user(Request $r): User
    {
        $u = $r->user();
        if ($u === null) {
            throw new \LogicException('route needs a signed-in user');
        }
        return $u;
    }

    /**
     * edit: may edit; read: sees the sent version; claim: unowned job a manager may take
     * (draft shown read-only); none: 404 (drafts of others must not leak).
     */
    public static function viewMode(User $u, BriefState $s): string
    {
        $a = $s->access;
        if (Policy::canEditBrief($u, $a)->allowed) {
            return 'edit';
        }
        if ($s->brief->isSent() && Policy::canViewBriefSent($u, $a)->allowed) {
            return 'read';
        }
        if (Policy::canViewBriefDraft($u, $a)->allowed) {
            return 'read';
        }
        if (Policy::canClaimAm($u, $a)->allowed && $a->creatorId === null) {
            return 'claim';
        }
        return 'none';
    }

    /** The brief for a Datastar write, or the error toast to answer with. */
    public static function writable(Request $r, Deps $d): BriefState|Response
    {
        $u = self::user($r);
        $s = BriefState::load($d, $r->pathValue('id'));
        if ($s === null) {
            return Response::events(Toast::error('This brief no longer exists.'));
        }
        $dec = Policy::canEditBrief($u, $s->access);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        return $s;
    }

    /** Reload after a write and correct the unsent flag (an edit can undo the last difference). */
    public static function reconcile(Deps $d, string $jobId): BriefState
    {
        $s = BriefState::load($d, $jobId);
        if ($s === null) {
            throw new \RuntimeException('brief vanished during a write');
        }
        $diff = $s->diff();
        if ($diff !== null) {
            $unsent = !$diff->contentIsEmpty();
            if ($unsent !== $s->brief->hasUnsentChanges) {
                $d->briefs->setUnsent($s->brief->id, $unsent);
                $s = BriefState::load($d, $jobId) ?? $s;
            }
        }
        return $s;
    }

    /** Re-render the form and rail from the database with a warning. */
    private static function conflict(BriefState $s, User $u, Deps $d): Response
    {
        $vm = BriefView::editor($s, $u, $d);
        return Response::events(
            PatchElements::html(partial_brief_form($vm, url('/jobs/' . rawurlencode($s->job->id) . '/brief'))),
            PatchElements::html(partial_brief_rail($vm->rail)),
            Toast::warn('Someone else changed this brief. You are now seeing their latest version.'),
        );
    }

    private static function versionsPage(Request $r, Deps $d, string $want): Response
    {
        $u = self::user($r);
        $s = BriefState::load($d, $r->pathValue('id'));
        if ($s === null || self::viewMode($u, $s) === 'none') {
            return Response::notFound();
        }
        $records = $s->brief->isSent() ? $d->briefs->versions($s->brief->id) : [];
        $base = url('/jobs/' . rawurlencode($s->job->id) . '/brief/versions');
        $selected = null;
        $prev = null;
        foreach ($records as $i => $rec) {
            if ($want === '' ? $i === count($records) - 1 : $rec->version->format() === $want) {
                $selected = $rec;
                $prev = $i > 0 ? $records[$i - 1] : null;
            }
        }
        if ($want !== '' && $selected === null) {
            return Response::notFound();
        }
        $rows = [];
        foreach (array_reverse($records) as $rec) {
            $rows[] = new VersionRowVM($rec->version->label(), $rec->bumpLevel, $rec->note, $rec->createdByName, fmt_when($rec->createdAt),
                $base . '/' . rawurlencode($rec->version->format()), $selected !== null && $rec->id === $selected->id);
        }
        $doc = null;
        $diff = null;
        $against = '';
        if ($selected !== null) {
            $doc = BriefView::doc($s, $selected->snapshot, $selected->version->label(), $selected, $u, false);
            $diff = $selected->diff;
            $against = $prev !== null ? $prev->version->label() : '';
        } elseif ($s->lastSent !== null) {
            // backfilled sent brief without version rows yet
            $doc = BriefView::doc($s, $s->lastSent, $s->brief->version->label(), null, $u, false);
        }
        $vm = new BriefVersionsVM($s->job->id, $s->job->jobNumber, $s->brief->title, $rows, $doc, $diff, $against, Policy::canViewBudget($u, $s->access)->allowed);
        return Shell::page($r, $d, $s->job->jobNumber . ' versions', 'briefs', page_brief_versions($vm));
    }
}

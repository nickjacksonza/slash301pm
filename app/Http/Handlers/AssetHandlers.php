<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\AssetOverride;
use App\Domain\AssetStatus;
use App\Domain\Policy;
use App\Domain\PublicationStatus;
use App\Domain\Signals\OverrideSignals;
use App\Domain\Types\JobAccess;
use App\Domain\Types\JobAsset;
use App\Domain\Types\Publication;
use App\Domain\Types\SocialWrite;
use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\Store\Ids;
use App\View\ui\SelectOption;
use App\View\VM\AssetGroupVM;
use App\View\VM\AssetRowVM;
use App\View\VM\JobAssetsVM;
use App\View\VM\OverridesVM;
use App\View\VM\PostRowVM;

/**
 * Asset status overrides (owner decision 2026-10; Traffic, COO, ECD):
 *   GET /jobs/{id}/assets                 every deliverable and asset of a sent job
 *   POST /jobs/{id}/assets/override       ova.{asset_id, from, to, reason}: a legacy asset status
 *   POST /jobs/{id}/publications/override ovp.{pub_id, rv, to, reason}: a Social post status
 *   GET /admin/overrides                  the COO's report (?from=&to= SAST dates)
 * Every override needs a reason and writes asset_status_overridden in the same
 * transaction. Answers re-render #job-assets and add a toast (one html patch set).
 */
final class AssetHandlers
{
    public static function page(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $a = $d->jobs->access($r->pathValue('id'));
        if ($a === null || !Policy::canViewJob($u, $a)->allowed) {
            return Response::notFound();
        }
        $dec = Policy::canViewJobAssets($u, $a);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $vm = self::vm($d, $u, $a, '');
        if ($vm === null) {
            return Response::notFound();
        }
        return Shell::page($r, $d, 'Assets · ' . $vm->jobNumber, 'jobs', page_job_assets($vm));
    }

    public static function overrideAsset(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::writable($r, $d, $u);
        if (!$a instanceof JobAccess) {
            return $a;
        }
        $in = OverrideSignals::asset($r->signals());
        $asset = null;
        foreach ($d->assetOverrides->jobAssets($a->jobId) as $x) {
            if ($x->id === $in->targetId) {
                $asset = $x;
            }
        }
        if ($asset === null) {
            return self::reply($d, $u, $a, Toast::error('This asset is not on this job.'), false);
        }
        $why = AssetOverride::reasonProblem($in->reason);
        if ($why === '') {
            $why = AssetOverride::assetProblem($in->from, $in->to);
        }
        if ($why !== '') {
            return self::reply($d, $u, $a, Toast::error($why), false);
        }
        $w = $d->assetOverrides->overrideAsset($a->jobId, $asset->id, $in->from, $in->to, $in->reason, $u->id, $d->clock->now());
        return self::reply($d, $u, $a, self::toast($w, $asset->name . ' is now ' . $in->to . '. Logged as an override.'), $w->ok);
    }

    public static function overridePost(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $a = self::writable($r, $d, $u);
        if (!$a instanceof JobAccess) {
            return $a;
        }
        $in = OverrideSignals::post($r->signals());
        $p = preg_match('/^[0-9a-f]{32}$/', $in->targetId) === 1 ? $d->publications->get($in->targetId) : null;
        if ($p === null || $p->jobId !== $a->jobId) {
            return self::reply($d, $u, $a, Toast::error('This post is not on this job.'), false);
        }
        $to = PublicationStatus::tryFrom($in->to);
        $why = AssetOverride::reasonProblem($in->reason);
        if ($why === '') {
            $why = AssetOverride::publicationProblem($p->status, $to);
        }
        if ($why !== '' || $to === null) {
            return self::reply($d, $u, $a, Toast::error($why), false);
        }
        if ($in->rowVersion !== $p->rowVersion) {
            return self::reply($d, $u, $a, self::toast(SocialWrite::stale(), ''), false);
        }
        $w = $d->publications->override($p, $in->rowVersion, $to, $in->reason, $u->id, $d->clock->now());
        $msg = $p->platform->label() . ' post is now ' . strtolower($to->label()) . '. Logged as an override.';
        if ($w->jobMoved() && $w->jobTo !== null) {
            $msg .= ' The job is now ' . strtolower($w->jobTo->label()) . '.';
        }
        return self::reply($d, $u, $a, self::toast($w, $msg), $w->ok);
    }

    public static function report(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $dec = Policy::canViewOverridesReport($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $range = AssetOverride::range($r->query('from'), $r->query('to'), $d->clock->now());
        $limit = 500;
        $rows = $d->assetOverrides->report($range, $limit);
        return Shell::page($r, $d, 'Overrides', 'admin-overrides', page_admin_overrides(new OverridesVM($range->fromDate, $range->toDate, $rows, count($rows) >= $limit)));
    }

    // ---- shared ------------------------------------------------------------------

    /** The job of a write, after the view and override checks; a Response when refused. */
    private static function writable(Request $r, Deps $d, User $u): JobAccess|Response
    {
        $a = $d->jobs->access($r->pathValue('id'));
        if ($a === null || !Policy::canViewJob($u, $a)->allowed) {
            return Response::events(Toast::error('This job no longer exists.'));
        }
        $dec = Policy::canOverrideAssetStatus($u, $a);
        return $dec->allowed ? $a : Shell::deny($r, $dec->reason);
    }

    private static function toast(SocialWrite $w, string $ok): Toast
    {
        if ($w->conflict) {
            return Toast::warn('Changed by someone else a moment ago. This shows the latest; override again if you still need to.');
        }
        return $w->ok ? Toast::ok($ok) : Toast::error($w->error);
    }

    private static function reply(Deps $d, User $u, JobAccess $a, Toast $t, bool $close): Response
    {
        $vm = self::vm($d, $u, $d->jobs->access($a->jobId) ?? $a, $close ? Ids::new() : '');
        return $vm === null ? Response::events($t) : Response::events(PatchElements::html(partial_job_assets($vm)), $t);
    }

    public static function vm(Deps $d, User $u, JobAccess $a, string $savedNonce): ?JobAssetsVM
    {
        $one = JobsView::one($d, $u, $a->jobId);
        if ($one === null) {
            return null;
        }
        $row = $one->rows[0];
        $byAsset = [];
        foreach ($d->publications->listByJob($a->jobId) as $p) {
            $byAsset[$p->assetId][] = $p;
        }
        $groups = [];
        $order = [];
        foreach ($d->assetOverrides->jobAssets($a->jobId) as $x) {
            $key = $x->lineId ?? '';
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $x->lineId !== null ? ($x->lineLabel !== '' ? $x->lineLabel : 'Deliverable') . ($x->channel !== '' ? ' · ' . $x->channel : '') : 'Other assets', 'rows' => []];
                $order[] = $key;
            }
            $groups[$key]['rows'][] = self::row($x, $byAsset[$x->id] ?? []);
        }
        $out = [];
        foreach ($order as $key) {
            $out[] = new AssetGroupVM($groups[$key]['label'], $groups[$key]['rows']);
        }
        $statuses = [];
        foreach (AssetStatus::OVERRIDE_VALUES as $v) {
            $statuses[] = new SelectOption($v, $v);
        }
        $posts = [];
        foreach (PublicationStatus::cases() as $s) {
            $posts[] = new SelectOption($s->value, $s->label());
        }
        return new JobAssetsVM($row->id, $row->jobNumber, $row->title !== '' ? $row->title : '(untitled)', $row->brandName, $row->stage, $out,
            Policy::canOverrideAssetStatus($u, $a)->allowed, $statuses, $posts, $savedNonce);
    }

    /** @param list<Publication> $pubs */
    public static function row(JobAsset $x, array $pubs): AssetRowVM
    {
        $posts = [];
        foreach ($pubs as $p) {
            $posts[] = new PostRowVM($p->id, $p->platform->label(), $p->status->value, $p->status->label(), $p->rowVersion);
        }
        return new AssetRowVM($x->id, $x->name, $x->status !== '' ? $x->status : AssetStatus::NEW, $x->assigneeName, $x->dueDate !== null ? fmt_date($x->dueDate) : '', $posts);
    }
}

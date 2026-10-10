<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Policy;
use App\Domain\Signals\CampaignSignals;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\ui\SelectOption;
use App\View\VM\CampaignGroupVM;
use App\View\VM\CampaignsVM;

/** GET /campaigns (by brand) and POST /campaigns (nc.* from the dialog; managers and admins). */
final class CampaignHandlers
{
    public static function list(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        // Every brand's campaigns: the account roles and admins only (nav: Policy::canSeeNav 'campaigns').
        $dec = Policy::canManageCampaign($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        return Shell::page($r, $d, 'Campaigns', 'campaigns', page_campaigns(self::vm($d, true, Policy::canCreateBrief($u)->allowed, '')));
    }

    public static function create(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $dec = Policy::canManageCampaign($u);
        if (!$dec->allowed) {
            return Shell::deny($r, $dec->reason);
        }
        $in = CampaignSignals::fromSignals($r->signals());
        $errors = $in->validate();
        if (!$errors->isEmpty()) {
            return Response::events(Toast::error($errors->first()));
        }
        $brand = $d->brands->get($in->brandId);
        if ($brand === null) {
            return Response::events(Toast::error('That brand does not exist.'));
        }
        if ($d->campaigns->nameTaken($brand->id, $in->name)) {
            return Response::events(Toast::error($brand->name . ' already has a campaign called ' . $in->name . '.'));
        }
        $id = $d->campaigns->create($brand->id, $in->name, $in->description, $u->id, $d->clock->now());
        $vm = self::vm($d, true, Policy::canCreateBrief($u)->allowed, 'Added ' . $in->name . ' to ' . $brand->name . '.', $id);
        return Response::events(PatchElements::html(partial_campaigns_panel($vm)), Toast::ok('Campaign created.'));
    }

    private static function vm(Deps $d, bool $canManage, bool $canCreateBrief, string $notice, string $createdId = ''): CampaignsVM
    {
        $groups = [];
        $brandOptions = [];
        $byBrand = [];
        foreach ($d->campaigns->listAll() as $c) {
            $byBrand[$c->brandId][] = $c;
        }
        foreach ($d->brands->list() as $b) {
            $groups[] = new CampaignGroupVM($b->id, $b->name, $b->prefix, $byBrand[$b->id] ?? []);
            $brandOptions[] = new SelectOption($b->id, $b->name);
        }
        return new CampaignsVM($groups, $brandOptions, $canManage, $canCreateBrief, $notice, $createdId);
    }
}

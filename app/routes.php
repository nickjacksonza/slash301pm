<?php
declare(strict_types=1);

// The route table: Go 1.22 ServeMux patterns, one route per action.
// Go: routes.go with mux.HandleFunc(pattern, handler).

use App\Http\Handlers\AccountHandlers;
use App\Http\Handlers\AdminUserHandlers;
use App\Http\Handlers\AssignmentHandlers;
use App\Http\Handlers\AuthHandlers;
use App\Http\Handlers\BriefHandlers;
use App\Http\Handlers\BriefLineHandlers;
use App\Http\Handlers\CampaignHandlers;
use App\Http\Handlers\JobHandlers;
use App\Http\Handlers\MyDayHandlers;
use App\Http\Handlers\SpikeHandlers;
use App\Http\Handlers\SystemHandlers;

return [
    ['GET /{$}', [SystemHandlers::class, 'home']],
    ['GET /healthz', [SystemHandlers::class, 'healthz']],
    // Phase 4: my day
    ['GET /today', [MyDayHandlers::class, 'page']],
    ['GET /today/sections/{section}', [MyDayHandlers::class, 'section']],
    ['POST /today/seen', [MyDayHandlers::class, 'markSeen']],

    ['GET /login', [AuthHandlers::class, 'loginForm']],
    ['POST /login', [AuthHandlers::class, 'login']],
    ['POST /logout', [AuthHandlers::class, 'logout']],
    ['POST /demo-login', [AuthHandlers::class, 'demoLogin']],

    ['GET /account/password', [AccountHandlers::class, 'passwordForm']],
    ['POST /account/password', [AccountHandlers::class, 'changePassword']],

    ['GET /admin/users', [AdminUserHandlers::class, 'list']],
    ['POST /admin/users', [AdminUserHandlers::class, 'create']],
    ['POST /admin/users/{id}/reset', [AdminUserHandlers::class, 'resetPassword']],
    ['POST /admin/users/{id}/deactivate', [AdminUserHandlers::class, 'deactivate']],
    ['POST /admin/users/{id}/activate', [AdminUserHandlers::class, 'activate']],

    ['GET /admin/system', [SystemHandlers::class, 'adminSystem']],
    ['POST /admin/system/migrate', [SystemHandlers::class, 'migrate']],

    ['GET /campaigns', [CampaignHandlers::class, 'list']],
    ['POST /campaigns', [CampaignHandlers::class, 'create']],

    ['GET /briefs', [BriefHandlers::class, 'mine']],
    ['POST /briefs', [BriefHandlers::class, 'create']],
    ['GET /jobs/{id}/brief', [BriefHandlers::class, 'editor']],
    ['PATCH /jobs/{id}/brief', [BriefHandlers::class, 'autosave']],
    ['GET /jobs/{id}/brief/send', [BriefHandlers::class, 'sendDialog']],
    ['POST /jobs/{id}/brief/send', [BriefHandlers::class, 'send']],
    ['GET /jobs/{id}/brief/update', [BriefHandlers::class, 'updateDialog']],
    ['POST /jobs/{id}/brief/update', [BriefHandlers::class, 'sendUpdate']],
    ['GET /jobs/{id}/brief/versions', [BriefHandlers::class, 'versions']],
    ['GET /jobs/{id}/brief/versions/{v}', [BriefHandlers::class, 'version']],
    ['GET /jobs/{id}/brief/print', [BriefHandlers::class, 'print']],
    ['POST /jobs/{id}/brief/assets', [BriefLineHandlers::class, 'add']],
    ['POST /jobs/{id}/brief/assets/order', [BriefLineHandlers::class, 'reorder']],
    ['PATCH /jobs/{id}/brief/assets/{aid}', [BriefLineHandlers::class, 'update']],
    ['DELETE /jobs/{id}/brief/assets/{aid}', [BriefLineHandlers::class, 'remove']],
    ['POST /jobs/{id}/assignments/{role}', [AssignmentHandlers::class, 'set']],
    ['POST /jobs/{id}/claim-am', [JobHandlers::class, 'claimAm']],
    ['POST /jobs/{id}/transition', [JobHandlers::class, 'transition']],

    ['GET /system/spike', [SpikeHandlers::class, 'page']],
    ['GET /system/spike/patch/{mode}', [SpikeHandlers::class, 'patch']],
    ['GET /system/spike/signals', [SpikeHandlers::class, 'signals']],
    ['GET /system/spike/toast', [SpikeHandlers::class, 'toast']],
    ['GET /system/spike/method', [SpikeHandlers::class, 'method']],
    ['POST /system/spike/method', [SpikeHandlers::class, 'method']],
    ['PUT /system/spike/method', [SpikeHandlers::class, 'method']],
    ['PATCH /system/spike/method', [SpikeHandlers::class, 'method']],
    ['DELETE /system/spike/method', [SpikeHandlers::class, 'method']],
    ['GET /system/spike/slow', [SpikeHandlers::class, 'slow']],
    ['GET /system/spike/html/{kind}', [SpikeHandlers::class, 'html']],
];

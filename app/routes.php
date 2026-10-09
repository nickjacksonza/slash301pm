<?php
declare(strict_types=1);

// The route table: Go 1.22 ServeMux patterns, one route per action.
// Go: routes.go with mux.HandleFunc(pattern, handler).

use App\Http\Handlers\AccountHandlers;
use App\Http\Handlers\AdminUserHandlers;
use App\Http\Handlers\AuthHandlers;
use App\Http\Handlers\SpikeHandlers;
use App\Http\Handlers\SystemHandlers;

return [
    ['GET /{$}', [SystemHandlers::class, 'home']],
    ['GET /healthz', [SystemHandlers::class, 'healthz']],
    ['GET /today', [SystemHandlers::class, 'today']],

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

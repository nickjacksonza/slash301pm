<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Dates;
use App\Domain\Policy;
use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\VM\LayoutVM;
use App\View\VM\NavItem;

/** Builds the shell view model and common replies. Handlers stay thin. */
final class Shell
{
    public static function layout(Request $r, Deps $d, string $title, string $active): LayoutVM
    {
        $user = $r->user();
        // Phase 4: my day
        $attention = 0;
        if ($user !== null) {
            try {
                $attention = $d->myDay->attentionCount($user->id, Dates::today($d->clock->now()));
            } catch (\Throwable $e) {
                error_log('[slash301pm] nav count: ' . $e->getMessage());
            }
        }
        $nav = [
            new NavItem('today', 'Today', url('/today'), true, $attention),
            new NavItem('briefs', 'Briefs', url('/briefs'), true),
            new NavItem('jobs', 'Jobs', url('/jobs'), false),
            new NavItem('board', 'Board', url('/jobs/board'), false),
            new NavItem('campaigns', 'Campaigns', url('/campaigns'), true),
        ];
        $admin = [];
        if ($user !== null && Policy::isAdmin($user)) {
            $admin = [
                new NavItem('admin-users', 'Users', url('/admin/users'), true),
                new NavItem('admin-system', 'System', url('/admin/system'), true),
                new NavItem('spike', 'Datastar spike', url('/system/spike'), true),
            ];
        }
        return new LayoutVM(
            $title,
            $active,
            $user !== null ? $user->name : '',
            $user !== null ? $user->role->value : '',
            $r->csrfToken(),
            $d->config->demoMode(),
            $nav,
            $admin,
        );
    }

    public static function page(Request $r, Deps $d, string $title, string $active, string $content): Response
    {
        return Response::page(layout_page(self::layout($r, $d, $title, $active), $content));
    }

    /** 403 page for plain requests, error toast (HTTP 200) for Datastar actions. */
    public static function deny(Request $r, string $reason): Response
    {
        return $r->isDatastar() ? Response::events(Toast::error($reason)) : Response::forbidden($reason);
    }
}

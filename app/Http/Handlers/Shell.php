<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Dates;
use App\Domain\MyDayMode;
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
                $mode = Policy::myDayMode($user);
                $today = Dates::today($d->clock->now());
                $attention = $mode === MyDayMode::Owner
                    ? $d->myDay->attentionCount($user->id, $today)
                    : $d->myDay->assignedAttentionCount($user->id, $today, $mode === MyDayMode::Traffic);
            } catch (\Throwable $e) {
                error_log('[slash301pm] nav count: ' . $e->getMessage());
            }
        }
        // Role-aware: Policy::canSeeNav() decides which items each role gets (no dead links).
        $items = [
            new NavItem('today', 'Today', url('/today'), true, $attention),
            new NavItem('briefs', 'Briefs', url('/briefs'), true),
            // Phase 3: jobs grid/board
            new NavItem('jobs', 'Jobs', url('/jobs'), true),
            new NavItem('board', 'Board', url('/jobs/board'), true),
            new NavItem('campaigns', 'Campaigns', url('/campaigns'), true),
            // Social publishing (visibility: Policy::canSeeNav 'social')
            new NavItem('social', 'Social', url('/social'), true),
        ];
        $adminItems = [
            new NavItem('admin-users', 'Users', url('/admin/users'), true),
            new NavItem('admin-system', 'System', url('/admin/system'), true),
            new NavItem('admin-overrides', 'Overrides', url('/admin/overrides'), true),
            new NavItem('spike', 'Datastar spike', url('/system/spike'), true),
        ];
        $nav = [];
        $admin = [];
        if ($user !== null) {
            foreach ($items as $it) {
                if (Policy::canSeeNav($user, $it->key)) {
                    $nav[] = $it;
                }
            }
            foreach ($adminItems as $it) {
                if (Policy::canSeeNav($user, $it->key)) {
                    $admin[] = $it;
                }
            }
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

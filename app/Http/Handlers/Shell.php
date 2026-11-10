<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Dates;
use App\Domain\DemoSwitch;
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
        // Reviews (owner spec 2026-10): posts to review, ECD approvals, changes requested, round warnings, ready for client.
        $reviewCount = 0;
        $reviewsApply = false;
        if ($user !== null) {
            try {
                $day = ReviewHandlers::day($d, $user);
                $reviewsApply = $day->applies();
                $reviewCount = $day->count();
            } catch (\Throwable $e) {
                error_log('[slash301pm] review count: ' . $e->getMessage());
            }
        }
        // Role-aware: Policy::canSeeNav() decides which items each role gets (no dead links).
        // Tabs in the legacy order. Work holds the grid and the board (toggle on the page).
        $items = [
            new NavItem('today', 'Today', url('/today'), true, $attention),
            new NavItem('jobs', 'Work', url('/jobs'), true),
            new NavItem('briefs', 'Briefs', url('/briefs'), true),
            new NavItem('campaigns', 'Campaigns', url('/campaigns'), true),
            // Social publishing (visibility: Policy::canSeeNav 'social')
            new NavItem('social', 'Social', url('/social'), true),
        ];
        if ($reviewsApply) {
            array_splice($items, 4, 0, [new NavItem('reviews', 'Reviews', url('/reviews'), true, $reviewCount)]);
        }
        $adminItems = [
            new NavItem('admin-users', 'Users', url('/admin/users'), true),
            new NavItem('admin-system', 'System', url('/admin/system'), true),
            new NavItem('admin-overrides', 'Overrides', url('/admin/overrides'), true),
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
        // Demo user switcher: the rule lives in DemoSwitch (demo mode on and a demo-picker session).
        $switchGroups = [];
        if ($user !== null) {
            $demoSession = AccountHandlers::isDemoSession($r->session());
            if (DemoSwitch::can($d->config->demoMode(), $demoSession)->allowed) {
                $switchGroups = AuthHandlers::demoGroups($d, $user->id);
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
            $switchGroups,
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

<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\BetaChecklist;
use App\Domain\Policy;
use App\Domain\Role;
use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\Store\MigrationLocked;
use App\View\VM\SystemVM;
use Throwable;

/** /, /healthz, /admin/system. */
final class SystemHandlers
{
    public static function home(Request $r, Deps $d): Response
    {
        return Response::redirect(url('/today'), 302);
    }

    /** Public JSON for the owner after each deploy. No secrets. */
    public static function healthz(Request $r, Deps $d): Response
    {
        $data = [
            'ok' => false,
            'php' => PHP_VERSION,
            'sqlite' => '',
            'driver' => $d->db->driver(),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'migration' => ['current' => 0, 'latest' => 0, 'failed' => false],
            'demo_mode' => $d->config->demoMode(),
            'transport' => $d->config->transport->value,
        ];
        try {
            $data['sqlite'] = $d->db->sqliteVersion();
            $current = $d->migrator->currentVersion();
            $latest = $d->migrator->latestVersion();
            $failed = $d->migrator->failure() !== null;
            $data['migration'] = ['current' => $current, 'latest' => $latest, 'failed' => $failed];
            $data['ok'] = $current >= $latest && !$failed;
        } catch (Throwable $e) {
            error_log('[slash301pm] healthz: ' . $e->getMessage());
        }
        return Response::json($data, $data['ok'] ? 200 : 503);
    }

    public static function adminSystem(Request $r, Deps $d): Response
    {
        $user = $r->user();
        $decision = $user === null ? null : Policy::canViewSystem($user);
        if ($user === null || $decision === null || !$decision->allowed) {
            return Shell::deny($r, $decision !== null ? $decision->reason : 'Sign in first.');
        }
        $notice = match ($r->query('notice')) {
            'migrated' => 'Migrations applied.',
            'failed' => 'The migration failed again. See the failure below.',
            'current' => 'Nothing to apply; the database is current.',
            'locked' => 'Another request is applying migrations right now. Reload in a few seconds.',
            default => '',
        };
        return Shell::page($r, $d, 'System', 'admin-system', page_admin_system(self::vm($r, $d, $notice)));
    }

    /** Retry after a failure: clears data/migrate-failed.json and runs pending migrations now. */
    public static function migrate(Request $r, Deps $d): Response
    {
        $user = $r->user();
        $decision = $user === null ? null : Policy::canViewSystem($user);
        if ($user === null || $decision === null || !$decision->allowed) {
            return Shell::deny($r, $decision !== null ? $decision->reason : 'Sign in first.');
        }
        if ($d->migrator->pending() === []) {
            $d->migrator->clearFailure();
            return Response::redirect(url('/admin/system', ['notice' => 'current']));
        }
        $d->migrator->clearFailure();
        try {
            $result = $d->migrator->run();
        } catch (MigrationLocked $e) {
            return Response::redirect(url('/admin/system', ['notice' => 'locked']));
        }
        return Response::redirect(url('/admin/system', ['notice' => $result->ok ? 'migrated' : 'failed']));
    }

    /**
     * POST /admin/system/demo-role-tasks (COO, demo mode only): the three role
     * tasks on up to 3 open sent jobs (DemoTaskStore). Answers with a toast.
     */
    public static function demoRoleTasks(Request $r, Deps $d): Response
    {
        $user = $r->user();
        $decision = $user === null ? null : Policy::canAddDemoRoleTasks($user, $d->config->demoMode());
        if ($user === null || $decision === null || !$decision->allowed) {
            return Shell::deny($r, $decision !== null ? $decision->reason : 'Sign in first.');
        }
        $res = $d->demoTasks->addRoleTasks($user->id, $d->clock->now());
        $msg = $res->jobNumbers === []
            ? ($res->skipped === [] ? 'No open sent job to add demo tasks to.' : 'Nothing to add: ' . implode(', ', $res->skipped) . ' already have the demo tasks.')
            : 'Added UTM link generation, Campaign hashtags and Asset test report to ' . implode(', ', $res->jobNumbers) . '.';
        if ($res->missingRoles !== []) {
            $msg .= ' No active ' . implode(', ', $res->missingRoles) . ' user exists, so ' . (count($res->missingRoles) === 1 ? 'that task stays' : 'those tasks stay') . ' unassigned.';
        }
        return Response::events($res->jobNumbers === [] || $res->missingRoles !== [] ? Toast::warn($msg) : Toast::ok($msg));
    }

    private static function vm(Request $r, Deps $d, string $notice): SystemVM
    {
        $pinned = '';
        $pinFile = $d->config->rootDir . '/public/js/datastar.js.sha256';
        if (is_file($pinFile)) {
            $pinned = strtok((string) file_get_contents($pinFile), " \n") ?: '';
        }
        $jsFile = $d->config->rootDir . '/public/js/datastar.js';
        $actual = is_file($jsFile) ? (string) hash_file('sha256', $jsFile) : '';
        $versions = [
            'PHP' => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'SQLite' => $d->db->sqliteVersion(),
            'Database driver' => $d->db->driver() . (extension_loaded('pdo_sqlite') ? '' : ' (pdo_sqlite missing, using the SQLite3 fallback)'),
            'Datastar' => '1.0.4, sha256 ' . substr($actual, 0, 16) . '...',
            'Transport' => $d->config->transport->value,
            'Environment' => $d->config->env->value,
        ];
        $status = $d->migrator->status();
        $isPinned = $pinned !== '' && hash_equals($pinned, $actual);
        $gate = BetaChecklist::items(
            $d->config->demoMode(), $d->seedPasswords->check(), count($d->users->listActiveByRole(Role::AM)),
            $status->failureJson !== null, count($status->pending), count($status->modified), count($status->backups), $isPinned,
        );
        $user = $r->user();
        $demoTasks = $user !== null && Policy::canAddDemoRoleTasks($user, $d->config->demoMode())->allowed;
        return new SystemVM($status, $versions, $d->config->demoMode(), $isPinned, $r->csrfToken(), $notice, $gate, $demoTasks,
            $user !== null && Policy::canViewOverridesReport($user)->allowed);
    }
}

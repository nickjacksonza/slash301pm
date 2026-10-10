<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Signals\NewUserSignals;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;
use App\View\VM\AdminUsersVM;
use App\View\VM\UserRowVM;

/**
 * /admin/users (COO and ECD). Every action answers with ONE patch (or one
 * toast) so it also works with transport=html. Never touches api/seed.php or
 * data/.demo_mode.
 */
final class AdminUserHandlers
{
    public static function list(Request $r, Deps $d): Response
    {
        $user = $r->user();
        $decision = $user === null ? null : Policy::canManageUsers($user);
        if ($user === null || $decision === null || !$decision->allowed) {
            return Shell::deny($r, $decision !== null ? $decision->reason : 'Sign in first.');
        }
        return Shell::page($r, $d, 'Users', 'admin-users', page_admin_users(self::vm($d, $user->id), ''));
    }

    public static function create(Request $r, Deps $d): Response
    {
        $user = $r->user();
        $decision = $user === null ? null : Policy::canManageUsers($user);
        if ($user === null || $decision === null || !$decision->allowed) {
            return Shell::deny($r, $decision !== null ? $decision->reason : 'Sign in first.');
        }
        $in = NewUserSignals::fromSignals($r->signals());
        $errors = $in->validate();
        if (!$errors->isEmpty()) {
            return Response::events(Toast::error($errors->first()));
        }
        if ($d->users->usernameTaken($in->username)) {
            return Response::events(Toast::error('That username is already taken.'));
        }
        $d->users->create($in->username, password_hash($in->password, PASSWORD_DEFAULT), $in->name, $in->email !== '' ? $in->email : null, Role::from($in->role), '#3b82f6', null, $user->id, $d->clock->now());
        $notice = 'Created ' . $in->name . ' (' . $in->username . '). Give them the password you set; they can change it at Change password.';
        return Response::events(PatchElements::html(partial_admin_users_panel(self::vm($d, $user->id), $notice)));
    }

    public static function resetPassword(Request $r, Deps $d): Response
    {
        $user = $r->user();
        $decision = $user === null ? null : Policy::canManageUsers($user);
        if ($user === null || $decision === null || !$decision->allowed) {
            return Shell::deny($r, $decision !== null ? $decision->reason : 'Sign in first.');
        }
        $target = $d->users->findById($r->pathValue('id'));
        if ($target === null) {
            return Response::events(Toast::error('User not found.'));
        }
        $password = self::generatePassword();
        $d->users->setPassword($target->id, password_hash($password, PASSWORD_DEFAULT), $user->id, 'password_reset', $d->clock->now());
        return Response::events(PatchElements::html(partial_admin_user_notice(
            'New password for ' . $target->name . ' (' . $target->username . '): ' . $password . ' . Copy it now; it is not shown again.'
        )));
    }

    public static function deactivate(Request $r, Deps $d): Response
    {
        return self::setActive($r, $d, false);
    }

    public static function activate(Request $r, Deps $d): Response
    {
        return self::setActive($r, $d, true);
    }

    private static function setActive(Request $r, Deps $d, bool $active): Response
    {
        $user = $r->user();
        if ($user === null) {
            return Shell::deny($r, 'Sign in first.');
        }
        $target = $d->users->findById($r->pathValue('id'));
        if ($target === null) {
            return Response::events(Toast::error('User not found.'));
        }
        $decision = $active ? Policy::canManageUsers($user) : Policy::canDeactivate($user, $target);
        if (!$decision->allowed) {
            return Shell::deny($r, $decision->reason);
        }
        $d->users->setActive($target->id, $active, $user->id, $d->clock->now());
        $fresh = $d->users->findById($target->id);
        if ($fresh === null) {
            return Response::events(Toast::error('User not found.'));
        }
        return Response::events(PatchElements::html(partial_admin_user_row(UserRowVM::from($fresh, $user->id))));
    }

    private static function vm(Deps $d, string $actorId): AdminUsersVM
    {
        $rows = [];
        foreach ($d->users->listAll() as $u) {
            $rows[] = UserRowVM::from($u, $actorId);
        }
        $roles = [];
        foreach (Role::ordered() as $role) {
            $roles[] = $role->value;
        }
        return new AdminUsersVM($rows, $roles);
    }

    /** 16 characters from an unambiguous alphabet (about 80 bits). */
    private static function generatePassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}

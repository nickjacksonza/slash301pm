<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\User;

/** One pure function per action. The actor always comes from the session. */
final class Policy
{
    public static function canManageUsers(User $actor): Decision
    {
        return self::isAdmin($actor) ? Decision::allow() : Decision::deny('Only the COO or ECD can manage users.');
    }

    public static function canViewSystem(User $actor): Decision
    {
        return self::isAdmin($actor) ? Decision::allow() : Decision::deny('Only the COO or ECD can open system pages.');
    }

    public static function canDeactivate(User $actor, User $target): Decision
    {
        $d = self::canManageUsers($actor);
        if (!$d->allowed) {
            return $d;
        }
        if ($actor->id === $target->id) {
            return Decision::deny('You cannot deactivate your own account.');
        }
        return Decision::allow();
    }

    /**
     * BetaGate: may this user use the new UI?
     * @param list<Role> $newUiRoles
     */
    public static function canUseNewUi(User $actor, array $newUiRoles): Decision
    {
        foreach ($newUiRoles as $role) {
            if ($actor->role === $role) {
                return Decision::allow();
            }
        }
        return Decision::deny('The new app is not open to your role yet.');
    }

    public static function isAdmin(User $actor): bool
    {
        return $actor->role === Role::COO || $actor->role === Role::ECD;
    }
}

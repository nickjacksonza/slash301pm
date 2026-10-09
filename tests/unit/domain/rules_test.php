<?php
declare(strict_types=1);

use App\Domain\LoginRules;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Signals\NewUserSignals;
use App\Domain\Types\User;

require_once dirname(__DIR__, 2) . '/support/app.php';

function dr_user(Role $role, string $id = 'u1'): User
{
    return new User($id, 'u', 'U', null, $role, '#000', null, true);
}

return [
    'password length is 12 to 128 bytes, like legacy' => function (): void {
        $cases = [['', false], [str_repeat('a', 11), false], [str_repeat('a', 12), true], [str_repeat('a', 128), true], [str_repeat('a', 129), false]];
        foreach ($cases as [$pw, $want]) {
            t_eq($want, LoginRules::passwordLengthOk($pw), 'length ' . strlen($pw));
        }
    },
    'retryAfter: 10 failures inside 15 minutes lock' => function (): void {
        $now = 1_000_000;
        $cases = [
            [9, $now - 100, 0],
            [10, $now - 100, 800],
            [10, $now - 899, 1],
            [12, $now - 10, 890],
            [10, null, 0],
        ];
        foreach ($cases as [$n, $oldest, $want]) {
            t_eq($want, LoginRules::retryAfter($n, $oldest, $now), "$n failures");
        }
        t_eq('user:alice', LoginRules::usernameKey('  Alice '));
    },
    'policy: admin pages are COO and ECD only' => function (): void {
        foreach (Role::cases() as $role) {
            $want = $role === Role::COO || $role === Role::ECD;
            t_eq($want, Policy::canManageUsers(dr_user($role))->allowed, $role->value);
            t_eq($want, Policy::canViewSystem(dr_user($role))->allowed, $role->value);
        }
        t_true(!Policy::canDeactivate(dr_user(Role::COO, 'a'), dr_user(Role::AM, 'a'))->allowed, 'not yourself');
        t_true(Policy::canDeactivate(dr_user(Role::COO, 'a'), dr_user(Role::AM, 'b'))->allowed);
    },
    'policy: new UI roles' => function (): void {
        $roles = [Role::AM, Role::COO, Role::ECD];
        t_true(Policy::canUseNewUi(dr_user(Role::AM), $roles)->allowed);
        t_true(!Policy::canUseNewUi(dr_user(Role::Designer), $roles)->allowed);
        t_true(!Policy::canUseNewUi(dr_user(Role::Client), $roles)->allowed);
    },
    'new user signals validate' => function (): void {
        $ok = NewUserSignals::fromSignals(['new_user' => ['name' => 'Ann Lee', 'username' => 'ann.lee', 'email' => '', 'role' => 'AM', 'password' => str_repeat('x', 12)]]);
        t_true($ok->validate()->isEmpty());
        $bad = NewUserSignals::fromSignals(['new_user' => ['name' => 'A', 'username' => 'Ann Lee', 'email' => 'nope', 'role' => 'King', 'password' => 'short']]);
        t_eq(['name', 'username', 'email', 'role', 'password'], array_keys($bad->validate()->errors));
        $junk = NewUserSignals::fromSignals(['new_user' => ['name' => ['x'], 'password' => 5]]);
        t_true(!$junk->validate()->isEmpty());
        t_true(!NewUserSignals::fromSignals([])->validate()->isEmpty());
    },
    'new password rules' => function (): void {
        t_true(LoginRules::validateNewPassword(str_repeat('a', 12), str_repeat('a', 12))->isEmpty());
        t_eq(['confirm'], array_keys(LoginRules::validateNewPassword(str_repeat('a', 12), str_repeat('b', 12))->errors));
        t_eq(['new', 'confirm'], array_keys(LoginRules::validateNewPassword('short', 'other')->errors));
    },
];

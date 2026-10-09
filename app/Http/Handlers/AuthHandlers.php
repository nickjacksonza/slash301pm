<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\LoginRules;
use App\Domain\Role;
use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\View\VM\DemoGroupVM;
use App\View\VM\DemoUserVM;
use App\View\VM\LoginVM;

/**
 * Sign in, sign out and demo sign-in. Writes the same session keys as
 * api/auth.php handleLogin() and api.php demo_login, so both apps share one login.
 */
final class AuthHandlers
{
    /** Real bcrypt (cost 12, like the live hashes) of a throwaway string, so unknown usernames cost the same time. */
    public const DUMMY_HASH = '$2y$12$MoMuqcrClPRL3B.gkzAdKe7.tV.9EdZHafEtcB2BZ6PGT55UKBTsm';

    public static function loginForm(Request $r, Deps $d): Response
    {
        if ($r->user() !== null) {
            return Response::redirect(url('/today'), 302);
        }
        $notice = $r->query('expired') === '1' ? 'Your session expired. Please sign in again.' : '';
        return self::render($r, $d, '', '', $notice, 200);
    }

    public static function login(Request $r, Deps $d): Response
    {
        $username = trim($r->form('username'));
        $password = $r->form('password');
        $ip = $r->clientIp();
        $userKey = LoginRules::usernameKey($username);
        $now = $d->clock->now()->getTimestamp();
        $since = $now - LoginRules::WINDOW_SECONDS;

        $d->loginAttempts->pruneBefore($since);
        $ipWin = $d->loginAttempts->failuresSince($ip, $since);
        $userWin = $d->loginAttempts->failuresSince($userKey, $since);
        $retry = max(
            LoginRules::retryAfter($ipWin->count, $ipWin->oldestAt, $now),
            LoginRules::retryAfter($userWin->count, $userWin->oldestAt, $now),
        );
        if ($retry > 0) {
            $minutes = (int) ceil($retry / 60);
            return self::render($r, $d, $username, "Too many sign-in attempts. Try again in $minutes minute" . ($minutes === 1 ? '' : 's') . '.', '', 429)
                ->withHeader('Retry-After', (string) $retry);
        }
        if ($username === '' || $password === '') {
            return self::render($r, $d, $username, 'Enter your username and password.', '', 200);
        }

        $user = LoginRules::passwordLengthOk($password) ? $d->users->findByUsername($username) : null;
        $hash = $user !== null ? ($d->users->passwordHash($user->id) ?? self::DUMMY_HASH) : self::DUMMY_HASH;
        $valid = password_verify($password, $hash);   // always runs, so timing does not reveal usernames
        if ($user === null || !$valid) {
            $d->loginAttempts->recordFailure($ip, $now);
            $d->loginAttempts->recordFailure($userKey, $now);
            return self::render($r, $d, $username, 'Invalid username or password.', '', 200);
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $d->users->setPassword($user->id, password_hash($password, PASSWORD_DEFAULT));
        }
        self::startLogin($r->session(), $user, $now);
        $d->loginAttempts->clear($ip);
        $d->loginAttempts->clear($userKey);
        return Response::redirect(url('/today'));
    }

    public static function logout(Request $r, Deps $d): Response
    {
        $r->session()->destroy();
        return Response::navigate($r->isDatastar(), url('/login'));
    }

    /** Demo mode only (data/.demo_mode exists). POST + CSRF, like the hotfixed legacy demo_login. */
    public static function demoLogin(Request $r, Deps $d): Response
    {
        if (!$d->config->demoMode()) {
            return Response::forbidden('Demo mode is not enabled.');
        }
        $user = $d->users->findById($r->form('user_id'));
        if ($user === null || !$user->isActive) {
            return self::render($r, $d, '', 'That user does not exist.', '', 200);
        }
        $s = $r->session();
        $s->regenerate();
        $s->remove('user_id');   // a real login would otherwise win over the demo user in both apps
        $s->set('demo_user_id', $user->id);
        $s->set('user_role', $user->role->value);
        $s->set('user_brand_id', $user->brandId);
        $s->set('last_activity', $d->clock->now()->getTimestamp());
        return Response::redirect(url('/today'));
    }

    /** Same keys and order as legacy handleLogin(). */
    public static function startLogin(Session $s, User $user, int $now): void
    {
        $s->regenerate();
        $s->set('user_id', $user->id);
        $s->set('user_role', $user->role->value);
        $s->set('user_brand_id', $user->brandId);
        $s->set('last_activity', $now);
        $s->set('login_at', $now);
        $s->set('csrf_token', bin2hex(random_bytes(32)));
    }

    private static function render(Request $r, Deps $d, string $username, string $error, string $notice, int $status): Response
    {
        $demo = $d->config->demoMode();
        return Response::page(page_login(new LoginVM($r->csrfToken(), $username, $error, $notice, $demo, $demo ? self::demoGroups($d) : [])), $status);
    }

    /** @return list<DemoGroupVM> active users grouped by role, agency roles first */
    private static function demoGroups(Deps $d): array
    {
        $byRole = [];
        foreach ($d->users->listActive() as $u) {
            $byRole[$u->role->value][] = new DemoUserVM($u->id, $u->name, $u->username);
        }
        $groups = [];
        foreach (Role::ordered() as $role) {
            if (isset($byRole[$role->value])) {
                $groups[] = new DemoGroupVM($role->value, $byRole[$role->value]);
            }
        }
        return $groups;
    }
}

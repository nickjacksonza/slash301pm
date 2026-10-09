<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\PhpSession;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use Closure;

/**
 * Loads the session user from the database on every request (active users
 * only), as legacy getSessionUser() does, with the same 1 hour idle timeout.
 * In demo mode (data/.demo_mode exists) a session with only demo_user_id is
 * accepted, as legacy api.php does. Pages other than PUBLIC need a user.
 */
final class Auth
{
    public const PUBLIC = ['/login', '/logout', '/demo-login', '/healthz'];

    /** Session keys that make up a login, shared with api/auth.php. */
    public const LOGIN_KEYS = ['user_id', 'user_role', 'user_brand_id', 'last_activity', 'login_at', 'demo_user_id'];

    public static function wrap(Closure $next): Closure
    {
        return static function (Request $r, Deps $d) use ($next): Response {
            $user = self::resolve($r->session(), $d);
            $r = $r->withUser($user);
            if ($user === null && !in_array($r->path(), self::PUBLIC, true)) {
                return Response::navigate($r->isDatastar(), url('/login'));
            }
            return $next($r, $d);
        };
    }

    public static function resolve(Session $s, Deps $d): ?User
    {
        $uid = $s->get('user_id');
        $demoId = $s->get('demo_user_id');
        $hasUid = is_string($uid) && $uid !== '';
        $hasDemo = is_string($demoId) && $demoId !== '';
        if (!$hasUid && !$hasDemo) {
            return null;
        }
        $now = $d->clock->now()->getTimestamp();
        $last = $s->get('last_activity');
        if (is_int($last) && $now - $last > PhpSession::IDLE_SECONDS) {
            self::endLogin($s);
            return null;
        }
        $user = null;
        if ($hasUid) {
            $found = $d->users->findById($uid);
            if ($found === null || !$found->isActive) {
                self::endLogin($s);
                return null;
            }
            $user = $found;
        } elseif ($d->config->demoMode()) {
            $found = $d->users->findById($demoId);
            $user = $found !== null && $found->isActive ? $found : null;
        }
        if ($user !== null) {
            $s->set('last_activity', $now);
        }
        return $user;
    }

    /** Forget the login but keep a fresh anonymous session (new id, new CSRF token). */
    public static function endLogin(Session $s): void
    {
        foreach (self::LOGIN_KEYS as $k) {
            $s->remove($k);
        }
        $s->regenerate();
        $s->set('csrf_token', bin2hex(random_bytes(32)));
    }
}

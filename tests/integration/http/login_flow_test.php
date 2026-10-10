<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\LoginRules;
use App\Domain\Role;
use App\Http\MemorySession;

require_once dirname(__DIR__, 2) . '/support/app.php';

const LF_PW = 'correct horse battery';

/** Anonymous session that already went through GET /login. */
function lf_session(): MemorySession
{
    return new MemorySession(['csrf_token' => 'anon-token']);
}

function lf_post_login(MemorySession $s, string $username, string $password, ?string $csrf = null): App\Http\Response
{
    $app = ts_app($s);
    return $app(ts_request('POST', '/login', ['sec-fetch-site' => 'same-origin', 'origin' => 'http://127.0.0.1:8301'], '', ['username' => $username, 'password' => $password, '_csrf' => $csrf ?? (string) $s->get('csrf_token')]), $GLOBALS['lf_deps']);
}

return [
    'GET /login renders the form with the CSRF field' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        $s = new MemorySession();
        $resp = (ts_app($s))(ts_request('GET', '/login'), $d);
        t_eq(200, $resp->status());
        $html = ts_body($resp);
        t_contains('name="_csrf" value="' . $s->get('csrf_token') . '"', $html);
        t_true(strlen((string) $s->get('csrf_token')) === 64, 'session got a token');
        t_not_contains('Demo mode: sign in as', $html);
    },
    'good password logs in with the legacy session keys' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        $id = ts_user($d, 'ann', Role::AM, LF_PW);
        $s = lf_session();
        $resp = lf_post_login($s, 'ann', LF_PW);
        t_eq(303, $resp->status());
        t_eq('/slash301pm/today', $resp->render(Transport::Sse)->header('Location'));
        t_eq($id, $s->get('user_id'));
        t_eq('AM', $s->get('user_role'));
        t_eq(null, $s->get('user_brand_id'));
        t_true(is_int($s->get('last_activity')) && is_int($s->get('login_at')));
        t_true($s->get('csrf_token') !== 'anon-token', 'fresh csrf token');
        t_eq(1, $s->regenerations, 'session id regenerated');
        // and the next request is the logged-in AM
        $today = (ts_app($s))(ts_request('GET', '/today'), $d);
        t_eq(200, $today->status());
        t_contains(', Ann', ts_body($today));
    },
    'bad password, unknown user and short password all fail the same way' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        ts_user($d, 'ann', Role::AM, LF_PW);
        foreach ([['ann', 'wrong password!!'], ['nobody', LF_PW], ['ann', 'short']] as [$u, $p]) {
            $s = lf_session();
            $resp = lf_post_login($s, $u, $p);
            t_eq(200, $resp->status(), "$u status");
            t_contains('Invalid username or password.', ts_body($resp), $u);
            t_eq(null, $s->get('user_id'));
        }
        $now = $d->clock->now()->getTimestamp();
        t_eq(3, $d->loginAttempts->failuresSince('127.0.0.1', $now - 900)->count, 'ip failures');
        t_eq(2, $d->loginAttempts->failuresSince(LoginRules::usernameKey('ann'), $now - 900)->count, 'username failures');
    },
    'rate limit: after 10 failures even the right password is refused for 15 minutes' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        ts_user($d, 'ann', Role::AM, LF_PW);
        for ($i = 0; $i < 10; $i++) {
            lf_post_login(lf_session(), 'ann', 'wrong password ' . $i);
        }
        $s = lf_session();
        $resp = lf_post_login($s, 'ann', LF_PW);
        t_eq(429, $resp->status());
        t_contains('Too many sign-in attempts', ts_body($resp));
        t_eq('900', $resp->render(Transport::Sse)->header('Retry-After'));
        t_eq(null, $s->get('user_id'));
        $d->clock->advance(901);
        $ok = lf_post_login($s, 'ann', LF_PW);
        t_eq(303, $ok->status(), 'allowed again after the window');
    },
    'rate limit by username also applies from other IPs' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        ts_user($d, 'ann', Role::AM, LF_PW);
        $now = $d->clock->now()->getTimestamp();
        for ($i = 0; $i < 10; $i++) {
            $d->loginAttempts->recordFailure(LoginRules::usernameKey('ann'), $now - 10);
        }
        t_eq(429, lf_post_login(lf_session(), 'ANN', LF_PW)->status());
    },
    'login with a stale CSRF token goes back to the form' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        ts_user($d, 'ann', Role::AM, LF_PW);
        $s = lf_session();
        $resp = lf_post_login($s, 'ann', LF_PW, 'stale');
        t_eq(303, $resp->status());
        t_eq('/slash301pm/login?expired=1', $resp->render(Transport::Sse)->header('Location'));
        t_eq(null, $s->get('user_id'));
    },
    'inactive users cannot log in, and a deactivated session ends' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        $id = ts_user($d, 'ann', Role::AM, LF_PW);
        $s = lf_session();
        t_eq(303, lf_post_login($s, 'ann', LF_PW)->status());
        $d->users->setActive($id, false);
        $resp = (ts_app($s))(ts_request('GET', '/today'), $d);
        t_eq(303, $resp->status());
        t_eq('/slash301pm/login', $resp->render(Transport::Sse)->header('Location'));
        t_eq(null, $s->get('user_id'));
        t_contains('Invalid username or password.', ts_body(lf_post_login(lf_session(), 'ann', LF_PW)));
    },
    'idle for over an hour ends the login' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        ts_user($d, 'ann', Role::AM, LF_PW);
        $s = lf_session();
        lf_post_login($s, 'ann', LF_PW);
        $d->clock->advance(3601);
        t_eq(303, (ts_app($s))(ts_request('GET', '/today'), $d)->status());
        t_eq(null, $s->get('user_id'));
    },
    'anonymous Datastar action gets a Redirect event with HTTP 200' => function (): void {
        $d = ts_deps();
        $s = lf_session();
        $resp = (ts_app($s))(ts_ds_request('GET', '/today', $s), $d);
        t_eq(200, $resp->status());
        t_contains('window.location.assign("/slash301pm/login")', ts_body($resp));
    },
    'logout destroys the session' => function (): void {
        $GLOBALS['lf_deps'] = $d = ts_deps();
        ts_user($d, 'ann', Role::AM, LF_PW);
        $s = lf_session();
        lf_post_login($s, 'ann', LF_PW);
        $resp = (ts_app($s))(ts_request('POST', '/logout', [], '', ['_csrf' => (string) $s->get('csrf_token')]), $d);
        t_eq(303, $resp->status());
        t_true($s->destroyed);
        t_eq(null, $s->get('user_id'));
    },
    'demo login works only when the demo flag exists (temp data dir)' => function (): void {
        $off = ts_deps(false);
        $idOff = ts_user($off, 'ann', Role::AM, LF_PW);
        $s = lf_session();
        $resp = (ts_app($s))(ts_request('POST', '/demo-login', [], '', ['user_id' => $idOff, '_csrf' => 'anon-token']), $off);
        t_eq(403, $resp->status());
        t_eq(null, $s->get('demo_user_id'));
        t_true(!is_file($off->config->demoFlagPath()));

        $on = ts_deps(true);
        t_true(str_starts_with($on->config->demoFlagPath(), sys_get_temp_dir()), 'flag lives in the temp dir');
        $id = ts_user($on, 'ann', Role::AM, LF_PW);
        ts_user($on, 'dee', Role::Designer, LF_PW);
        $s2 = lf_session();
        $page = ts_body((ts_app($s2))(ts_request('GET', '/login'), $on));
        t_contains('Demo mode: sign in as', $page);
        t_contains('<legend class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">AM</legend>', $page);
        t_contains('<legend class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Designer</legend>', $page);
        $resp2 = (ts_app($s2))(ts_request('POST', '/demo-login', [], '', ['user_id' => $id, '_csrf' => (string) $s2->get('csrf_token')]), $on);
        t_eq(303, $resp2->status());
        t_eq($id, $s2->get('demo_user_id'));
        t_eq(null, $s2->get('user_id'));
        t_eq('AM', $s2->get('user_role'));
        $today = (ts_app($s2))(ts_request('GET', '/today'), $on);
        t_eq(200, $today->status());
        t_contains('Demo mode is on', ts_body($today));
        // demo login still needs the CSRF field
        $s3 = lf_session();
        t_eq(403, (ts_app($s3))(ts_request('POST', '/demo-login', [], '', ['user_id' => $id, '_csrf' => 'nope']), $on)->status());
        t_eq(null, $s3->get('demo_user_id'));
    },
    'a demo session is ignored once demo mode is off' => function (): void {
        $d = ts_deps(false);
        $id = ts_user($d, 'ann', Role::AM, LF_PW);
        $s = new MemorySession(['csrf_token' => 't', 'demo_user_id' => $id]);
        t_eq(303, (ts_app($s))(ts_request('GET', '/today'), $d)->status());
    },
];

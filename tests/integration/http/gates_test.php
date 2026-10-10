<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\Role;
use App\Http\Handlers\AuthHandlers;
use App\Http\MemorySession;

require_once dirname(__DIR__, 2) . '/support/app.php';

function gt_session(App\Http\Deps $d, Role $role, string $name = 'pat'): MemorySession
{
    $id = ts_user($d, $name, $role);
    $s = new MemorySession(['csrf_token' => 'x']);
    AuthHandlers::startLogin($s, $d->users->findById($id), $d->clock->now()->getTimestamp());
    return $s;
}

return [
    'BetaGate: Clients go to /legacy/ with a 302 (the client portal comes later)' => function (): void {
        $d = ts_deps();
        foreach ([Role::Client] as $i => $role) {
            $s = gt_session($d, $role, 'u' . $i);
            $resp = (ts_app($s))(ts_request('GET', '/today'), $d);
            t_eq(302, $resp->status(), $role->value);
            t_eq('/slash301pm/legacy/', $resp->render(Transport::Sse)->header('Location'));
            $ds = (ts_app($s))(ts_ds_request('GET', '/today', $s), $d);
            t_eq(200, $ds->status());
            t_contains('{"_redirect":"/slash301pm/legacy/"}', ts_body($ds));
            // password change and sign out stay open to everyone
            t_eq(200, (ts_app($s))(ts_request('GET', '/account/password'), $d)->status());
        }
        foreach (Role::ordered() as $i => $role) {
            if ($role === Role::Client) {
                continue;
            }
            $s = gt_session($d, $role, 'b' . $i);
            t_eq(200, (ts_app($s))(ts_request('GET', '/today'), $d)->status(), $role->value);
        }
    },
    'CSRF: Datastar request without or with a wrong token is HTTP 200 with an error toast' => function (): void {
        $d = ts_deps();
        $s = gt_session($d, Role::AM);
        $signals = ['pw' => ['current' => 'correct horse battery', 'new' => 'another long password', 'confirm' => 'another long password']];
        foreach ([false, 'wrong'] as $variant) {
            $req = ts_ds_request('POST', '/account/password', $s, $signals, $variant !== false);
            if ($variant === 'wrong') {
                $req = new App\Http\Request('POST', '/account/password', [], ['datastar-request' => 'true', 'x-csrf-token' => 'wrong', 'content-type' => 'application/json', 'host' => '127.0.0.1:8301'], (string) json_encode($signals), [], '127.0.0.1', 'http');
            }
            $resp = (ts_app($s))($req, $d);
            t_eq(200, $resp->status());
            $body = ts_body($resp);
            t_contains('data: selector #toasts', $body);
            t_contains('Security check failed', $body);
        }
        $hash = $d->users->passwordHash((string) $s->get('user_id'));
        t_true(password_verify('correct horse battery', (string) $hash), 'password unchanged');
    },
    'CSRF: cross-site and wrong-origin requests are refused even with the token' => function (): void {
        $d = ts_deps();
        $s = gt_session($d, Role::AM);
        $base = ['datastar-request' => 'true', 'x-csrf-token' => (string) $s->get('csrf_token'), 'content-type' => 'application/json', 'host' => '127.0.0.1:8301'];
        foreach ([['sec-fetch-site' => 'cross-site'], ['sec-fetch-site' => 'same-site'], ['origin' => 'https://evil.example']] as $extra) {
            $req = new App\Http\Request('POST', '/account/password', [], $extra + $base, '{}', [], '127.0.0.1', 'http');
            t_contains('Security check failed', ts_body((ts_app($s))($req, $d)));
        }
        // a JSON post without the Datastar header is refused too
        $plain = new App\Http\Request('POST', '/account/password', [], ['x-csrf-token' => (string) $s->get('csrf_token'), 'content-type' => 'application/json', 'host' => '127.0.0.1:8301'], '{}', [], '127.0.0.1', 'http');
        t_eq(403, (ts_app($s))($plain, $d)->status());
    },
    'CSRF: the right token passes and the password changes' => function (): void {
        $d = ts_deps();
        $s = gt_session($d, Role::AM);
        $signals = ['pw' => ['current' => 'correct horse battery', 'new' => 'another long password', 'confirm' => 'another long password']];
        $req = ts_ds_request('POST', '/account/password', $s, $signals);
        $resp = (ts_app($s))($req, $d);
        $body = ts_body($resp);
        t_contains('id="pw-panel"', $body);
        t_contains('Password changed', $body);
        t_true(password_verify('another long password', (string) $d->users->passwordHash((string) $s->get('user_id'))));
        // wrong current password: toast, nothing changes
        $again = ts_body((ts_app($s))(ts_ds_request('POST', '/account/password', $s, $signals), $d));
        t_contains('Your current password is not right.', $again);
    },
    'admin users: COO creates, AM is refused, nobody deactivates themselves' => function (): void {
        $d = ts_deps();
        $coo = gt_session($d, Role::COO, 'boss');
        $sig = ['new_user' => ['name' => 'New Person', 'username' => 'new.person', 'email' => '', 'role' => 'AM', 'password' => 'a long first password']];
        $body = ts_body((ts_app($coo))(ts_ds_request('POST', '/admin/users', $coo, $sig), $d));
        t_contains('id="users-panel"', $body);
        t_contains('Created New Person', $body);
        $u = $d->users->findByUsername('new.person');
        t_true($u !== null && $u->role === Role::AM);
        t_contains('already taken', ts_body((ts_app($coo))(ts_ds_request('POST', '/admin/users', $coo, $sig), $d)));
        t_contains('Name must be', ts_body((ts_app($coo))(ts_ds_request('POST', '/admin/users', $coo, ['new_user' => ['name' => 'x']]), $d)));

        $am = gt_session($d, Role::AM, 'acct');
        t_eq(403, (ts_app($am))(ts_request('GET', '/admin/users'), $d)->status());
        t_contains('Only the COO or ECD', ts_body((ts_app($am))(ts_ds_request('POST', '/admin/users', $am, $sig), $d)));

        $self = (string) $coo->get('user_id');
        t_contains('cannot deactivate your own', ts_body((ts_app($coo))(ts_ds_request('POST', '/admin/users/' . $self . '/deactivate', $coo), $d)));
        $row = ts_body((ts_app($coo))(ts_ds_request('POST', '/admin/users/' . $u->id . '/deactivate', $coo), $d));
        t_contains('id="user-row-' . $u->id . '"', $row);
        t_contains('Deactivated', $row);
        t_true(!$d->users->findById($u->id)->isActive);
        $reset = ts_body((ts_app($coo))(ts_ds_request('POST', '/admin/users/' . $u->id . '/reset', $coo), $d));
        t_true(preg_match('/New password for New Person \(new\.person\): ([A-Za-z0-9]{16})/', $reset, $m) === 1, 'new password shown once');
        t_true(password_verify($m[1], (string) $d->users->passwordHash($u->id)));
    },
    'healthz is public JSON and admin/system is COO/ECD only' => function (): void {
        $d = ts_deps();
        $s = new MemorySession();
        $h = (ts_app($s))(ts_request('GET', '/healthz'), $d);
        t_eq(200, $h->status());
        $j = json_decode(ts_body($h), true);
        t_eq(true, $j['ok']);
        t_eq($d->migrator->latestVersion(), $j['migration']['current']);
        t_eq(false, $j['demo_mode']);
        t_true($s->data === [], 'healthz starts no session');
        $am = gt_session($d, Role::AM);
        t_eq(403, (ts_app($am))(ts_request('GET', '/admin/system'), $d)->status());
        $coo = gt_session($d, Role::COO, 'boss');
        $page = ts_body((ts_app($coo))(ts_request('GET', '/admin/system'), $d));
        t_contains('id="migration-level">' . $d->migrator->latestVersion() . '</strong>', $page);
        t_contains('pre-0001-', $page);
        t_contains('matches public/js/datastar.js.sha256', $page);
    },
    'spike endpoints: every method echoes the signals intact' => function (): void {
        $d = ts_deps();
        $coo = gt_session($d, Role::COO, 'boss');
        $sig = ['spike_echo' => ['n' => 41, 's' => App\Http\Handlers\SpikeHandlers::ECHO_S]];
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m) {
            $body = ts_body((ts_app($coo))(ts_ds_request($m, '/system/spike/method', $coo, $sig), $d));
            t_contains('"m_' . strtolower($m) . '":"pass"', $body, $m);
        }
        foreach (['outer', 'inner', 'replace', 'prepend', 'append', 'before', 'after', 'remove'] as $mode) {
            $body = ts_body((ts_app($coo))(ts_ds_request('GET', '/system/spike/patch/' . $mode, $coo), $d));
            t_contains('event: datastar-patch-elements', $body, $mode);
        }
        $html = (ts_app($coo))(ts_ds_request('GET', '/system/spike/html/inner', $coo), $d)->render(Transport::Sse);
        t_eq('#t-html-inner', $html->header('Datastar-Selector'));
        $slow = (ts_app($coo))(ts_ds_request('GET', '/system/spike/slow', $coo), $d)->render(Transport::Html);
        t_eq(4, count($slow->chunks));
        t_eq(1000, $slow->pauseMs);
        $am = gt_session($d, Role::AM, 'acct');
        t_eq(403, (ts_app($am))(ts_request('GET', '/system/spike'), $d)->status());
        t_eq(200, (ts_app($coo))(ts_request('GET', '/system/spike'), $d)->status());
    },
    'every foundation Datastar response also renders with transport=html (one patch each)' => function (): void {
        $d = ts_deps();
        $coo = gt_session($d, Role::COO, 'boss');
        $target = ts_user($d, 'target', Role::AM);
        $pw = ['pw' => ['current' => 'correct horse battery', 'new' => 'another long password', 'confirm' => 'another long password']];
        $nu = ['new_user' => ['name' => 'New Person', 'username' => 'np', 'email' => '', 'role' => 'AM', 'password' => 'a long first password']];
        $requests = [
            ts_ds_request('POST', '/account/password', $coo, $pw, false),          // CSRF toast
            ts_ds_request('POST', '/account/password', $coo, $pw),                 // success panel
            ts_ds_request('POST', '/admin/users', $coo, $nu),                      // created panel
            ts_ds_request('POST', '/admin/users', $coo, ['new_user' => []]),       // validation toast
            ts_ds_request('POST', '/admin/users/' . $target . '/reset', $coo),
            ts_ds_request('POST', '/admin/users/' . $target . '/deactivate', $coo),
            ts_ds_request('POST', '/admin/users/' . $target . '/activate', $coo),
        ];
        foreach ($requests as $i => $req) {
            $r = (ts_app($coo))($req, $d)->render(Transport::Html);
            t_eq(200, $r->status, "request $i");
        }
        $anon = new MemorySession(['csrf_token' => 'x']);
        t_eq('application/json; charset=utf-8', (ts_app($anon))(ts_ds_request('GET', '/today', $anon), $d)->render(Transport::Html)->header('Content-Type'));
    },
];

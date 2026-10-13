<?php
declare(strict_types=1);

use App\Domain\Role;
use App\Http\MemorySession;

require_once dirname(__DIR__, 2) . '/support/app.php';

/** Demo user switcher: POST /account/switch/{userId} (docs/beta-gate.md). */
function dsw_demo_session(string $userId, Role $role = Role::AM): MemorySession
{
    return new MemorySession(['csrf_token' => 'tok', 'demo_user_id' => $userId, 'user_role' => $role->value, 'last_activity' => time()]);
}

function dsw_post(MemorySession $s, \App\Http\Deps $d, string $target, string $csrf = 'tok'): \App\Http\Response
{
    return (ts_app($s))(ts_request('POST', '/account/switch/' . $target, [], '', ['_csrf' => $csrf]), $d);
}

return [
    'switch changes the session user, regenerates the id and redirects to /today' => function (): void {
        $d = ts_deps(true);
        $ann = ts_user($d, 'ann', Role::AM);
        $dee = ts_user($d, 'dee', Role::Designer);
        $s = dsw_demo_session($ann);
        $resp = dsw_post($s, $d, $dee);
        t_eq(303, $resp->status());
        t_eq($dee, $s->get('demo_user_id'));
        t_eq('Designer', $s->get('user_role'));
        t_eq(null, $s->get('user_id'));
        t_true($s->regenerations > 0, 'session id regenerated');
        $today = (ts_app($s))(ts_request('GET', '/today'), $d);
        t_eq(200, $today->status());
        t_contains('Dee', ts_body($today));
    },
    'menu lists active users by role with the current one marked, and only in demo mode' => function (): void {
        $d = ts_deps(true);
        $ann = ts_user($d, 'ann', Role::AM);
        $dee = ts_user($d, 'dee', Role::Designer);
        $gone = ts_user($d, 'gone', Role::QA, 'correct horse battery', false);
        $s = dsw_demo_session($ann);
        $html = ts_body((ts_app($s))(ts_request('GET', '/today'), $d));
        t_contains('data-switch-menu', $html);
        t_contains('/account/switch/' . $dee, $html);
        t_not_contains('/account/switch/' . $gone, $html);
        t_contains('aria-current="true"', $html);
        // flag removed: the menu is gone (and the demo session no longer works at all)
        unlink($d->config->demoFlagPath());
        t_not_contains('data-switch-menu', ts_body((ts_app(dsw_demo_session($ann)))(ts_request('GET', '/login'), $d)));
    },
    'refused with 403 when demo mode is off, even called directly' => function (): void {
        $d = ts_deps(false);
        $ann = ts_user($d, 'ann', Role::AM);
        $dee = ts_user($d, 'dee', Role::Designer);
        // a real login (user_id) so the request reaches the handler
        $s = new MemorySession(['csrf_token' => 'tok', 'user_id' => $ann, 'user_role' => 'AM', 'last_activity' => time()]);
        t_eq(403, dsw_post($s, $d, $dee)->status());
        t_eq($ann, $s->get('user_id'));
        t_eq(null, $s->get('demo_user_id'));
    },
    'refused when the session is a real login, even in demo mode' => function (): void {
        $d = ts_deps(true);
        $coo = ts_user($d, 'boss', Role::COO);
        $dee = ts_user($d, 'dee', Role::Designer);
        $s = new MemorySession(['csrf_token' => 'tok', 'user_id' => $coo, 'user_role' => 'COO', 'last_activity' => time()]);
        t_eq(403, dsw_post($s, $d, $dee)->status());
        t_eq($coo, $s->get('user_id'));
        t_not_contains('data-switch-menu', ts_body((ts_app($s))(ts_request('GET', '/today'), $d)));
    },
    'refused for an unknown or inactive user, session unchanged' => function (): void {
        $d = ts_deps(true);
        $ann = ts_user($d, 'ann', Role::AM);
        $gone = ts_user($d, 'gone', Role::QA, 'correct horse battery', false);
        foreach (['does-not-exist', $gone] as $target) {
            $s = dsw_demo_session($ann);
            t_eq(403, dsw_post($s, $d, $target)->status());
            t_eq($ann, $s->get('demo_user_id'));
        }
    },
    'CSRF token is required' => function (): void {
        $d = ts_deps(true);
        $ann = ts_user($d, 'ann', Role::AM);
        $dee = ts_user($d, 'dee', Role::Designer);
        $s = dsw_demo_session($ann);
        t_eq(403, dsw_post($s, $d, $dee, 'wrong')->status());
        t_eq(403, (ts_app($s))(ts_request('POST', '/account/switch/' . $dee), $d)->status());
        t_eq($ann, $s->get('demo_user_id'));
    },
];

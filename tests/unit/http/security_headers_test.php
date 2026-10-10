<?php
declare(strict_types=1);

use App\Config\Config;
use App\Config\Env;
use App\Config\Transport;
use App\Domain\Role;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Response;
use App\Http\Toast;

require_once dirname(__DIR__, 2) . '/support/app.php';

function sh_config(Env $env): Config
{
    return new Config($env, dirname(__DIR__, 3), '/tmp/x', '/tmp/x/db', '/slash301pm', 'projects.slash301.com', '', $env === Env::Live, Transport::Sse, [Role::AM]);
}

return [
    'security headers: the full set, HSTS on live only' => function (): void {
        $local = SecurityHeaders::headers(sh_config(Env::Local));
        $live = SecurityHeaders::headers(sh_config(Env::Live));
        foreach ([$local, $live] as $h) {
            t_eq('strict-origin-when-cross-origin', $h['Referrer-Policy']);
            t_eq('nosniff', $h['X-Content-Type-Options']);
            t_eq('DENY', $h['X-Frame-Options']);
            t_contains('camera=()', $h['Permissions-Policy']);
            t_not_contains('interest-cohort', $h['Permissions-Policy'], 'unknown features log console errors in Chromium');
        }
        t_true(!isset($local['Strict-Transport-Security']), 'no HSTS locally (plain http)');
        t_eq('max-age=63072000; includeSubDomains', $live['Strict-Transport-Security']);
    },
    'CSP: same-origin only, eval for Datastar, no inline scripts, no framing' => function (): void {
        $csp = [];
        foreach (explode(';', SecurityHeaders::CSP) as $part) {
            $words = preg_split('/\s+/', trim($part)) ?: [];
            $csp[(string) array_shift($words)] = $words;
        }
        t_eq(["'self'"], $csp['default-src']);
        t_eq(["'self'", "'unsafe-eval'"], $csp['script-src'], 'Datastar needs eval; nothing inline, no CDN');
        t_eq(["'self'"], $csp['style-src-elem'], 'no <style> elements');
        t_eq(["'unsafe-inline'"], $csp['style-src-attr'], 'style attributes (data-show, UI kit)');
        t_eq(["'self'", 'data:'], $csp['img-src']);
        t_eq(["'self'"], $csp['connect-src']);
        t_eq(["'none'"], $csp['frame-ancestors']);
        t_eq(["'self'"], $csp['base-uri']);
        t_eq(["'self'"], $csp['form-action']);
        t_eq(["'none'"], $csp['object-src']);
        foreach ($csp as $dir => $vals) {
            t_true(!in_array("'unsafe-inline'", $vals, true) || in_array($dir, ['style-src', 'style-src-attr'], true), "$dir must not allow inline");
            foreach ($vals as $v) {
                t_true(!str_contains($v, '://') && $v !== '*', "$dir allows only this origin: $v");
            }
        }
    },
    'apply() sets the headers on pages, events and redirects without touching the body' => function (): void {
        $c = sh_config(Env::Live);
        foreach ([Response::page('<p>x</p>'), Response::events(Toast::ok('x')), Response::redirect('/slash301pm/login'), Response::json(['a' => 1])] as $r) {
            $out = SecurityHeaders::apply($r, $c)->render(Transport::Sse);
            t_eq(SecurityHeaders::CSP, $out->header('Content-Security-Policy'));
            t_eq('DENY', $out->header('X-Frame-Options'));
            t_eq(SecurityHeaders::HSTS, $out->header('Strict-Transport-Security'));
            t_eq($r->render(Transport::Sse)->body(), $out->body());
        }
    },
    'Config.iniSettings: errors never displayed, always logged to data/logs' => function (): void {
        foreach ([Env::Live, Env::Local] as $env) {
            $ini = sh_config($env)->iniSettings('2026-10-10');
            t_eq('0', $ini['display_errors'], $env->value);
            t_eq('0', $ini['display_startup_errors']);
            t_eq('0', $ini['html_errors']);
            t_eq('1', $ini['log_errors']);
            t_eq('/tmp/x/logs/php-2026-10-10.log', $ini['error_log']);
        }
    },
];

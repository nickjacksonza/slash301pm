<?php
declare(strict_types=1);

use App\View\VM\LayoutVM;
use App\View\VM\NavItem;

require_once dirname(__DIR__, 2) . '/support/app.php';

function lt_vm(string $name = 'Ann', bool $demo = false, bool $admin = false, string $csrf = 'tok123'): LayoutVM
{
    app_base_path('/slash301pm');
    $nav = [new NavItem('today', 'Today', url('/today'), true), new NavItem('jobs', 'Jobs', url('/jobs'), false)];
    $adminNav = $admin ? [new NavItem('admin-users', 'Users', url('/admin/users'), true)] : [];
    return new LayoutVM('My <day>', 'today', $name, 'AM', $csrf, $demo, $nav, $adminNav);
}

return [
    'layout escapes text with e()' => function (): void {
        $html = layout_page(lt_vm('<script>alert(1)</script>'), '<p id="c">content</p>');
        t_not_contains('<script>alert(1)</script>', $html);
        t_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        t_contains('<title>My &lt;day&gt; · Slash 301 PM</title>', $html);
        t_contains('<p id="c">content</p>', $html);
    },
    'csrf token enters data-signals through js()' => function (): void {
        $html = layout_page(lt_vm('Ann', false, false, "t'\"<>&"), '');
        preg_match('/data-signals="([^"]*)"/', $html, $m);
        t_true(isset($m[1]), 'body has data-signals');
        $decoded = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        t_eq(['_csrf' => "t'\"<>&", '_net_error' => 0], $decoded);
        t_not_contains("t'", $m[1]);
    },
    'datastar is a module script with data-cfasync=false, versioned by hash' => function (): void {
        $html = layout_page(lt_vm(), '');
        t_true(preg_match('#<script type="module" data-cfasync="false" src="/slash301pm/public/js/datastar\.js\?v=[0-9a-f]{10}"></script>#', $html) === 1, 'datastar script tag');
        t_contains('href="/slash301pm/public/css/app.css?v=', $html);
        t_contains('<div id="toasts"', $html);
        t_contains('<div id="sheet"></div>', $html);
        t_contains('data-theme-toggle', $html);
    },
    'demo banner and admin nav only when on' => function (): void {
        t_not_contains('Demo mode is on', layout_page(lt_vm(), ''));
        t_contains('Demo mode is on', layout_page(lt_vm('Ann', true), ''));
        t_not_contains('/slash301pm/admin/users', layout_page(lt_vm(), ''));
        t_contains('/slash301pm/admin/users', layout_page(lt_vm('Ann', false, true), ''));
    },
    'act() builds an escaped action with the CSRF header' => function (): void {
        $a = act('post', "/slash301pm/x?a='b'", 'filterSignals: {include: /^pw\\./}');
        $decoded = html_entity_decode($a, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        t_eq("@post(\"/slash301pm/x?a=\\u0027b\\u0027\", {headers: {'X-CSRF-Token': \$_csrf}, filterSignals: {include: /^pw\\./}})", $decoded);
        t_not_contains('"', $a);
        t_throws(static fn () => act('query', '/x'), InvalidArgumentException::class);
    },
    'toast partial escapes the message' => function (): void {
        $t = partial_toast('error', '<img src=x onerror=alert(1)>');
        t_not_contains('<img', $t);
        t_contains('role="alert"', $t);
        t_contains('data-init__delay.4s="el.remove()"', $t);
    },
    'admin user row: names go through js() inside confirm()' => function (): void {
        $row = partial_admin_user_row(new App\View\VM\UserRowVM('u1', "O'Brien \"x\" </script>", 'ob', '', 'AM', true, false));
        t_not_contains('</script>', $row);
        t_not_contains("O'Brien", $row);
        t_contains('id="user-row-u1"', $row);
    },
];

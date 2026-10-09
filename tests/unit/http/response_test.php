<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Http\PatchElements;
use App\Http\PatchMode;
use App\Http\PatchSignals;
use App\Http\Redirect;
use App\Http\Response;
use App\Http\Toast;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'sse: patch-elements wire format for every mode' => function (): void {
        $cases = [
            [PatchElements::html('<div id="a">x</div>'), "event: datastar-patch-elements\ndata: elements <div id=\"a\">x</div>\n\n"],
            [new PatchElements('<div id="a">x</div>', null, PatchMode::Replace), "event: datastar-patch-elements\ndata: mode replace\ndata: elements <div id=\"a\">x</div>\n\n"],
            [PatchElements::into('#list', '<li>1</li>', PatchMode::Append), "event: datastar-patch-elements\ndata: selector #list\ndata: mode append\ndata: elements <li>1</li>\n\n"],
            [PatchElements::into('#t', '<b>i</b>'), "event: datastar-patch-elements\ndata: selector #t\ndata: mode inner\ndata: elements <b>i</b>\n\n"],
            [PatchElements::into('#t', '<p>p</p>', PatchMode::Prepend), "event: datastar-patch-elements\ndata: selector #t\ndata: mode prepend\ndata: elements <p>p</p>\n\n"],
            [PatchElements::into('#t', '<p>b</p>', PatchMode::Before), "event: datastar-patch-elements\ndata: selector #t\ndata: mode before\ndata: elements <p>b</p>\n\n"],
            [PatchElements::into('#t', '<p>a</p>', PatchMode::After), "event: datastar-patch-elements\ndata: selector #t\ndata: mode after\ndata: elements <p>a</p>\n\n"],
            [PatchElements::remove('#row-12'), "event: datastar-patch-elements\ndata: selector #row-12\ndata: mode remove\n\n"],
            [PatchElements::html("<ul id=\"u\">\r\n<li>1</li>\n</ul>"), "event: datastar-patch-elements\ndata: elements <ul id=\"u\">\ndata: elements <li>1</li>\ndata: elements </ul>\n\n"],
        ];
        foreach ($cases as $i => [$ev, $want]) {
            t_eq($want, ts_body(Response::events($ev)), "case $i");
        }
    },
    'sse: signals, toast and redirect' => function (): void {
        t_eq("event: datastar-patch-signals\ndata: signals {\"brief\":{\"saved_at\":\"12:03\"}}\n\n", ts_body(Response::events(new PatchSignals(['brief' => ['saved_at' => '12:03']]))));
        t_eq("event: datastar-patch-signals\ndata: signals {}\n\n", ts_body(Response::events(new PatchSignals([]))));
        t_eq("event: datastar-patch-signals\ndata: onlyIfMissing true\ndata: signals {\"a\":1}\n\n", ts_body(Response::events(new PatchSignals(['a' => 1], true))));
        $toast = ts_body(Response::events(Toast::error('Bad <input> & "quotes"')));
        t_contains("data: selector #toasts\ndata: mode append\ndata: elements <div", $toast);
        t_contains('Bad &lt;input&gt; &amp; &quot;quotes&quot;', $toast);
        t_not_contains('<input>', $toast);
        $redir = ts_body(Response::events(new Redirect('/slash301pm/login?a=1&b="x"')));
        t_contains("data: selector body\ndata: mode append\n", $redir);
        t_contains('window.location.assign("/slash301pm/login?a=1\u0026b=\u0022x\u0022")', $redir);
    },
    'sse: headers and status' => function (): void {
        $r = Response::events(Toast::ok('x'))->render(Transport::Sse);
        t_eq(200, $r->status);
        t_eq('text/event-stream', $r->header('Content-Type'));
        t_eq('no-cache', $r->header('Cache-Control'));
        t_eq('no', $r->header('X-Accel-Buffering'));
        t_eq('nosniff', $r->header('X-Content-Type-Options'));
        t_eq(2, count(Response::events(Toast::ok('a'), Toast::ok('b'))->render(Transport::Sse)->chunks));
    },
    'html transport: one patch with selector and mode headers' => function (): void {
        $r = Response::events(PatchElements::into('#list', '<li>1</li>', PatchMode::Append), PatchElements::into('#list', '<li>2</li>', PatchMode::Append))->render(Transport::Html);
        t_eq(200, $r->status);
        t_eq('text/html; charset=utf-8', $r->header('Content-Type'));
        t_eq('#list', $r->header('Datastar-Selector'));
        t_eq('append', $r->header('Datastar-Mode'));
        t_eq('<li>1</li><li>2</li>', $r->body());
        $outer = Response::events(PatchElements::html('<tr id="row-1"></tr>'))->render(Transport::Html);
        t_eq('', $outer->header('Datastar-Selector'));
        t_eq('', $outer->header('Datastar-Mode'));
    },
    'html transport: toast replaces the #toasts region; row plus toast is one outer patch' => function (): void {
        $r = Response::events(Toast::ok('Saved'))->render(Transport::Html);
        t_contains('<div id="toasts"', $r->body());
        t_contains('Saved', $r->body());
        t_eq('', $r->header('Datastar-Mode'));
        $both = Response::events(PatchElements::html('<tr id="row-1"></tr>'), Toast::ok('Saved'))->render(Transport::Html);
        t_contains('<tr id="row-1"></tr><div id="toasts"', $both->body());
    },
    'html transport: signals as JSON, redirect as javascript' => function (): void {
        $s = Response::events(new PatchSignals(['a' => ['b' => 1]], true))->render(Transport::Html);
        t_eq('application/json; charset=utf-8', $s->header('Content-Type'));
        t_eq('{"a":{"b":1}}', $s->body());
        t_eq('true', $s->header('Datastar-Only-If-Missing'));
        $j = Response::events(new Redirect('/slash301pm/login'))->render(Transport::Html);
        t_eq('text/javascript; charset=utf-8', $j->header('Content-Type'));
        t_eq('window.location.assign("/slash301pm/login")', $j->body());
    },
    'html transport: mixed lists throw LogicException' => function (): void {
        $mixed = [
            [PatchElements::html('<div id="a"></div>'), new PatchSignals(['x' => 1])],
            [PatchElements::into('#a', '<i></i>'), PatchElements::into('#b', '<i></i>')],
            [PatchElements::into('#a', '<i></i>', PatchMode::Inner), PatchElements::into('#a', '<i></i>', PatchMode::Append)],
            [PatchElements::into('#list', '<li></li>', PatchMode::Append), Toast::ok('x')],
            [new Redirect('/a'), Toast::ok('x')],
            [new PatchSignals(['a' => 1]), new PatchSignals(['b' => 1])],
        ];
        foreach ($mixed as $events) {
            t_throws(static fn () => Response::events(...$events)->render(Transport::Html), LogicException::class);
        }
        t_eq(204, Response::events()->render(Transport::Html)->status);
    },
    'withTransport overrides the config' => function (): void {
        $r = Response::events(PatchElements::html('<div id="a"></div>'))->withTransport(Transport::Html)->render(Transport::Sse);
        t_eq('text/html; charset=utf-8', $r->header('Content-Type'));
    },
    'pages, redirects and json' => function (): void {
        $p = Response::page('<p>x</p>', 201)->render(Transport::Sse);
        t_eq(201, $p->status);
        t_eq('text/html; charset=utf-8', $p->header('Content-Type'));
        t_eq('no-store', $p->header('Cache-Control'));
        $r = Response::redirect('/slash301pm/today')->render(Transport::Sse);
        t_eq(303, $r->status);
        t_eq('/slash301pm/today', $r->header('Location'));
        $nav = Response::navigate(true, '/slash301pm/login');
        t_eq(200, $nav->status());
        t_true($nav->eventList()[0] instanceof Redirect);
        $j = Response::json(['ok' => true, 'x' => '<b>'])->render(Transport::Sse);
        t_eq(['ok' => true, 'x' => '<b>'], json_decode($j->body(), true));
        t_not_contains('<b>', $j->body());
        t_eq(404, Response::notFound()->status());
        t_eq(403, Response::forbidden()->status());
    },
    'PatchElements without a selector only allows outer and replace' => function (): void {
        t_throws(static fn () => new PatchElements('<i></i>', null, PatchMode::Append), InvalidArgumentException::class);
        t_throws(static fn () => new PatchElements('', null, PatchMode::Remove), InvalidArgumentException::class);
    },
];

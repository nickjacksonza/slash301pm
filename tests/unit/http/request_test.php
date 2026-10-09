<?php
declare(strict_types=1);

use App\Http\Request;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'relative path strips the base path' => function (): void {
        $cases = [
            ['/slash301pm/jobs/12', '/jobs/12'],
            ['/slash301pm', '/'],
            ['/slash301pm/', '/'],
            ['/slash301pmx/jobs', '/slash301pmx/jobs'],
            ['/other', '/other'],
        ];
        foreach ($cases as [$in, $want]) {
            t_eq($want, Request::relativePath($in, '/slash301pm'), $in);
        }
    },
    'signals come from ?datastar= on GET and DELETE, the JSON body otherwise' => function (): void {
        $sig = ['brief' => ['due_date' => '2026-10-09', 'n' => 3], 'filters' => ['q' => 'a&b <c>']];
        $json = json_encode($sig, JSON_THROW_ON_ERROR);
        $cases = [
            ['GET', ['datastar' => $json], '', $sig],
            ['DELETE', ['datastar' => $json], '', $sig],
            ['POST', [], $json, $sig],
            ['PUT', [], $json, $sig],
            ['PATCH', [], $json, $sig],
            ['GET', [], '', []],
            ['GET', ['datastar' => 'not json'], '', []],
            ['POST', [], '[1,2,3]', []],          // a list is not a signal tree
            ['POST', [], '"str"', []],
            ['POST', ['datastar' => $json], '', []], // POST ignores the query string
        ];
        foreach ($cases as [$method, $query, $body, $want]) {
            $r = new Request($method, '/x', $query, ['content-type' => 'application/json'], $body, [], '127.0.0.1', 'http');
            t_eq($want, $r->signals(), "$method signals");
        }
    },
    'form posts carry no signals' => function (): void {
        $r = new Request('POST', '/login', [], ['content-type' => 'application/x-www-form-urlencoded'], 'username=a', ['username' => 'a'], '127.0.0.1', 'http');
        t_eq([], $r->signals());
        t_eq('a', $r->form('username'));
        t_true($r->isFormPost());
    },
    'headers are case-insensitive and isDatastar needs the exact header' => function (): void {
        $r = new Request('GET', '/x', [], ['datastar-request' => 'true', 'x-csrf-token' => 'abc'], '', [], '127.0.0.1', 'http');
        t_true($r->isDatastar());
        t_eq('abc', $r->header('X-CSRF-Token'));
        $plain = new Request('GET', '/x', [], ['datastar-request' => 'yes'], '', [], '127.0.0.1', 'http');
        t_true(!$plain->isDatastar());
    },
    'query and form values that are arrays read as empty strings' => function (): void {
        $r = new Request('POST', '/x', ['a' => ['x']], [], '', ['b' => ['y']], '127.0.0.1', 'http');
        t_eq('', $r->query('a'));
        t_eq('', $r->form('b'));
    },
    'withPathValues keeps everything else' => function (): void {
        $r = (new Request('GET', '/jobs/1', [], ['host' => 'h'], '', [], '1.2.3.4', 'https'))->withPathValues(['id' => '1']);
        t_eq('1', $r->pathValue('id'));
        t_eq('h', $r->host());
        t_eq('', $r->pathValue('missing'));
    },
    'client ip trusts CF-Connecting-IP only from Cloudflare' => function (): void {
        $cf = new Request('GET', '/', [], ['cf-connecting-ip' => '41.1.2.3'], '', [], '162.158.1.1', 'https');
        t_eq('41.1.2.3', $cf->clientIp());
        $spoof = new Request('GET', '/', [], ['cf-connecting-ip' => '41.1.2.3'], '', [], '8.8.8.8', 'https');
        t_eq('8.8.8.8', $spoof->clientIp());
        $v6 = new Request('GET', '/', [], ['cf-connecting-ip' => '2001:db8::1'], '', [], '2606:4700::1', 'https');
        t_eq('2001:db8::1', $v6->clientIp());
    },
];

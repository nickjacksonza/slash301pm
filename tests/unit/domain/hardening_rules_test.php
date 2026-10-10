<?php
declare(strict_types=1);

use App\Domain\BetaChecklist;
use App\Domain\Links;
use App\Domain\RateLimit;
use App\Domain\Types\SeedPasswordReport;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'RateLimit.decide: allowed under the limit, retry when the oldest hit leaves the window' => function (): void {
        $now = 1_000_000;
        // [hits in window, oldest, allowed, retryAfter]
        $cases = [
            [0, null, true, 0],
            [119, $now - 30, true, 0],
            [120, $now - 30, false, 30],
            [120, $now - 59, false, 1],
            [120, $now - 60, false, 1],
            [125, $now, false, 60],
            [120, null, false, 60],
        ];
        foreach ($cases as [$hits, $oldest, $allowed, $retry]) {
            $dec = RateLimit::decide($hits, $oldest, $now, 120, 60);
            t_eq($allowed, $dec->allowed, "hits $hits");
            t_eq($retry, $dec->retryAfter, "retry for $hits");
        }
    },
    'RateLimit.key: user id when signed in, else the IP; writes are POST PUT PATCH DELETE' => function (): void {
        t_eq('user:abc', RateLimit::key('abc', '1.2.3.4'));
        t_eq('ip:1.2.3.4', RateLimit::key(null, '1.2.3.4'));
        t_eq('ip:1.2.3.4', RateLimit::key('', '1.2.3.4'));
        t_eq('ip:unknown', RateLimit::key(null, ''));
        foreach (['POST' => true, 'put' => true, 'PATCH' => true, 'DELETE' => true, 'GET' => false, 'HEAD' => false, 'OPTIONS' => false] as $m => $want) {
            t_eq($want, RateLimit::isWrite($m), $m);
        }
        t_eq('Too many changes in a short time. Wait 1 second and try again.', RateLimit::message(1));
        t_contains('Wait 30 seconds', RateLimit::message(30));
    },
    'Links: web links are http(s) with a host; javascript: and friends are refused' => function (): void {
        $cases = [
            ['https://example.com/a?b=1#c', true, true],
            ['http://intranet:8080/x', true, true],
            ['HTTPS://Example.com', true, true],
            ['https://', false, false],
            ['https:///path', false, false],
            ['javascript:alert(1)', false, false],
            ['JavaScript:alert(1)', false, false],
            [' javascript:alert(1)', false, false],
            ['java&#115;cript:alert(1)', false, false],
            ['data:text/html,<script>alert(1)</script>', false, false],
            ['vbscript:msgbox(1)', false, false],
            ['file:///etc/passwd', false, false],
            ['https://example.com/"onmouseover="x', false, false],
            ['https://example.com/<x>', false, false],
            ["https://example.com/\nx", false, false],
            ['https://exa mple.com', false, false],
            ['https://example.com\\@evil.com', false, false],
            ['//evil.com/x', false, false],
            ['smb://fileserver/Clients/Meridian', false, true],
            ['afp://mac-server/Jobs', false, true],
            ['\\\\fileserver\\Clients\\Meridian', false, true],
            ['X:\\Clients\\Meridian', false, true],
            ['/Volumes/Clients/Meridian', false, true],
            ['smb:fileserver', false, false],
            ['ftp://files.example.com/x', false, false],
            ['Clients folder on the server', false, false],
            ['<script>"\' & {{x}} </style><img src=x onerror=1>', false, false],
            [str_repeat('a', 10) . 'https://x.com', false, false],
            ['https://x.com/' . str_repeat('a', 2000), false, false],
        ];
        foreach ($cases as [$v, $web, $server]) {
            t_eq($web, Links::isWebUrl($v), "web: $v");
            t_eq($web, Links::isLinkable($v), "linkable: $v");
            t_eq($server, Links::isServerLink($v), "server: $v");
        }
    },
    'brief_link: only web links become <a>; share paths and javascript: are text' => function (): void {
        t_contains('<a href="https://example.com/a?b=1&amp;c=2"', brief_link('https://example.com/a?b=1&c=2', 'Mood'));
        t_eq('javascript:alert(1)', brief_link('javascript:alert(1)'));
        t_eq('Click', brief_link('javascript:alert(1)', 'Click'));
        t_eq('\\\\fileserver\\Clients', brief_link('\\\\fileserver\\Clients'));
        t_eq('&lt;img src=x onerror=1&gt;', brief_link('<img src=x onerror=1>'));
    },
    'BetaChecklist: every computed item, met and unmet, with the owner actions flagged' => function (): void {
        $clean = new SeedPasswordReport([], 0);
        $ok = BetaChecklist::items(false, $clean, 1, false, 0, 0, 3, true);
        t_eq(6, count($ok));
        t_eq([], BetaChecklist::unmet($ok));
        // [args, unmet keys]
        $cases = [
            [[true, $clean, 1, false, 0, 0, 1, true], [BetaChecklist::DEMO_OFF]],
            [[false, new SeedPasswordReport(['a', 'b'], 0), 1, false, 0, 0, 1, true], [BetaChecklist::SEED_PASSWORDS]],
            [[false, new SeedPasswordReport([], 4), 1, false, 0, 0, 1, true], [BetaChecklist::SEED_PASSWORDS]],
            [[false, $clean, 0, false, 0, 0, 1, true], [BetaChecklist::AM_USER]],
            [[false, $clean, 1, true, 0, 0, 1, true], [BetaChecklist::MIGRATIONS]],
            [[false, $clean, 1, false, 2, 0, 1, true], [BetaChecklist::MIGRATIONS]],
            [[false, $clean, 1, false, 0, 1, 1, true], [BetaChecklist::MIGRATIONS]],
            [[false, $clean, 1, false, 0, 0, 0, true], [BetaChecklist::BACKUP]],
            [[false, $clean, 1, false, 0, 0, 1, false], [BetaChecklist::DATASTAR_PIN]],
            [[true, new SeedPasswordReport(['x'], 0), 0, true, 1, 0, 0, false], [BetaChecklist::DEMO_OFF, BetaChecklist::SEED_PASSWORDS, BetaChecklist::AM_USER, BetaChecklist::MIGRATIONS, BetaChecklist::BACKUP, BetaChecklist::DATASTAR_PIN]],
        ];
        foreach ($cases as $i => [$args, $want]) {
            $keys = [];
            foreach (BetaChecklist::unmet(BetaChecklist::items(...$args)) as $item) {
                $keys[] = $item->key;
                t_true($item->detail !== '', "case $i: unmet $item->key explains itself");
            }
            t_eq($want, $keys, "case $i");
        }
        $seed = BetaChecklist::unmet(BetaChecklist::items(false, new SeedPasswordReport(['amy', 'ben'], 3), 1, false, 0, 0, 1, true))[0];
        t_contains('2 active user(s) still sign in with the seeded password: amy, ben', $seed->detail);
        t_contains('3 user(s) not checked yet', $seed->detail);
        t_true($seed->ownerAction);
        $many = [];
        for ($i = 0; $i < 20; $i++) {
            $many[] = 'user' . $i;
        }
        t_contains('and 8 more', BetaChecklist::unmet(BetaChecklist::items(false, new SeedPasswordReport($many, 0), 1, false, 0, 0, 1, true))[0]->detail);
    },
];

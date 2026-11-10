<?php
declare(strict_types=1);

use App\Auth\InviteTokens;
use App\Domain\ClientPersona;
use App\Domain\InviteRules;
use App\Domain\Signals\InviteSignals;
use App\Domain\Types\Invite;
use App\Mail\MailMessage;
use App\Mail\MemoryMailer;

/** A stored invite for $token; $o overrides by constructor name. */
function irt_invite(string $token, array $o = []): Invite
{
    $d = ['id' => 'i1', 'userId' => 'u1', 'tokenHash' => InviteRules::hash($token), 'expiresAt' => '2026-10-16 07:00:00', 'usedAt' => null,
        'userName' => 'Sophie', 'username' => 'sophie@example.com', 'brandId' => 'b1', 'brandName' => 'Mercedes', 'persona' => ClientPersona::BrandDirector];
    return new Invite(...array_merge($d, $o));
}

return [
    'invite tokens: 43 base64url characters, never the same twice, shape check' => function (): void {
        $a = InviteTokens::generate();
        $b = InviteTokens::generate();
        t_eq(43, strlen($a));
        t_true($a !== $b);
        t_true(InviteRules::tokenShapeOk($a));
        foreach (['', 'short', str_repeat('a', 42), str_repeat('a', 44), str_repeat('a', 42) . '/', str_repeat('a', 42) . '=', str_repeat('a', 42) . "\n"] as $bad) {
            t_true(!InviteRules::tokenShapeOk($bad), 'bad shape: ' . json_encode($bad));
        }
        t_eq(64, strlen(InviteRules::hash($a)));
        t_true(InviteRules::hash($a) !== $a, 'only the hash is stored');
    },
    'invite usable: right token, unused, unexpired only (UTC compare)' => function (): void {
        $t = InviteTokens::generate();
        $now = new DateTimeImmutable('2026-10-09 09:00:00', new DateTimeZone('Africa/Johannesburg'));   // 07:00 UTC
        $cases = [
            'valid' => [irt_invite($t), $t, true],
            'null invite' => [null, $t, false],
            'other token' => [irt_invite($t), InviteTokens::generate(), false],
            'malformed token' => [irt_invite($t), 'nope', false],
            'used' => [irt_invite($t, ['usedAt' => '2026-10-09 06:00:00']), $t, false],
            'expired a second ago' => [irt_invite($t, ['expiresAt' => '2026-10-09 06:59:59']), $t, false],
            'expires exactly now' => [irt_invite($t, ['expiresAt' => '2026-10-09 07:00:00']), $t, false],
            'expires in a second' => [irt_invite($t, ['expiresAt' => '2026-10-09 07:00:01']), $t, true],
        ];
        foreach ($cases as $name => [$inv, $tok, $want]) {
            t_eq($want, InviteRules::usable($inv, $tok, $now), $name);
        }
    },
    'invite expiry is 7 days later in UTC' => function (): void {
        $now = new DateTimeImmutable('2026-10-09 09:00:00', new DateTimeZone('Africa/Johannesburg'));
        t_eq('2026-10-16 07:00:00', InviteRules::expiresAt($now)->format('Y-m-d H:i:s'));
    },
    'invite link uses the configured base URL; paths are redacted for logs' => function (): void {
        t_eq('https://projects.slash301.com/slash301pm/invite/abc', InviteRules::link('https://projects.slash301.com/slash301pm/', 'abc'));
        $cases = [
            '/invite/' . str_repeat('A', 43) => '/invite/[token]',
            '/invite/abc/def' => '/invite/[token]/def',
            '/invite/' => '/invite/[token]',
            '/invite' => '/invite',
            '/portal/jobs/1' => '/portal/jobs/1',
            '/x/invite/abc' => '/x/invite/abc',
        ];
        foreach ($cases as $in => $want) {
            t_eq($want, InviteRules::redactPath($in), $in);
        }
    },
    'invite form validation: name, email and persona' => function (): void {
        $cases = [
            'ok' => ['Sophie Client', 'sophie@example.com', 'brand_director', []],
            'ok mm' => ['Sam', 'sam@example.co.za', 'marketing_manager', []],
            'short name' => ['S', 'sophie@example.com', 'brand_director', ['name']],
            'long name' => [str_repeat('a', 101), 'sophie@example.com', 'brand_director', ['name']],
            'control in name' => ["Sophie\nBcc: x", 'sophie@example.com', 'brand_director', ['name']],
            'bad email' => ['Sophie', 'not-an-email', 'brand_director', ['email']],
            'header injection' => ['Sophie', "sophie@example.com\r\nBcc: evil@example.com", 'brand_director', ['email']],
            'long email' => ['Sophie', str_repeat('a', 195) . '@x.com', 'brand_director', ['email']],
            'bad persona' => ['Sophie', 'sophie@example.com', 'ceo', ['persona']],
            'everything wrong' => ['', '', '', ['name', 'email', 'persona']],
        ];
        foreach ($cases as $name => [$n, $em, $p, $fields]) {
            $e = InviteRules::validateNew($n, $em, $p);
            $got = [];
            foreach (['name', 'email', 'persona'] as $f) {
                if (isset($e->errors[$f])) {
                    $got[] = $f;
                }
            }
            t_eq($fields, $got, $name);
        }
    },
    'invite signals: trimmed, email lower-cased, wrong types become empty' => function (): void {
        $s = InviteSignals::fromSignals(['inv' => ['name' => '  Sophie ', 'email' => ' Sophie@Example.COM ', 'persona' => 'brand_director']]);
        t_eq('Sophie', $s->name);
        t_eq('sophie@example.com', $s->email);
        t_true($s->validate()->isEmpty());
        $bad = InviteSignals::fromSignals(['inv' => ['name' => ['x'], 'email' => 5, 'persona' => null]]);
        t_eq(['', '', ''], [$bad->name, $bad->email, $bad->persona]);
        t_eq('', InviteSignals::fromSignals([])->name);
    },
    'invite email: one-line names, link and expiry in SAST; mail headers are checked' => function (): void {
        $exp = new DateTimeImmutable('2026-10-16 07:00:00', new DateTimeZone('UTC'));
        $body = InviteRules::emailBody("Sophie\nBcc: x", 'Merc', 'Amy', 'https://x.example/invite/abc', $exp);
        t_contains('Hello Sophie Bcc: x,', $body);
        t_contains('https://x.example/invite/abc', $body);
        t_contains('16 October 2026, 09:00 SAST', $body);
        t_eq('Your Slash 301 client portal invite for Merc', InviteRules::emailSubject('Merc'));
        t_eq('Your Slash 301 client portal invite for M x', InviteRules::emailSubject("M\r\nx"));
        $m = new MemoryMailer();
        t_true($m->send(new MailMessage('from@example.com', 'to@example.com', 'Hi', 'Body')));
        t_true(!$m->send(new MailMessage('from@example.com', "to@example.com\r\nBcc: e@example.com", 'Hi', 'Body')));
        t_true(!$m->send(new MailMessage('from@example.com', 'to@example.com', "Hi\nBcc: e@example.com", 'Body')));
        t_eq(1, count($m->sent));
        $failing = new MemoryMailer(true);
        t_true(!$failing->send(new MailMessage('from@example.com', 'to@example.com', 'Hi', 'Body')));
    },
    'client persona maps users.client_signoff; NULL reads as Brand Director' => function (): void {
        t_eq(ClientPersona::MarketingManager, ClientPersona::fromSignoff(0));
        t_eq(ClientPersona::BrandDirector, ClientPersona::fromSignoff(1));
        t_eq(ClientPersona::BrandDirector, ClientPersona::fromSignoff(null));
        t_eq(0, ClientPersona::MarketingManager->signoff());
        t_eq(1, ClientPersona::BrandDirector->signoff());
    },
];

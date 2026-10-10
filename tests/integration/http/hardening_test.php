<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Config\Transport;
use App\Domain\RateLimit;
use App\Domain\Role;
use App\Http\ErrorResponse;
use App\Logs\AppLog;
use App\Store\SeedPasswordAudit;

require_once dirname(__DIR__, 2) . '/support/brief_http.php';

return [
    'rate limit: the 121st write in a minute is refused (Datastar 200 + toast, form 429), reads are not counted, the window slides' => function (): void {
        $d = ts_deps();
        $p = bd_seed_people($d);
        $s = bh_session($d, $p['am']);
        $app = ts_app($s);
        for ($i = 0; $i < RateLimit::WRITES_PER_WINDOW; $i++) {
            $ok = $app(ts_ds_request('POST', '/today/seen', $s), $d);
            t_eq(200, $ok->status());
            t_not_contains('Too many changes', ts_body($ok), "write $i");
        }
        t_eq(RateLimit::WRITES_PER_WINDOW, $d->rateLimits->count(RateLimit::BUCKET_WRITES, 'user:' . $p['am']));
        // reads are never limited
        t_eq(200, $app(ts_request('GET', '/today'), $d)->status());
        $over = $app(ts_ds_request('POST', '/today/seen', $s), $d);
        $r = $over->render(Transport::Sse);
        t_eq(200, $r->status, 'Datastar ignores non-200 bodies (ADR 0005)');
        t_contains('Too many changes in a short time. Wait 60 seconds and try again.', $r->body());
        t_eq('60', $r->header('Retry-After'));
        t_eq(200, $over->render(Transport::Html)->status, 'html transport too');
        // refused hits are not stored: the table never grows past the limit
        t_eq(RateLimit::WRITES_PER_WINDOW, $d->rateLimits->count(RateLimit::BUCKET_WRITES, 'user:' . $p['am']));
        // a plain form post gets a real 429 page
        $form = $app(ts_request('POST', '/logout', [], '', ['_csrf' => (string) $s->get('csrf_token')]), $d);
        t_eq(429, $form->status());
        t_eq('60', $form->render(Transport::Sse)->header('Retry-After'));
        t_contains('Slow down', ts_body($form));
        // another person is not affected
        $ben = bh_session($d, $p['am2']);
        t_not_contains('Too many changes', ts_body((ts_app($ben))(ts_ds_request('POST', '/today/seen', $ben), $d)));
        // the window slides: 61 seconds later the user can write again
        $clock = $d->clock;
        t_true($clock instanceof FixedClock);
        $clock->advance(61);
        t_not_contains('Too many changes', ts_body($app(ts_ds_request('POST', '/today/seen', $s), $d)));
        t_eq(1, $d->rateLimits->count(RateLimit::BUCKET_WRITES, 'user:' . $p['am']), 'old hits pruned');
        // about an hour on (inside the 1 hour idle timeout), the first write of a fresh window drops everyone's stale rows
        $clock->advance(3540);
        bh_ds($d, $s, 'POST', '/today/seen');
        t_eq(0, $d->rateLimits->count(RateLimit::BUCKET_WRITES, 'user:' . $p['am2']), 'stale keys dropped');
    },
    'rate limit: signed-out writes are keyed by the client IP' => function (): void {
        $d = ts_deps();
        $anon = new App\Http\MemorySession(['csrf_token' => 'x']);
        $app = ts_app($anon);
        $app(ts_request('POST', '/login', [], '', ['_csrf' => 'x', 'username' => 'nobody', 'password' => 'not the password']), $d);
        t_eq(1, $d->rateLimits->count(RateLimit::BUCKET_WRITES, 'ip:127.0.0.1'));
    },
    'errors: users get a generic message with a request id; the detail goes only to data/logs' => function (): void {
        $page = ErrorResponse::for(false, 'abc123def4567890')->render(Transport::Sse);
        t_eq(500, $page->status);
        t_contains('quote reference abc123def4567890', $page->body());
        t_eq('abc123def4567890', $page->header('X-Request-Id'));
        $ds = ErrorResponse::for(true, 'abc123def4567890')->render(Transport::Sse);
        t_eq(200, $ds->status, 'Datastar: a toast, HTTP 200');
        t_contains('datastar-patch-elements', $ds->body());
        t_contains('abc123def4567890', $ds->body());
        t_eq(1, preg_match('/^[0-9a-f]{16}$/', AppLog::newRequestId()));

        $dir = ts_temp_dir() . '/logs';
        $clock = new FixedClock(new DateTimeImmutable('2026-10-10 09:00:00'));
        $log = new AppLog($dir, $clock);
        $e = new RuntimeException('secret detail /home/x', 0, new LogicException('inner cause'));
        $log->exception('req1', $e, 'POST', "/jobs/1\n2026-10-10 fake entry", 'user9');
        $text = (string) file_get_contents($dir . '/app-2026-10-10.log');
        t_contains('[req1] RuntimeException POST /jobs/1?2026-10-10 fake entry', $text, 'newlines in the request cannot forge an entry');
        $entries = 0;
        foreach (explode("\n", trim($text)) as $l) {
            $entries += preg_match('/^\d{4}-\d{2}-\d{2} /', $l);
        }
        t_eq(1, $entries, 'one entry, every other line indented');
        t_contains('user=user9', $text);
        t_contains('secret detail /home/x', $text);
        t_contains('LogicException: inner cause', $text);
        t_contains('#0 ', $text, 'trace logged');
        t_true(is_file($dir . '/.htaccess'), 'deny file');
        t_contains('Require all denied', (string) file_get_contents($dir . '/.htaccess'));
    },
    'logs: files older than 30 days are deleted, at most once per day' => function (): void {
        $dir = ts_temp_dir() . '/logs';
        mkdir($dir, 0700, true);
        foreach (['app-2026-09-01.log', 'app-2026-09-10.log', 'app-2026-09-11.log', 'php-2026-08-01.log', 'app-2026-10-09.log', 'notes.log', 'app-x.log'] as $f) {
            file_put_contents($dir . '/' . $f, "x\n");
        }
        $clock = new FixedClock(new DateTimeImmutable('2026-10-10 09:00:00'));
        $log = new AppLog($dir, $clock);
        t_eq(2, $log->pruneOncePerDay());
        foreach (['app-2026-09-01.log' => false, 'app-2026-09-10.log' => true, 'php-2026-08-01.log' => false, 'app-2026-09-11.log' => true, 'app-2026-10-09.log' => true, 'notes.log' => true, 'app-x.log' => true] as $f => $kept) {
            t_eq($kept, is_file($dir . '/' . $f), $f);
        }
        file_put_contents($dir . '/app-2026-01-01.log', "x\n");
        t_eq(0, $log->pruneOncePerDay(), 'second run the same day does nothing');
        $log->write('info', 'still writes');
        t_true(is_file($dir . '/app-2026-01-01.log'));
        $clock->advance(86400);
        t_eq(2, $log->pruneOncePerDay(), 'next day prunes again (2026-01-01, and 2026-09-10 is now past 30 days)');
    },
    'index.php: an uncaught error shows no detail and no trace, even with display_errors forced on' => function (): void {
        $data = ts_temp_dir();
        $cmd = 'S301_DB=' . escapeshellarg('/etc/passwd/broken.db') . ' S301_DATA_DIR=' . escapeshellarg($data)
            . ' php -d display_errors=1 -d html_errors=1 -r ' . escapeshellarg('$_SERVER["REQUEST_URI"] = "/slash301pm/today"; $_SERVER["REQUEST_METHOD"] = "GET"; require ' . var_export(dirname(__DIR__, 3) . '/index.php', true) . ';') . ' 2>&1';
        $out = (string) shell_exec($cmd);
        t_contains('Something went wrong on our side', $out);
        t_true(preg_match('/quote reference ([0-9a-f]{16})/', $out, $m) === 1, 'request id shown: ' . substr($out, 0, 300));
        foreach (['/etc/passwd', 'ErrorException', 'mkdir', '#0 ', 'Stack trace', '.php:', 'Warning'] as $leak) {
            t_not_contains($leak, $out, 'response leaks ' . $leak);
        }
        $logs = glob($data . '/logs/app-*.log') ?: [];
        t_eq(1, count($logs), 'one log file');
        $text = (string) file_get_contents($logs[0]);
        t_contains('[' . $m[1] . ']', $text);
        t_contains('mkdir(): File exists', $text, 'the detail is in the log');
        t_contains('Db::open()', $text, 'with the trace');
    },
    'seed password audit: finds active users on the seed password, checks a few per call, caches by hash fingerprint' => function (): void {
        $d = ts_deps();
        $seed = SeedPasswordAudit::SEED_PASSWORD;
        $a = ts_user($d, 'alpha', Role::AM, $seed);
        ts_user($d, 'bravo', Role::COO, 'a different long password');
        ts_user($d, 'charlie', Role::Designer, $seed);
        ts_user($d, 'delta', Role::Designer, $seed, false);   // inactive: cannot sign in, not counted
        $cache = ts_temp_dir() . '/seed-password-check.json';
        $audit = new SeedPasswordAudit($d->db, $cache);
        $first = $audit->check(2);
        t_eq(['alpha'], $first->usernames, 'alpha and bravo checked, charlie not yet');
        t_eq(1, $first->unchecked);
        $second = $audit->check(2);
        t_eq(['alpha', 'charlie'], $second->usernames);
        t_eq(0, $second->unchecked);
        $raw = (string) file_get_contents($cache);
        t_not_contains('$2y$', $raw, 'no hashes in the cache');
        // cached: zero checks allowed and still the full answer
        t_eq(['alpha', 'charlie'], $audit->check(0)->usernames);
        // a reset changes the fingerprint, so alpha is checked again
        $d->users->setPassword($a, password_hash('a brand new long password', PASSWORD_BCRYPT, ['cost' => 4]), null, 'password_reset', $d->clock->now());
        $after = $audit->check(0);
        t_eq(['charlie'], $after->usernames);
        t_eq(1, $after->unchecked);
        t_eq(['charlie'], $audit->check()->usernames);
    },
    'admin/system: the beta gate banner lists the unmet items' => function (): void {
        $d = ts_deps(true);
        $coo = bh_session($d, ts_user($d, 'boss', Role::COO, SeedPasswordAudit::SEED_PASSWORD));
        $html = ts_body((ts_app($coo))(ts_request('GET', '/admin/system'), $d));
        t_contains('id="beta-gate"', $html);
        t_contains('data-gate-item="demo_off"', $html);
        t_contains('data-gate-item="seed_passwords"', $html);
        t_contains('still sign in with the seeded password: boss', $html);
        t_contains('data-gate-item="am_user"', $html);
        t_not_contains('data-gate-item="migrations"', $html);
        t_not_contains('data-gate-item="backup"', $html);
        t_contains('Demo mode is on.', $html, 'the shell banner shows on every page while demo mode is on');
        // all met
        $d2 = ts_deps();
        ts_user($d2, 'amy', Role::AM);
        $coo2 = bh_session($d2, ts_user($d2, 'boss', Role::COO));
        $ok = ts_body((ts_app($coo2))(ts_request('GET', '/admin/system'), $d2));
        t_contains('Beta gate: every check the app can make passes', $ok);
        t_not_contains('data-gate-item=', $ok);
        t_not_contains('Demo mode is on.', $ok);
    },
];

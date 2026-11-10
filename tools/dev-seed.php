<?php
declare(strict_types=1);

/**
 * Build a small demo database for local runs and screenshots. Never run on
 * the server (tools/ is denied by .htaccess and is not part of the upload).
 *
 *   php tools/dev-seed.php /tmp/s301demo
 *   S301_DATA_DIR=/tmp/s301demo S301_DB=/tmp/s301demo/demo.db php -S 127.0.0.1:8301 tools/dev-router.php
 *
 * The directory must not hold a database yet and must be outside the repo.
 * Every user gets the password printed at the end. The world: two brands,
 * three campaigns, nine legacy-style jobs at mixed stages, and four briefed
 * jobs: one in progress with work handed in, one in review, one approved
 * internally and ready for the client, one approved by the client with its
 * Social records.
 */

use App\Clock\FixedClock;
use App\Config\Config;
use App\Config\Env;
use App\Config\Transport;
use App\Domain\Role;
use App\Http\Deps;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!class_exists('TestFailure')) {
    final class TestFailure extends RuntimeException {}
}
$root = dirname(__DIR__);
foreach (['app', 'brief_db', 'brief_fx', 'brief_http', 'jobs_fx', 'social_fx', 'review_fx'] as $f) {
    require_once $root . '/tests/support/' . $f . '.php';
}

/** Stop with the toast text when a seeding request is refused. */
function seed_ok(\App\Http\Response $r): void
{
    $body = ts_body($r);
    if (preg_match('/data-toast="error"[^>]*>(?:<span[^>]*>[^<]*<\/span>)?([^<]*)/', $body, $m) === 1) {
        fwrite(STDERR, 'seed request refused: ' . html_entity_decode($m[1]) . "\n");
        exit(1);
    }
}

$dir = rtrim((string) ($argv[1] ?? ''), '/');
if ($dir === '' || str_starts_with((string) realpath(dirname($dir)) . '/', $root . '/') || $dir === $root) {
    fwrite(STDERR, "usage: php tools/dev-seed.php <new data dir outside the repo>\n");
    exit(2);
}
$dbPath = $dir . '/demo.db';
if (file_exists($dbPath)) {
    fwrite(STDERR, "refusing to overwrite $dbPath\n");
    exit(2);
}
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "cannot create $dir\n");
    exit(2);
}

app_base_path('/slash301pm');
$config = new Config(Env::Local, $root, $dir, $dbPath, '/slash301pm', 'projects.slash301.com', '', false, Transport::Sse, Config::defaultNewUiRoles());
// The brief fixtures carry October 2026 dates, so the seed runs on a fixed clock like the tests do.
$d = Deps::build($config, new FixedClock(new DateTimeImmutable('2026-10-09 09:00:00')), 'auto', new App\Mail\MemoryMailer());
$res = $d->migrator->run();
if (!$res->ok) {
    fwrite(STDERR, 'migration failed: ' . $res->error . "\n");
    exit(1);
}

// Brands, campaigns, people.
$m = bd_seed_campaign($d);
$a = bd_seed_campaign($d, 'AURA', 'Aura Spa', 'Winter Glow');
$d->db->exec("INSERT INTO campaigns (id, brand_id, name, status) VALUES ('camp_merc2', 'brand_merc', 'Summer Menu', 'active')");
$p = bd_seed_people($d);
foreach (['ecd' => ['ecd_dominic', Role::ECD], 'cd2' => ['cd_james', Role::CD], 'producer' => ['prod_elena', Role::Producer],
    'social' => ['social_sol', Role::Social], 'pm' => ['pm_pat', Role::PM]] as $k => [$name, $role]) {
    $p[$k] = ts_user($d, $name, $role);
}
$hash = password_hash('correct horse battery', PASSWORD_BCRYPT, ['cost' => 4]);
$p['client'] = $d->users->create('client_sophie', $hash, 'Sophie Laurent', null, Role::Client, '#c9a86c', $m['brand'], null, $d->clock->now());
$names = ['am_amy' => 'Amy Naidoo', 'am_ben' => 'Ben Fourie', 'traffic_morgan' => 'Morgan Dube', 'traffic_ryan' => 'Ryan Peters',
    'design_kim' => 'Kim Abrahams', 'copy_alex' => 'Alex Mokoena', 'cd_isabelle' => 'Isabelle Roux', 'coo_priya' => 'Priya Govender',
    'ecd_dominic' => 'Dominic Hart', 'cd_james' => 'James Khumalo', 'prod_elena' => 'Elena Costa', 'social_sol' => 'Sol Jacobs', 'pm_pat' => 'Pat Venter'];
foreach ($names as $u => $n) {
    $d->db->exec('UPDATE users SET name = :n WHERE username = :u', ['n' => $n, 'u' => $u]);
}

// Legacy-style jobs at mixed stages (dates around today).
$today = new DateTimeImmutable('today');
$day = static fn (int $offset): string => $today->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
$c = ['merc' => $m['campaign'], 'merc2' => 'camp_merc2', 'aura' => $a['campaign']];
jf_job($d, 'MERC-001', $c['merc'], 'Opening poster', ['due' => $day(-4), 'slots' => ['AM' => $p['am'], 'Traffic' => $p['traffic']], 'hours' => 12.5]);
jf_job($d, 'MERC-002', $c['merc'], 'Launch email 50% off', ['due' => $day(0), 'slots' => ['AM' => $p['am']]]);
jf_job($d, 'MERC-003', $c['merc2'], 'Menu cards', ['status' => 'Inbox', 'due' => $day(2), 'created_by' => $p['am']]);
jf_job($d, 'MERC-004', $c['merc2'], 'Summer social', ['due' => $day(11), 'slots' => ['AM' => $p['am2'], 'Traffic' => $p['traffic2']]]);
jf_job($d, 'AURA-001', $c['aura'], 'Spa brochure', ['status' => 'Waiting']);
jf_job($d, 'AURA-002', $c['aura'], 'Glow video', ['status' => 'In Review', 'due' => $day(6), 'slots' => ['Traffic' => $p['traffic']]]);
jf_job($d, 'AURA-003', $c['aura'], 'Old campaign', ['status' => 'Done', 'due' => $day(-40), 'slots' => ['AM' => $p['am']]]);
jf_job($d, 'AURA-004', $c['aura'], 'Designer only', ['status' => 'To Do', 'due' => $day(3), 'asset' => $p['designer'], 'asset_status' => 'In Progress']);
jf_job($d, 'MERC-005', $c['merc'], 'Paused banner', ['status' => 'On Hold', 'due' => $day(21), 'slots' => ['AM' => $p['am']]]);

// Briefed jobs, driven through the real handlers.
$s = static fn (string $who): \App\Http\MemorySession => bh_session($d, $p[$who]);
$team = static function (string $job) use ($d, $p): void {
    foreach (['CD' => 'cd', 'Copywriter' => 'copy', 'Designer' => 'designer'] as $role => $who) {
        $d->assignments->set($job, Role::from($role), $p[$who], $p['traffic'], $d->clock->now());
    }
    $d->db->exec("INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, 'ECD')", ['id' => bin2hex(random_bytes(16)), 'j' => $job, 'u' => $p['ecd']]);
};
$handIn = static function (string $job, bool $request) use ($d, $s): array {
    $posts = rx_posts($d, $job);
    foreach ($posts as $i => $aid) {
        seed_ok(rx_sub($d, $s('copy'), $job, $aid, ['copy_text' => "Doors open 12 November.\nBook your table for the first week.", 'hashtags' => '#grandopening #london',
            'link_url' => 'https://example.com/open']));
        seed_ok(rx_sub($d, $s('designer'), $job, $aid, ['media_url' => 'https://cdn.example.com/opening-' . ($i + 1) . '.png'], $request));
    }
    return $posts;
};
$approve = static function (string $job, array $posts) use ($d, $s): void {
    foreach ($posts as $aid) {
        seed_ok(rx_rev($d, $s('cd'), $job, $aid, 'approved'));
    }
    seed_ok(rx_job($d, $s('cd'), $job, 'approve'));
    seed_ok(rx_job($d, $s('ecd'), $job, 'ecd-approve'));
};

$j1 = sx_seed($d, $c['merc'], $p['am'], $p['traffic'], 3, 'Grand Opening Teasers');
$team($j1);
$handIn($j1, false);

$j2 = sx_seed($d, $c['merc'], $p['am'], $p['traffic'], 2, 'Grand Opening Posts');
$team($j2);
$handIn($j2, true);

$j3 = sx_seed($d, $c['merc2'], $p['am'], $p['traffic'], 2, 'Summer Menu Launch');
$team($j3);
$approve($j3, $handIn($j3, true));
seed_ok(rx_job($d, $s('am'), $j3, 'ready'));

$j4 = sx_seed($d, $c['merc'], $p['am'], $p['traffic'], 2, 'Opening Week Social');
$team($j4);
$approve($j4, $handIn($j4, true));
$d->db->exec("INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, 'Social')", ['id' => bin2hex(random_bytes(16)), 'j' => $j4, 'u' => $p['social']]);
sx_legacy_approve($d, $j4);
$posts = rx_posts($d, $j4);
$ig = sx_add($d, $s('social'), $posts[0], 'instagram');
sx_add($d, $s('social'), $posts[0], 'facebook');
sx_add($d, $s('social'), $posts[1], 'instagram');
sx_tick_all($d, $s('social'), $ig);

echo "Seeded $dbPath\n";
echo "Jobs: teasers $j1 (in progress), posts $j2 (in review), menu $j3 (ready for client), social $j4 (approved by client)\n";
echo "Users (password: correct horse battery): " . implode(', ', array_merge(array_keys($names), ['client_sophie'])) . "\n";
echo "Run: S301_DATA_DIR=$dir S301_DB=$dbPath php -S 127.0.0.1:8301 tools/dev-router.php\n";

<?php
declare(strict_types=1);

/**
 * Integration tests for the Phase 0 hotfixes in the legacy PHP API
 * (api/api.php, api/auth.php, api/db.php).
 *
 * How it works: copies data/live-copy.db to a temp file (or, when that file is
 * not present, lets api/db.php build an empty schema), adds a small fixture of
 * its own rows (ids start with "t"), starts `php -S` on a random loopback port
 * with S301_DB pointing at the temp copy, and talks to /api/api.php over HTTP
 * with real logins. Nothing touches data/slash301pm.db.
 */

const S301_TEST_PASSWORD = 'Test-Password-123';

/** @return array{port:int, db:SQLite3, path:string, data_dir:string} */
function s301_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }
    $root = dirname(__DIR__, 2);
    $path = sys_get_temp_dir() . '/s301_legacy_' . bin2hex(random_bytes(6)) . '.db';
    $live = $root . '/data/live-copy.db';
    if (is_file($live)) {
        copy($live, $path);
    }

    $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($sock === false) {
        throw new TestFailure("cannot pick a port: $errstr");
    }
    $port = (int) substr(strrchr((string) stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);

    // Isolated data dir (sessions, demo flag) so the test never touches data/
    $dataDir = sys_get_temp_dir() . '/s301_legacy_data_' . bin2hex(random_bytes(6));
    mkdir($dataDir, 0700, true);

    $started = time();
    $proc = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        $root,
        array_merge(getenv(), ['S301_DB' => $path, 'S301_DATA_DIR' => $dataDir])
    );
    if (!is_resource($proc)) {
        throw new TestFailure('could not start php -S');
    }
    register_shutdown_function(static function () use ($proc, $path, $dataDir): void {
        proc_terminate($proc);
        proc_close($proc);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($path . $suffix);
        }
        // Remove the isolated data dir (sessions, demo flag)
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dataDir);
    });

    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $r = @file_get_contents("http://127.0.0.1:$port/api/api.php?action=health");
        if ($r !== false && str_contains($r, '"status":"ok"')) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new TestFailure('php -S did not become ready');
    }

    // The health call created the schema if the DB did not exist. Now add fixture rows.
    $db = new SQLite3($path);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON');
    $hash = password_hash(S301_TEST_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
    $db->exec("INSERT INTO brands (id, name, prefix) VALUES ('tb_a', 'Test Brand A', 'TSA'), ('tb_b', 'Test Brand B', 'TSB')");
    $users = [
        ['tu_client_a', 'Client', 'tb_a', 'client_a@example.test'],
        ['tu_client_a2', 'Client', 'tb_a', 'client_a2@example.test'],
        ['tu_client_b', 'Client', 'tb_b', 'client_b@example.test'],
        ['tu_ecd', 'ECD', null, 'ecd@example.test'],
        ['tu_am', 'AM', null, 'am@example.test'],
        ['tu_designer', 'Designer', null, 'designer@example.test'],
    ];
    foreach ($users as [$id, $role, $brand, $email]) {
        $st = $db->prepare('INSERT INTO users (id, username, password_hash, name, email, role, brand_id) VALUES (:id, :id, :h, :n, :e, :r, :b)');
        $st->bindValue(':id', $id, SQLITE3_TEXT);
        $st->bindValue(':h', $hash, SQLITE3_TEXT);
        $st->bindValue(':n', 'Name ' . $id, SQLITE3_TEXT);
        $st->bindValue(':e', $email, SQLITE3_TEXT);
        $st->bindValue(':r', $role, SQLITE3_TEXT);
        $st->bindValue(':b', $brand, $brand === null ? SQLITE3_NULL : SQLITE3_TEXT);
        $st->execute();
    }
    $db->exec("INSERT INTO campaigns (id, brand_id, name) VALUES ('tc_a', 'tb_a', 'Camp A'), ('tc_b', 'tb_b', 'Camp B')");
    $db->exec("INSERT INTO jobs (id, job_number, campaign_id, title, status) VALUES
        ('tj_a', 'TSA-001', 'tc_a', 'Job A', 'In Progress'),
        ('tj_b', 'TSB-001', 'tc_b', 'Job B', 'In Progress')");
    $db->exec("INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES ('tja_1', 'tj_a', 'tu_designer', 'Designer')");
    $db->exec("INSERT INTO tasks (id, job_id, type, status, assigned_to) VALUES
        ('tt_a', 'tj_a', 'media', 'Not Started', 'tu_designer'),
        ('tt_b', 'tj_b', 'media', 'Not Started', NULL)");
    $db->exec("INSERT INTO assets (id, job_id, name, type) VALUES ('ta_a', 'tj_a', 'Asset A', 'static')");
    $db->exec("INSERT INTO wiki_pages (id, title, slug, content) VALUES
        ('tw_a', 'Wiki A', 'tw-a', '<p>Real content A</p>'),
        ('tw_b', 'Wiki B', 'tw-b', '<p>Real content B</p>'),
        ('tw_empty', 'Wiki Empty', 'tw-empty', '')");
    $db->exec("INSERT INTO wiki_page_links (wiki_page_id, entity_type, entity_id) VALUES
        ('tw_a', 'job', 'tj_a'), ('tw_b', 'job', 'tj_b')");

    return $env = ['port' => $port, 'db' => $db, 'path' => $path, 'data_dir' => $dataDir];
}

/** Put the mutable fixture rows back to a known state. */
function s301_reset(): void
{
    $db = s301_env()['db'];
    $db->exec("UPDATE jobs SET title = 'Job A', status = 'In Progress', internal_feedback = 'SECRET internal note',
        internal_feedback_by = 'tu_ecd', hours_estimate = 12, internal_approved_by = NULL, internal_approved_at = NULL,
        client_feedback_by = NULL WHERE id = 'tj_a'");
    $db->exec("UPDATE jobs SET title = 'Job B', status = 'In Progress' WHERE id = 'tj_b'");
    $db->exec("UPDATE tasks SET status = 'Not Started', content = NULL, completed_by = NULL, internal_feedback = 'SECRET task note' WHERE id IN ('tt_a', 'tt_b')");
    $db->exec("UPDATE assets SET name = 'Asset A' WHERE id = 'ta_a'");
    $db->exec("UPDATE wiki_pages SET content = '<p>Real content A</p>', title = 'Wiki A' WHERE id = 'tw_a'");
}

function s301_val(string $sql): mixed
{
    return s301_env()['db']->querySingle($sql);
}

/** @return array{cookie:string, csrf:string, set_cookies:list<string>} */
function s301_session(): array
{
    return ['cookie' => '', 'csrf' => '', 'set_cookies' => []];
}

/**
 * @param array<string,mixed> $sess
 * @param array<string,string> $query
 * @param array<string,mixed>|null $body
 * @return array{status:int, json:array<string,mixed>, raw:string}
 */
function s301_call(array &$sess, string $method, string $action, array $query = [], ?array $body = null): array
{
    $port = s301_env()['port'];
    $url = "http://127.0.0.1:$port/api/api.php?" . http_build_query(['action' => $action] + $query);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($sess['cookie'] !== '') {
        $headers[] = 'Cookie: ' . $sess['cookie'];
    }
    if ($sess['csrf'] !== '') {
        $headers[] = 'X-CSRF-Token: ' . $sess['csrf'];
    }
    $http = ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 30];
    if ($body !== null) {
        $http['content'] = json_encode($body, JSON_THROW_ON_ERROR);
    }
    $raw = @file_get_contents($url, false, stream_context_create(['http' => $http]));
    $responseHeaders = $http_response_header ?? [];
    if ($raw === false || $responseHeaders === []) {
        throw new TestFailure("no response from $method $action");
    }
    preg_match('#^HTTP/\S+ (\d{3})#', $responseHeaders[0], $m);
    foreach ($responseHeaders as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) {
            $sess['set_cookies'][] = $h;
            if (preg_match('/SLASH301PM_SID=([^;]*)/i', $h, $cm) && $cm[1] !== '' && $cm[1] !== 'deleted') {
                $sess['cookie'] = 'SLASH301PM_SID=' . $cm[1];
            }
        }
    }
    $json = json_decode($raw, true);
    return ['status' => (int) ($m[1] ?? 0), 'json' => is_array($json) ? $json : [], 'raw' => $raw];
}

/** @return array<string,mixed> a logged-in session */
function s301_login(string $userId): array
{
    $sess = s301_session();
    $r = s301_call($sess, 'POST', 'login', [], ['username' => $userId, 'password' => S301_TEST_PASSWORD]);
    t_eq(200, $r['status'], "login as $userId: " . $r['raw']);
    $sess['csrf'] = (string) ($r['json']['csrf_token'] ?? '');
    t_true($sess['csrf'] !== '', 'login returned a csrf token');
    return $sess;
}

return [
    'auth.php dummy hash is a valid bcrypt hash' => function (): void {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/api/auth.php');
        t_true((bool) preg_match("/const DUMMY_PASSWORD_HASH = '([^']+)'/", $src, $m), 'DUMMY_PASSWORD_HASH constant exists');
        t_eq('bcrypt', password_get_info($m[1])['algoName']);
        t_eq(false, password_verify('anything-at-all', $m[1]));
    },

    'login works over plain http on localhost with a host-only, non-secure cookie' => function (): void {
        s301_env();
        $sess = s301_session();
        $r = s301_call($sess, 'POST', 'login', [], ['username' => 'tu_ecd', 'password' => S301_TEST_PASSWORD]);
        t_eq(200, $r['status'], 'no HTTPS redirect for local dev: ' . $r['raw']);
        $cookie = implode("\n", $sess['set_cookies']);
        t_contains('SLASH301PM_SID=', $cookie);
        t_not_contains('domain=', strtolower($cookie), 'cookie must be host-only');
        t_not_contains('secure', strtolower($cookie), 'cookie must not be Secure on localhost');
        t_contains('HttpOnly', $cookie);
    },

    'unknown username and wrong password both get the same 401' => function (): void {
        s301_env();
        $s = s301_session();
        $a = s301_call($s, 'POST', 'login', [], ['username' => 'nobody-here', 'password' => 'x-long-enough-password']);
        $b = s301_call($s, 'POST', 'login', [], ['username' => 'tu_ecd', 'password' => 'x-long-enough-password']);
        t_eq(401, $a['status']);
        t_eq(401, $b['status']);
        t_eq($a['json'], $b['json']);
    },

    'batch: a Client is denied update_job and nothing changes' => function (): void {
        s301_reset();
        $c = s301_login('tu_client_a');
        $r = s301_call($c, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_job', 'data' => ['id' => 'tj_a', 'title' => 'Hacked by client']],
        ]]);
        t_eq(403, $r['status'], $r['raw']);
        t_eq('Job A', s301_val("SELECT title FROM jobs WHERE id = 'tj_a'"));
    },

    'batch: a Client is denied update_task and update_asset' => function (): void {
        s301_reset();
        $c = s301_login('tu_client_a');
        $r = s301_call($c, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_task', 'data' => ['id' => 'tt_a', 'content' => 'client wrote this']],
        ]]);
        t_eq(403, $r['status'], $r['raw']);
        t_eq(null, s301_val("SELECT content FROM tasks WHERE id = 'tt_a'"));
        $r = s301_call($c, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_asset', 'data' => ['id' => 'ta_a', 'name' => 'Hacked asset']],
        ]]);
        t_eq(403, $r['status'], $r['raw']);
        t_eq('Asset A', s301_val("SELECT name FROM assets WHERE id = 'ta_a'"));
    },

    'batch: one denied operation stops the whole batch (nothing is applied)' => function (): void {
        s301_reset();
        $d = s301_login('tu_designer');
        // tt_a is assigned to the designer (allowed), tt_b is not (denied)
        $r = s301_call($d, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_task', 'data' => ['id' => 'tt_a', 'content' => 'allowed edit']],
            ['action' => 'update_task', 'data' => ['id' => 'tt_b', 'content' => 'not allowed']],
        ]]);
        t_eq(403, $r['status'], $r['raw']);
        t_eq(null, s301_val("SELECT content FROM tasks WHERE id = 'tt_a'"), 'first op must not be applied');
        t_eq(null, s301_val("SELECT content FROM tasks WHERE id = 'tt_b'"));
    },

    'batch: an authorised user can still batch update' => function (): void {
        s301_reset();
        $e = s301_login('tu_ecd');
        $r = s301_call($e, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_job', 'data' => ['id' => 'tj_a', 'sort_order' => 7]],
            ['action' => 'update_task', 'data' => ['id' => 'tt_a', 'content' => 'ok']],
        ]]);
        t_eq(200, $r['status'], $r['raw']);
        t_eq(7, s301_val("SELECT sort_order FROM jobs WHERE id = 'tj_a'"));
        t_eq('ok', s301_val("SELECT content FROM tasks WHERE id = 'tt_a'"));
    },

    '*_by fields are set from the session on update_task and batch' => function (): void {
        s301_reset();
        $d = s301_login('tu_designer');
        $r = s301_call($d, 'POST', 'update_task', [], ['id' => 'tt_a', 'status' => 'Done', 'completed_by' => 'tu_ecd', 'feedback_by' => 'tu_client_b']);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('tu_designer', s301_val("SELECT completed_by FROM tasks WHERE id = 'tt_a'"));
        t_eq('tu_designer', s301_val("SELECT feedback_by FROM tasks WHERE id = 'tt_a'"));

        s301_reset();
        $r = s301_call($d, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_task', 'data' => ['id' => 'tt_a', 'status' => 'Done', 'completed_by' => 'tu_ecd']],
        ]]);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('tu_designer', s301_val("SELECT completed_by FROM tasks WHERE id = 'tt_a'"));
    },

    '*_by fields are set from the session on update_job, add_job and approve_internal' => function (): void {
        s301_reset();
        $e = s301_login('tu_ecd');
        $r = s301_call($e, 'POST', 'update_job', [], [
            'id' => 'tj_a', 'internal_feedback' => 'note',
            'internal_feedback_by' => 'tu_client_b', 'client_feedback_actioned_by' => 'tu_client_b',
        ]);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('tu_ecd', s301_val("SELECT internal_feedback_by FROM jobs WHERE id = 'tj_a'"));
        t_eq('tu_ecd', s301_val("SELECT client_feedback_actioned_by FROM jobs WHERE id = 'tj_a'"));

        $am = s301_login('tu_am');
        $r = s301_call($am, 'POST', 'add_job', [], ['title' => 'New job', 'campaign_id' => 'tc_a', 'created_by' => 'tu_client_b']);
        t_eq(201, $r['status'], $r['raw']);
        $id = (string) $r['json']['job']['id'];
        t_eq('tu_am', s301_val("SELECT created_by FROM jobs WHERE id = '" . SQLite3::escapeString($id) . "'"));

        s301_reset();
        s301_env()['db']->exec("UPDATE tasks SET status = 'Done' WHERE id = 'tt_a'");
        $r = s301_call($e, 'POST', 'approve_internal', [], ['job_id' => 'tj_a', 'approved_by' => 'tu_client_b']);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('tu_ecd', s301_val("SELECT internal_approved_by FROM jobs WHERE id = 'tj_a'"));
    },

    'update_job and batch cannot set an Approved status' => function (): void {
        s301_reset();
        $e = s301_login('tu_ecd');
        foreach (['Approved (Internal)', 'Approved (External)'] as $status) {
            $r = s301_call($e, 'POST', 'update_job', [], ['id' => 'tj_a', 'status' => $status]);
            t_eq(403, $r['status'], $r['raw']);
            $r = s301_call($e, 'POST', 'batch', [], ['operations' => [
                ['action' => 'update_job', 'data' => ['id' => 'tj_a', 'status' => $status]],
            ]]);
            t_eq(403, $r['status'], $r['raw']);
        }
        t_eq('In Progress', s301_val("SELECT status FROM jobs WHERE id = 'tj_a'"));

        // Other statuses still work
        $r = s301_call($e, 'POST', 'update_job', [], ['id' => 'tj_a', 'status' => 'Waiting']);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('Waiting', s301_val("SELECT status FROM jobs WHERE id = 'tj_a'"));

        // Re-sending the status the job already has is not a change (legacy UI sends the whole job)
        s301_env()['db']->exec("UPDATE jobs SET status = 'Approved (Internal)' WHERE id = 'tj_a'");
        $r = s301_call($e, 'POST', 'update_job', [], ['id' => 'tj_a', 'status' => 'Approved (Internal)', 'title' => 'Renamed']);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('Renamed', s301_val("SELECT title FROM jobs WHERE id = 'tj_a'"));
    },

    'demo_login rejects GET and does not log the caller in' => function (): void {
        s301_env();
        $s = s301_session();
        $r = s301_call($s, 'GET', 'demo_login', ['user_id' => 'tu_ecd']);
        t_eq(405, $r['status'], $r['raw']);
        $r = s301_call($s, 'GET', 'get_jobs');
        t_eq(401, $r['status'], 'GET demo_login must not create a session user');
    },

    'demo_login with POST works in demo mode and issues a new session id' => function (): void {
        // Demo mode is a flag file in the isolated test data dir (never data/)
        $flag = s301_env()['data_dir'] . '/.demo_mode';
        file_put_contents($flag, '1');
        try {
            $s = s301_session();
            s301_call($s, 'GET', 'check_session'); // starts a session, sets a cookie
            $before = $s['cookie'];
            t_true($before !== '', 'session cookie issued by check_session');
            $r = s301_call($s, 'POST', 'demo_login', [], ['user_id' => 'tu_ecd']);
            t_eq(200, $r['status'], $r['raw']);
            t_true($s['cookie'] !== $before, 'session id must change on demo_login');
            $r = s301_call($s, 'GET', 'get_jobs');
            t_eq(200, $r['status'], 'demo user can read after demo_login: ' . $r['raw']);
        } finally {
            @unlink($flag);
        }
    },

    'update_wiki_page refuses to blank existing content (409)' => function (): void {
        s301_reset();
        $e = s301_login('tu_ecd');
        $r = s301_call($e, 'POST', 'update_wiki_page', [], ['id' => 'tw_a', 'title' => 'Wiki A v2', 'content' => '']);
        t_eq(409, $r['status'], $r['raw']);
        $r = s301_call($e, 'POST', 'update_wiki_page', [], ['id' => 'tw_a', 'content' => "  \n "]);
        t_eq(409, $r['status'], $r['raw']);
        t_eq('<p>Real content A</p>', s301_val("SELECT content FROM wiki_pages WHERE id = 'tw_a'"));
        t_eq('Wiki A', s301_val("SELECT title FROM wiki_pages WHERE id = 'tw_a'"), 'nothing partially saved');

        // Allowed: real content, no content key, and empty over empty
        $r = s301_call($e, 'POST', 'update_wiki_page', [], ['id' => 'tw_a', 'content' => '<p>New</p>']);
        t_eq(200, $r['status'], $r['raw']);
        t_eq('<p>New</p>', s301_val("SELECT content FROM wiki_pages WHERE id = 'tw_a'"));
        $r = s301_call($e, 'POST', 'update_wiki_page', [], ['id' => 'tw_a', 'title' => 'Title only']);
        t_eq(200, $r['status'], $r['raw']);
        $r = s301_call($e, 'POST', 'update_wiki_page', [], ['id' => 'tw_empty', 'content' => '']);
        t_eq(200, $r['status'], $r['raw']);
    },

    'a failed write returns 500 JSON, not success' => function (): void {
        s301_reset();
        $e = s301_login('tu_ecd');
        // 'Bogus' violates the tasks.status CHECK constraint
        $r = s301_call($e, 'POST', 'update_task', [], ['id' => 'tt_a', 'status' => 'Bogus']);
        t_eq(500, $r['status'], $r['raw']);
        t_true(isset($r['json']['error']), 'JSON error body');
        t_eq('Not Started', s301_val("SELECT status FROM tasks WHERE id = 'tt_a'"));
        // Inside a batch the earlier operation is rolled back too
        $r = s301_call($e, 'POST', 'batch', [], ['operations' => [
            ['action' => 'update_job', 'data' => ['id' => 'tj_a', 'title' => 'Half done']],
            ['action' => 'update_task', 'data' => ['id' => 'tt_a', 'status' => 'Bogus']],
        ]]);
        t_eq(500, $r['status'], $r['raw']);
        t_eq('Job A', s301_val("SELECT title FROM jobs WHERE id = 'tj_a'"));
    },

    'Client: get_users returns only own brand plus agency staff, without emails' => function (): void {
        s301_reset();
        $c = s301_login('tu_client_a');
        $r = s301_call($c, 'GET', 'get_users');
        t_eq(200, $r['status'], $r['raw']);
        $byId = [];
        foreach ($r['json']['users'] as $u) {
            $byId[$u['id']] = $u;
        }
        t_true(isset($byId['tu_client_a']) && isset($byId['tu_client_a2']), 'own brand users present');
        t_true(!isset($byId['tu_client_b']), 'other brand client hidden');
        t_true(isset($byId['tu_ecd']) && isset($byId['tu_am']), 'agency staff present');
        t_eq('Name tu_ecd', $byId['tu_ecd']['name']);
        foreach ($r['json']['users'] as $u) {
            if ($u['id'] !== 'tu_client_a') {
                t_eq(null, $u['email'], 'no email for ' . $u['id']);
                t_true(!isset($u['username']), 'no username for ' . $u['id']);
            }
            t_true($u['role'] !== 'Client' || $u['brand_id'] === 'tb_a', 'only own-brand clients');
        }
        // Staff still see everyone
        $e = s301_login('tu_ecd');
        $r = s301_call($e, 'GET', 'get_users');
        $ids = array_column($r['json']['users'], 'id');
        t_true(in_array('tu_client_b', $ids, true), 'ECD sees all users');
    },

    'Client: get_campaigns is limited to their brand' => function (): void {
        s301_reset();
        $c = s301_login('tu_client_a');
        foreach ([[], ['brand_id' => 'tb_b']] as $query) {
            $r = s301_call($c, 'GET', 'get_campaigns', $query);
            t_eq(200, $r['status'], $r['raw']);
            $ids = array_column($r['json']['campaigns'], 'id');
            t_eq(['tc_a'], $ids, 'only tc_a, whatever brand_id is passed');
        }
    },

    'Client: wiki pages are limited to pages linked to their brand' => function (): void {
        s301_reset();
        $c = s301_login('tu_client_a');
        $r = s301_call($c, 'GET', 'get_wiki_pages');
        t_eq(200, $r['status'], $r['raw']);
        t_eq(['tw_a'], array_column($r['json']['wikiPages'], 'id'));
        t_eq(['tj_a'], $r['json']['wikiPages'][0]['linkedJobs']);
        t_eq(200, s301_call($c, 'GET', 'get_wiki_page', ['id' => 'tw_a'])['status']);
        t_eq(404, s301_call($c, 'GET', 'get_wiki_page', ['id' => 'tw_b'])['status']);
        t_eq(404, s301_call($c, 'GET', 'get_wiki_page', ['id' => 'tw_empty'])['status']);
    },

    'Client: internal-only fields are stripped from job reads' => function (): void {
        s301_reset();
        $c = s301_login('tu_client_a');
        $r = s301_call($c, 'GET', 'get_job', ['id' => 'tj_a']);
        t_eq(200, $r['status'], $r['raw']);
        t_not_contains('SECRET', $r['raw']);
        t_true(!array_key_exists('internal_feedback', $r['json']['job']));
        t_true(!array_key_exists('hours_estimate', $r['json']['job']));
        t_true(!array_key_exists('internal_feedback', $r['json']['job']['tasks'][0]));
        $r = s301_call($c, 'GET', 'get_jobs');
        t_eq(200, $r['status'], $r['raw']);
        t_not_contains('SECRET', $r['raw']);
        $jobIds = array_column($r['json']['jobs'], 'id');
        t_true(in_array('tj_a', $jobIds, true) && !in_array('tj_b', $jobIds, true), 'only own brand jobs');
        t_eq(404, s301_call($c, 'GET', 'get_job', ['id' => 'tj_b'])['status']);
        // Staff still get the internal fields
        $e = s301_login('tu_ecd');
        $r = s301_call($e, 'GET', 'get_job', ['id' => 'tj_a']);
        t_eq('SECRET internal note', $r['json']['job']['internal_feedback']);
    },

    'get_job: a creative can read assigned jobs only' => function (): void {
        s301_reset();
        $d = s301_login('tu_designer');
        t_eq(200, s301_call($d, 'GET', 'get_job', ['id' => 'tj_a'])['status']);
        t_eq(403, s301_call($d, 'GET', 'get_job', ['id' => 'tj_b'])['status']);
    },

    'demo_login: POST JSON from the legacy client shape works, GET is refused, cookie path is /slash301pm/' => function (): void {
        $env = s301_env();
        $flag = $env['data_dir'] . '/.demo_mode';
        file_put_contents($flag, '1');
        try {
            $sess = s301_session();
            // Old shape (GET with user_id in the query) must be refused
            $r = s301_call($sess, 'GET', 'demo_login', ['user_id' => 'tu_ecd']);
            t_eq(405, $r['status'], $r['raw']);
            // New legacy client shape: POST JSON {user_id}
            $r = s301_call($sess, 'POST', 'demo_login', [], ['user_id' => 'tu_ecd']);
            t_eq(200, $r['status'], $r['raw']);
            t_eq('tu_ecd', $r['json']['user']['id'] ?? null);
            t_contains('path=/slash301pm/', implode("\n", $sess['set_cookies']), 'session cookie path');
            // The session now acts as that user (no password, no CSRF in demo mode)
            $me = s301_call($sess, 'GET', 'get_jobs');
            t_eq(200, $me['status'], $me['raw']);
        } finally {
            @unlink($flag);
        }
    },

    'add_job: numbers come from job_counters and never collide' => function (): void {
        $env = s301_env();
        $db = $env['db'];
        $db->exec('CREATE TABLE IF NOT EXISTS job_counters (prefix TEXT PRIMARY KEY, next INTEGER NOT NULL)');
        $db->exec("DELETE FROM job_counters WHERE prefix = 'TSA'");
        $e = s301_login('tu_ecd');
        $maxBefore = (int) s301_val("SELECT MAX(CAST(SUBSTR(job_number, 5) AS INTEGER)) FROM jobs WHERE job_number LIKE 'TSA-%'");
        $nums = [];
        for ($i = 0; $i < 2; $i++) {
            $r = s301_call($e, 'POST', 'add_job', [], ['title' => 'Counter job ' . $i, 'campaign_id' => 'tc_a']);
            t_eq(201, $r['status'], $r['raw']);
            $nums[] = (string) $r['json']['job']['job_number'];
        }
        // Seeded from the existing max for the prefix, then +1 each time
        t_eq([sprintf('TSA-%03d', $maxBefore + 1), sprintf('TSA-%03d', $maxBefore + 2)], $nums);
        t_eq($maxBefore + 3, (int) s301_val("SELECT next FROM job_counters WHERE prefix = 'TSA'"));
        t_true($nums[0] !== $nums[1], 'no collision');
        $db->exec("DELETE FROM jobs WHERE title LIKE 'Counter job %'");
    },

    'Client: new internal-only job columns never reach a Client' => function (): void {
        $env = s301_env();
        $db = $env['db'];
        $cols = ['stage' => "'doing'", 'stage_changed_at' => "'2026-01-01'", 'waiting_on' => "'SECRETWAIT'", 'waiting_reason' => "'SECRETREASON'",
            'resume_stage' => "'doing'", 'row_version' => '7', 'am_user_id' => "'tu_am'", 'updated_by' => "'tu_ecd'"];
        $have = [];
        $res = $db->query('PRAGMA table_info(jobs)');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $have[$row['name']] = true;
        }
        foreach ($cols as $name => $_) {
            if (!isset($have[$name])) {
                $db->exec("ALTER TABLE jobs ADD COLUMN $name " . ($name === 'row_version' ? 'INTEGER' : 'TEXT'));
            }
        }
        $sets = [];
        foreach ($cols as $name => $val) {
            $sets[] = "$name = $val";
        }
        $db->exec('UPDATE jobs SET ' . implode(', ', $sets) . " WHERE id = 'tj_a'");
        $c = s301_login('tu_client_a');
        foreach ([s301_call($c, 'GET', 'get_job', ['id' => 'tj_a']), s301_call($c, 'GET', 'get_jobs')] as $r) {
            t_eq(200, $r['status'], $r['raw']);
            t_not_contains('SECRETWAIT', $r['raw']);
            t_not_contains('SECRETREASON', $r['raw']);
            $job = $r['json']['job'] ?? null;
            if ($job === null) {
                foreach ($r['json']['jobs'] as $j) {
                    if ($j['id'] === 'tj_a') {
                        $job = $j;
                    }
                }
            }
            t_true(is_array($job), 'job present');
            foreach (array_keys($cols) as $name) {
                t_true(!array_key_exists($name, $job), "client must not see $name");
            }
        }
        // Staff still see them
        $e = s301_login('tu_ecd');
        $r = s301_call($e, 'GET', 'get_job', ['id' => 'tj_a']);
        t_eq('SECRETWAIT', $r['json']['job']['waiting_on'] ?? null);
    },
];

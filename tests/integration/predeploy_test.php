<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/support/app.php';

/**
 * tools/predeploy.php against throwaway git repositories (never this checkout):
 * a clean baseline commit, then one scenario per branch. --no-tests and
 * --no-build keep it fast and offline.
 */

/** Run a command in $dir; returns [exit code, output]. @return array{0:int,1:string} */
function pd_run(string $dir, string $cmd): array
{
    $out = [];
    $code = 0;
    $env = 'GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@example.com GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@example.com ';
    exec('cd ' . escapeshellarg($dir) . ' && ' . $env . $cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

/** @param array<string,string|null> $files path => content (null deletes) */
function pd_write(string $dir, array $files): void
{
    foreach ($files as $path => $content) {
        $full = $dir . '/' . $path;
        if ($content === null) {
            @unlink($full);
            continue;
        }
        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0700, true);
        }
        file_put_contents($full, $content);
    }
}

function pd_commit(string $dir, string $msg, int $time = 1_800_000_000): void
{
    $date = '@' . $time . ' +0000';
    [$code, $out] = pd_run($dir, 'git add -A && GIT_AUTHOR_DATE=' . escapeshellarg($date) . ' GIT_COMMITTER_DATE=' . escapeshellarg($date) . ' git commit -q -m ' . escapeshellarg($msg));
    t_eq(0, $code, $out);
}

/** @return array{0:int,1:string} */
function pd_check(string $dir, string $to = 'HEAD'): array
{
    return pd_run($dir, 'php ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/predeploy.php') . ' base ' . escapeshellarg($to) . ' --no-tests --no-build --repo=' . escapeshellarg($dir));
}

/** A repo with a clean, deployable baseline tagged "base". */
function pd_repo(): string
{
    $dir = ts_temp_dir() . '/repo';
    mkdir($dir, 0700, true);
    pd_run($dir, 'git init -q && git config commit.gpgsign false');
    $deny = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
    $sql = "CREATE TABLE IF NOT EXISTS a (id TEXT PRIMARY KEY);\n";
    $js = "console.log('datastar');\n";
    $files = [
        '.htaccess' => "RewriteEngine On\n", 'index.php' => "<?php\necho 1;\n", 'app/x.php' => "<?php\ndeclare(strict_types=1);\n",
        'api/.htaccess' => $deny, 'api/x.php' => "<?php\n", 'migrations/0001_a.sql' => $sql,
        'migrations/CHECKSUMS.txt' => hash('sha256', $sql) . "  0001_a.sql\n",
        'public/js/datastar.js' => $js, 'public/js/datastar.js.sha256' => hash('sha256', $js) . "  datastar.js\n",
        'styles/app.css' => "@import \"tailwindcss\";\n", 'public/css/app.css' => "/* built */\n",
    ];
    foreach (['app', 'vendor', 'migrations', 'tests', 'tools', 'data', 'data/sessions', 'data/logs'] as $d) {
        $files[$d . '/.htaccess'] = $deny;
    }
    pd_write($dir, $files);
    pd_commit($dir, 'base', 1_800_000_000);
    pd_run($dir, 'git tag base');
    return $dir;
}

function pd_branch(string $dir, string $name): void
{
    [$code, $out] = pd_run($dir, 'git checkout -q -b ' . escapeshellarg($name) . ' base');
    t_eq(0, $code, $out);
}

return [
    'predeploy: a clean change passes and lists files in deploy order' => function (): void {
        $dir = pd_repo();
        pd_branch($dir, 'clean');
        pd_write($dir, ['app/y.php' => "<?php\n", 'public/css/print.css' => "a{}\n", 'tests/a_test.php' => "<?php\n", 'docs/x.md' => "x\n",
            'styles/extra.css' => "a{}\n", '.claude/notes.txt' => "x\n", 'tools/t.php' => "<?php\n", 'app/sub/.htaccess' => "Require all denied\n", '.htaccess' => "RewriteEngine On\n# v2\n"]);
        pd_commit($dir, 'clean', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(0, $code, $out);
        t_contains('Pre-deploy check passed', $out);
        t_true(strpos($out, 'upload  app/sub/.htaccess') < strpos($out, 'upload  app/y.php'), 'sub-folder .htaccess first');
        t_true(strpos($out, 'upload  public/css/print.css') < strpos($out, 'upload  .htaccess'), 'root .htaccess last');
        pd_branch($dir, 'order');
        $sql = "CREATE TABLE IF NOT EXISTS b (id TEXT);\n";
        pd_write($dir, ['app/z.php' => "<?php\n", 'index.php' => "<?php\necho 2;\n", 'api/y.php' => "<?php\n", 'migrations/0002_b.sql' => $sql,
            'migrations/CHECKSUMS.txt' => file_get_contents($dir . '/migrations/CHECKSUMS.txt') . hash('sha256', $sql) . "  0002_b.sql\n"]);
        pd_commit($dir, 'order', 1_800_000_200);
        [$code2, $out2] = pd_check($dir);
        t_eq(0, $code2, $out2);
        $at = static fn (string $p): int => (int) strpos($out2, 'upload  ' . $p);
        t_true($at('migrations/0002_b.sql') < $at('api/y.php') && $at('api/y.php') < $at('app/z.php') && $at('app/z.php') < $at('index.php'),
            'backend order: migrations, api, app, index.php last' . "\n" . $out2);
        foreach (['tests/a_test.php', 'docs/x.md', 'styles/extra.css', '.claude/notes.txt', 'tools/t.php'] as $repoOnly) {
            t_not_contains('upload  ' . $repoOnly, $out, 'repo-only file in the manifest');
        }
        t_contains('ok    migration checksums: 1 file(s) match', $out);
        t_contains('ok    datastar.js matches its pinned sha256', $out);
        t_contains('ok    deny .htaccess present', $out);
    },
    'predeploy: forbidden files fail the check, each with its reason' => function (): void {
        $cases = [
            'unzipper.php' => 'server unzip tool', 'release.zip' => 'archive', 'public/site.tar.gz' => 'archive', 'api/seed.php' => 'seed script',
            'backup/old.js' => 'old backup copies', 'data/.demo_mode' => 'demo mode switch', 'data/slash301pm.db' => 'database file',
            'data/logs/app-2026-10-10.log' => 'runtime data', 'data/seed-password-check.json' => 'runtime data', 'data/sessions/sess_abc' => 'runtime data',
            'data/backups/pre-0001.db' => 'database file', '.env' => 'environment secrets', 'app/.env.local' => 'environment secrets', 'legacy/x.sqlite' => 'database file',
        ];
        $dir = pd_repo();
        foreach ($cases as $path => $why) {
            pd_branch($dir, 'f' . md5($path));
            pd_write($dir, [$path => "x\n"]);
            pd_commit($dir, 'bad', 1_800_000_100);
            [$code, $out] = pd_check($dir);
            t_eq(1, $code, "$path: $out");
            t_contains("$path ($why", $out, $path);
            t_contains('Pre-deploy check FAILED', $out);
            t_not_contains('upload  ' . $path, $out, "$path never listed for upload");
        }
    },
    'predeploy: migrations must match CHECKSUMS.txt, pass the lint and never change after shipping' => function (): void {
        $dir = pd_repo();
        pd_branch($dir, 'edited');
        pd_write($dir, ['migrations/0001_a.sql' => "CREATE TABLE IF NOT EXISTS a (id TEXT PRIMARY KEY, x TEXT);\n"]);
        pd_commit($dir, 'edit', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(1, $code, $out);
        t_contains('migrations/0001_a.sql (edited after it shipped)', $out);
        t_contains('0001_a.sql sha256', $out);

        pd_branch($dir, 'unlisted');
        pd_write($dir, ['migrations/0002_b.sql' => "CREATE TABLE IF NOT EXISTS b (id TEXT);\n"]);
        pd_commit($dir, 'new', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(1, $code, $out);
        t_contains('0002_b.sql is not in migrations/CHECKSUMS.txt', $out);

        pd_branch($dir, 'lint');
        $bad = "INSERT INTO a (id) VALUES ('x') RETURNING id;\n";
        pd_write($dir, ['migrations/0002_b.sql' => $bad, 'migrations/CHECKSUMS.txt' => file_get_contents($dir . '/migrations/CHECKSUMS.txt') . hash('sha256', $bad) . "  0002_b.sql\n"]);
        pd_commit($dir, 'lint', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(1, $code, $out);
        t_contains('RETURNING needs SQLite 3.35', $out);

        pd_branch($dir, 'good');
        $ok = "CREATE TABLE IF NOT EXISTS b (id TEXT);\n";
        pd_write($dir, ['migrations/0002_b.sql' => $ok, 'migrations/CHECKSUMS.txt' => file_get_contents($dir . '/migrations/CHECKSUMS.txt') . hash('sha256', $ok) . "  0002_b.sql\n"]);
        pd_commit($dir, 'good', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(0, $code, $out);
        t_contains('migration checksums: 2 file(s) match', $out);
        t_not_contains('upload  migrations/CHECKSUMS.txt', $out, 'the checksum list stays in the repo');
    },
    'predeploy: datastar pin, deny files and PHP syntax' => function (): void {
        $dir = pd_repo();
        pd_branch($dir, 'pin');
        pd_write($dir, ['public/js/datastar.js' => "console.log('tampered');\n"]);
        pd_commit($dir, 'pin', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(1, $code, $out);
        t_contains('public/js/datastar.js sha256', $out);

        pd_branch($dir, 'deny');
        pd_write($dir, ['vendor/.htaccess' => null, 'data/logs/.htaccess' => "# nothing\n"]);
        pd_commit($dir, 'deny', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(1, $code, $out);
        t_contains('data/logs/.htaccess', $out);
        t_contains('vendor/.htaccess', $out);

        pd_branch($dir, 'syntax');
        pd_write($dir, ['app/broken.php' => "<?php\nfunction (\n"]);
        pd_commit($dir, 'syntax', 1_800_000_100);
        [$code, $out] = pd_check($dir);
        t_eq(1, $code, $out);
        t_contains('PHP syntax errors', $out);
        t_contains('app/broken.php', $out);
    },
    'predeploy: Tailwind input newer than the built CSS only warns' => function (): void {
        $dir = pd_repo();
        pd_branch($dir, 'css');
        pd_write($dir, ['styles/app.css' => "@import \"tailwindcss\";\n/* changed */\n"]);
        pd_commit($dir, 'css', 1_800_000_500);
        [$code, $out] = pd_check($dir);
        t_eq(0, $code, $out);
        t_contains('WARN  public/css/app.css was last committed before a change in styles/', $out);
        t_contains('passed with 1 warning(s)', $out);
    },
    'predeploy: WORKTREE checks uncommitted and new files too' => function (): void {
        $dir = pd_repo();
        pd_write($dir, ['app/new.php' => "<?php\n", 'unzipper.php' => "<?php\n"]);
        [$code, $out] = pd_check($dir, 'WORKTREE');
        t_eq(1, $code, $out);
        t_contains('unzipper.php (server unzip tool)', $out);
        t_contains('upload  app/new.php', $out);
        unlink($dir . '/unzipper.php');
        [$code, $out] = pd_check($dir, 'WORKTREE');
        t_eq(0, $code, $out);
    },
];

<?php
declare(strict_types=1);

/**
 * Zero-dependency test runner (ports to Go's table-driven tests).
 *
 *   php tests/run.php                 all suites
 *   php tests/run.php --unit          tests/unit only (fast; used by the Stop hook)
 *   php tests/run.php integration     one suite: unit | integration | migration
 *   php tests/run.php --filter=brief  only test names containing "brief"
 *
 * A test file is tests/<suite>/**\/*_test.php and returns an array of
 * 'test name' => callable. A test fails if it throws. Use the t_* asserts below.
 */

require_once dirname(__DIR__) . '/app/autoload.php';

final class TestFailure extends RuntimeException {}

function t_eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(($msg !== '' ? "$msg\n" : '') . 'expected: ' . var_export($expected, true) . "\n  actual: " . var_export($actual, true));
    }
}

function t_true(bool $cond, string $msg = 'expected true'): void
{
    if (!$cond) {
        throw new TestFailure($msg);
    }
}

function t_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new TestFailure(($msg !== '' ? "$msg\n" : '') . "expected to contain: $needle\n  in: " . substr($haystack, 0, 400));
    }
}

function t_not_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new TestFailure(($msg !== '' ? "$msg\n" : '') . "expected NOT to contain: $needle");
    }
}

function t_throws(callable $fn, string $class = Throwable::class): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new TestFailure("expected $class, got " . get_class($e) . ': ' . $e->getMessage());
    }
    throw new TestFailure("expected $class to be thrown");
}

/** Fresh temp SQLite file path for integration tests; deleted at exit. */
function t_temp_db(): string
{
    $path = tempnam(sys_get_temp_dir(), 's301db_');
    register_shutdown_function(static function () use ($path): void {
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($path . $suffix);
        }
    });
    return $path;
}

$args = array_slice($argv, 1);
$suites = ['unit', 'integration', 'migration'];
$filter = '';
$chosen = [];
foreach ($args as $a) {
    if ($a === '--unit') {
        $chosen[] = 'unit';
    } elseif (str_starts_with($a, '--filter=')) {
        $filter = substr($a, 9);
    } elseif (in_array($a, $suites, true)) {
        $chosen[] = $a;
    }
}
if ($chosen === []) {
    $chosen = $suites;
}

$passed = 0;
$failed = [];
foreach ($chosen as $suite) {
    $dir = __DIR__ . '/' . $suite;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    $files = [];
    foreach ($it as $f) {
        if (str_ends_with($f->getFilename(), '_test.php')) {
            $files[] = $f->getPathname();
        }
    }
    sort($files);
    foreach ($files as $file) {
        $tests = require $file;
        if (!is_array($tests)) {
            $failed[] = "$file: did not return an array of tests";
            continue;
        }
        $rel = substr($file, strlen(__DIR__) + 1);
        foreach ($tests as $name => $fn) {
            $full = "$rel :: $name";
            if ($filter !== '' && stripos($full, $filter) === false) {
                continue;
            }
            try {
                $fn();
                $passed++;
            } catch (Throwable $e) {
                $failed[] = "$full\n  " . str_replace("\n", "\n  ", $e->getMessage())
                    . ($e instanceof TestFailure ? '' : "\n  at " . $e->getFile() . ':' . $e->getLine());
            }
        }
    }
}

foreach ($failed as $f) {
    echo "FAIL $f\n";
}
echo sprintf("\n%d passed, %d failed (%s)\n", $passed, count($failed), implode(', ', $chosen));
exit($failed === [] ? 0 : 1);

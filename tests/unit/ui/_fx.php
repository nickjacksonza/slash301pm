<?php
declare(strict_types=1);

// Shared by the ui tests: loads the partials, the normaliser and the fixture assert.
require_once dirname(__DIR__, 3) . '/app/autoload.php';
require_once dirname(__DIR__, 3) . '/app/View/ui/_all.php';
require_once __DIR__ . '/_normalize.php';

/** Assert that rendered HTML equals the fixture tests/fixtures/ui/<name>.html after normalising both. */
function ui_fx(string $name, string $html): void
{
    $diff = ui_fixture_diff($html, dirname(__DIR__, 2) . '/fixtures/ui/' . $name . '.html');
    if ($diff !== null) {
        throw new TestFailure($diff);
    }
}

/** Build the tests array entries "<prefix> <name>" => fn for a map of fixture name => callable returning HTML. */
function ui_fx_cases(string $prefix, array $cases): array
{
    $tests = [];
    foreach ($cases as $name => $render) {
        $tests["$prefix fixture $name"] = static function () use ($name, $render): void {
            ui_fx($name, $render());
        };
    }
    return $tests;
}

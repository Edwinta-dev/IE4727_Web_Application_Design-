<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/assert.php';

// The suite never touches the application database (ie4727db). Every run uses
// its own database, rebuilt from 001 + 002 + 003 first so results never depend
// on what an earlier run left behind. The environment variable (not just a
// constant) is what child PHP processes spawned by tests inherit.
const TEST_DATABASE = 'ie4727db_test';
putenv('CLINIC_DB_NAME=' . TEST_DATABASE);
// Do not load clinic-base/config.php here: some tests define config constants
// themselves before their first include. test_db_isolation verifies the database.
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/db_reset.php') . ' --test 2>&1', $resetOutput, $resetStatus);
if ($resetStatus !== 0 || !in_array('Reset ' . TEST_DATABASE . ' (--test)', $resetOutput, true)) {
    fwrite(STDERR, "Could not rebuild the test database " . TEST_DATABASE . ":\n" . implode("\n", $resetOutput) . "\n");
    exit(1);
}

/** @var list<array{name: string, fn: callable}> $cases */
$cases = [];

function test(string $name, callable $fn): void
{
    global $cases;
    $cases[] = ['name' => $name, 'fn' => $fn];
}

$requested = $argv[1] ?? null;
if ($requested === null) {
    $files = glob(__DIR__ . '/test_*.php') ?: [];
    sort($files);
} else {
    $requested = preg_replace('/[\\\\\/]+/', DIRECTORY_SEPARATOR, $requested) ?? $requested;
    $requested = ltrim($requested, DIRECTORY_SEPARATOR);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $requested;
    if (pathinfo($requested, PATHINFO_EXTENSION) !== 'php') {
        $file .= '.php';
    }
    $files = [$file];
}

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Test file not found: {$file}\n");
        exit(1);
    }

    $before = count($cases);
    $name = basename($file, '.php');
    ob_start();
    try {
        require $file;
        ob_end_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        test($name, static function () use ($exception): void {
            throw $exception;
        });
        continue;
    }

    if (count($cases) === $before) {
        test($name, static function (): void {
            // Script-style tests report failures by throwing during require.
        });
    }
}

$passed = 0;
$failed = 0;
foreach ($cases as $case) {
    try {
        ($case['fn'])();
        echo "PASS: {$case['name']}\n";
        $passed++;
    } catch (Throwable $exception) {
        echo "FAIL: {$case['name']}: {$exception->getMessage()}\n";
        $failed++;
    }
}

echo "{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

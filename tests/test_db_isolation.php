<?php

declare(strict_types=1);

// The suite must never write to the application database. tests/run.php pins
// CLINIC_DB_NAME to ie4727db_test and rebuilds it from the seed before any test.

require_once dirname(__DIR__) . '/clinic-base/lib/db.php';

assert_eq(DB_NAME, 'ie4727db_test', 'suite database constant');
assert_eq(getenv('CLINIC_DB_NAME'), 'ie4727db_test', 'environment inherited by child processes');
assert_eq(q('SELECT DATABASE() AS db')->fetch()['db'], 'ie4727db_test', 'q() connection');

// A child PHP process (as test_csrf spawns) must land on the same database.
$probe = tempnam(sys_get_temp_dir(), 'dbiso');
file_put_contents($probe, '<?php require ' . var_export(dirname(__DIR__) . '/clinic-base/lib/db.php', true) . '; echo q("SELECT DATABASE() AS db")->fetch()["db"];');
$child = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe)));
unlink($probe);
assert_eq($child, 'ie4727db_test', 'child process database');

// Rebuilt from the seed: only the seeded demo patients exist at start of run.
assert_true((int) q('SELECT COUNT(*) AS n FROM `patient`')->fetch()['n'] < 50, 'test database starts from the seed');

// db_reset refuses to treat the application database as a test database.
$refused = [];
$command = 'set CLINIC_DB_NAME=ie4727db&& ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/db_reset.php') . ' --test 2>&1';
if (DIRECTORY_SEPARATOR === '/') {
    $command = 'CLINIC_DB_NAME=ie4727db ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/db_reset.php') . ' --test 2>&1';
}
exec($command, $refused, $status);
assert_true($status !== 0 && str_contains(implode("\n", $refused), "must end in '_test'"), 'db_reset --test refuses ie4727db');

echo "PASS: test database isolation\n";

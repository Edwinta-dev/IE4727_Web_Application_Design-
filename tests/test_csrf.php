<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/csrf.php';

session_save_path(sys_get_temp_dir());
session_id('csrf-test-' . bin2hex(random_bytes(4)));
start_session_once();
$_SESSION = [];

test('token is stable and field is hidden', function (): void {
    $first = csrf_token();
    $second = csrf_token();

    assert_eq($first, $second, 'CSRF token changed within a session');
    assert_eq(strlen($first), 64, 'CSRF token is not 32 random bytes encoded safely');
    assert_contains(csrf_field(), 'name="_csrf"');
    assert_contains(csrf_field(), 'value="' . $first . '"');
});

test('token rotates explicitly', function (): void {
    $before = csrf_token();
    $after = csrf_rotate();

    assert_true($before !== $after, 'CSRF token did not rotate');
    assert_eq(csrf_token(), $after, 'rotated token was not stored in the session');
});

$csrfPath = dirname(__DIR__) . '/clinic-base/lib/csrf.php';
$runner = tempnam(sys_get_temp_dir(), 'csrf-test-');
if ($runner === false) {
    throw new RuntimeException('Unable to create CSRF rejection test runner');
}

$runnerCode = '<?php' . PHP_EOL
    . 'session_save_path(sys_get_temp_dir());' . PHP_EOL
    . 'session_id("csrf-rejection-' . bin2hex(random_bytes(4)) . '");' . PHP_EOL
    . 'require_once ' . var_export($csrfPath, true) . ';' . PHP_EOL
    . 'csrf_token();' . PHP_EOL
    . '$_POST = (($argv[1] ?? "") === "missing") ? [] : ["_csrf" => "wrong"];' . PHP_EOL
    . 'csrf_check();' . PHP_EOL;
file_put_contents($runner, $runnerCode);

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' missing 2>&1';
$missingOutput = shell_exec($command);
$missingStatus = null;
exec($command, $unused, $missingStatus);

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' wrong 2>&1';
$wrongOutput = shell_exec($command);
$wrongStatus = null;
exec($command, $unused, $wrongStatus);
@unlink($runner);

test('missing and wrong tokens are rejected', function () use ($missingOutput, $wrongOutput, $missingStatus, $wrongStatus): void {
    assert_eq($missingStatus, 0, 'missing-token rejection runner failed unexpectedly');
    assert_eq($wrongStatus, 0, 'wrong-token rejection runner failed unexpectedly');
    assert_contains((string) $missingOutput, 'CSRF token validation failed');
    assert_contains((string) $wrongOutput, 'CSRF token validation failed');
});

if (strpos(file_get_contents($csrfPath) ?: '', 'hash_equals(') === false) {
    throw new RuntimeException('csrf_check() must use hash_equals()');
}

echo "PASS: CSRF checks\n";

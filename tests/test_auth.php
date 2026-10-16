<?php

declare(strict_types=1);

defined('ADMIN_USER') || define('ADMIN_USER', 'admin');
defined('ADMIN_HASH') || define('ADMIN_HASH', password_hash('Password123', PASSWORD_DEFAULT));

require_once dirname(__DIR__) . '/clinic-base/lib/auth.php';

function auth_attempt_in_fresh_session(string $client, string $user, string $password): bool
{
    $authPath = var_export(dirname(__DIR__) . '/clinic-base/lib/auth.php', true);
    $runner = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ie4727-auth-attempt-' . getmypid() . '.php';
    $script = 'ini_set(\'session.save_path\', sys_get_temp_dir()); $_SERVER[\'REMOTE_ADDR\'] = getenv(\'IE4727_TEST_AUTH_CLIENT\') ?: \'unknown\'; require '
        . $authPath . '; echo attempt_login(getenv(\'IE4727_TEST_AUTH_USER\') ?: \'\', getenv(\'IE4727_TEST_AUTH_PASSWORD\') ?: \'\') ? \'1\' : \'0\';';
    assert_true(file_put_contents($runner, "<?php\n" . $script) !== false, 'fresh-session test runner can be written');
    putenv('IE4727_TEST_AUTH_CLIENT=' . $client);
    putenv('IE4727_TEST_AUTH_USER=' . $user);
    putenv('IE4727_TEST_AUTH_PASSWORD=' . $password);
    try {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1', $output, $status);
    } finally {
        putenv('IE4727_TEST_AUTH_CLIENT');
        putenv('IE4727_TEST_AUTH_USER');
        putenv('IE4727_TEST_AUTH_PASSWORD');
        @unlink($runner);
    }
    assert_true($status === 0, 'fresh-session authentication process exits cleanly: ' . implode("\n", $output));
    $result = implode('', $output);
    assert_true($result === '0' || $result === '1', 'fresh-session authentication emits a result: ' . $result);
    return $result === '1';
}

test('authentication and role guards', static function (): void {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.163';
    auth_clear_failures();
    $canRotate = !headers_sent();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.save_path', sys_get_temp_dir());
        session_start();
    }

    $before = session_id();
    assert_true(attempt_login('drsmith', 'Password123'), 'doctor username login');
    if ($canRotate) {
        assert_true(session_id() !== $before, 'login regenerates the session id');
    } else {
        assert_contains(file_get_contents(dirname(__DIR__) . '/clinic-base/lib/auth.php') ?: '', 'session_regenerate_id(true)');
    }
    assert_true(is_doctor(), 'doctor role is stored');
    assert_true(current_user() !== null, 'doctor profile is available');
    assert_true(isset(current_user()['DoctorID']), 'doctor id is loaded from the profile');
    assert_true(!isset($_SESSION['remember_hash']), 'login does not create remember-token session state');
    assert_true(!isset($_COOKIE['clinic_remember']), 'login does not create a remember cookie');
    $authSource = file_get_contents(dirname(__DIR__) . '/clinic-base/lib/auth.php') ?: '';
    assert_true(!str_contains($authSource, 'auth_set_remember_cookie') && !str_contains($authSource, 'auth_remember_token_matches'), 'unused remember-token functions are removed');
    assert_true(str_contains($authSource, "setcookie('clinic_remember', ''"), 'logout clears any legacy remember cookie');

    logout();
    assert_true(attempt_login('drsmith@clinic.test', 'Password123'), 'doctor email login');
    logout();

    assert_true(!attempt_login('drsmith', 'wrong password'), 'wrong password fails');
    assert_true(attempt_login('alextan', 'Password123'), 'patient login');
    assert_true(is_patient(), 'patient role is stored');
    assert_true(!is_doctor(), 'patient is not a doctor');
    logout();

    assert_true(attempt_login('admin', 'Password123'), 'admin login');
    assert_true(is_admin(), 'admin role is stored');
    logout();

    for ($i = 0; $i < AUTH_MAX_FAILURES; $i++) {
        assert_true(!auth_attempt_in_fresh_session('192.0.2.163', 'alextan', 'wrong password'), 'wrong password fails from a fresh session');
    }
    assert_true(!auth_attempt_in_fresh_session('192.0.2.163', 'alextan', 'Password123'), 'correct password is refused while locked out across sessions');
    assert_true(auth_attempt_in_fresh_session('192.0.2.164', 'alextan', 'Password123'), 'a different client is not locked out');
    assert_true(auth_attempt_in_fresh_session('192.0.2.163', 'drsmith', 'Password123'), 'another account on the same client is not locked out by typos on another account');
    logout();
    $_SERVER['REMOTE_ADDR'] = '192.0.2.163';
    auth_failure_store_change(static function (array &$entries): void {
        $entries[auth_user_key('alextan')]['started'] = time() - AUTH_FAILURE_WINDOW;
    });
    assert_true(auth_attempt_in_fresh_session('192.0.2.163', 'alextan', 'Password123'), 'login works once the lockout window resets');
    $remaining = auth_failure_store_change(static function (array &$entries): ?array {
        return $entries[auth_user_key('alextan')] ?? null;
    });
    assert_true($remaining === null, 'successful login clears the failure count for that login name');
    logout();

    // Spraying many usernames from one client still hits the looser per-client cap.
    $_SERVER['REMOTE_ADDR'] = '192.0.2.165';
    auth_clear_failures();
    for ($i = 0; $i < AUTH_MAX_CLIENT_FAILURES; $i++) {
        auth_record_failure('spray-user-' . $i);
    }
    assert_true(auth_is_limited('alextan'), 'per-client cap throttles a client that sprays many usernames');
    assert_true(!auth_attempt_in_fresh_session('192.0.2.165', 'alextan', 'Password123'), 'a capped client cannot log in to any account');
    assert_true(auth_attempt_in_fresh_session('192.0.2.166', 'alextan', 'Password123'), 'the per-client cap does not affect other clients');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.165';
    auth_clear_failures();
    assert_true(!auth_is_limited('alextan'), 'clearing with no name clears every counter from the client');
    logout();

    $authSource = file_get_contents(dirname(__DIR__) . '/clinic-base/lib/auth.php') ?: '';
    assert_true(preg_match('/function auth_failure_store_read\(.*?LOCK_SH/s', $authSource) === 1, 'read-only throttle checks take a shared lock');
    assert_true(preg_match('/function auth_is_limited\(.*?\n}/s', $authSource, $m) === 1 && !str_contains($m[0], 'store_change'), 'auth_is_limited never rewrites the store');

    $store = auth_failure_store_path();
    assert_true(dirname($store) !== dirname(__DIR__) . '/clinic-base', 'throttle store is outside the web application directory');
    assert_true(is_file($store), 'throttle store is persisted outside the web application directory');
    if (DIRECTORY_SEPARATOR !== '\\') {
        assert_true((fileperms($store) & 0077) === 0, 'throttle store is private on disk');
    }
});

test('login throttle fails open when the store cannot be used', static function (): void {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.170';
    $store = auth_failure_store_path();
    auth_clear_failures();
    @unlink($store);
    // A directory at the store path makes every fopen() fail, as an unreadable file would.
    assert_true(mkdir($store), 'store path can be blocked for the test');
    $logged = ini_set('error_log', rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ie4727-auth-failopen-' . getmypid() . '.log');
    try {
        auth_record_failure('alextan');
        assert_true(!auth_is_limited('alextan'), 'an unusable store never reports a lockout');
        assert_true(attempt_login('alextan', 'Password123'), 'login is allowed when the throttle store cannot be opened');
    } finally {
        @rmdir($store);
        if ($logged !== false) {
            @unlink((string) ini_get('error_log'));
            ini_set('error_log', $logged);
        }
        logout();
    }
});

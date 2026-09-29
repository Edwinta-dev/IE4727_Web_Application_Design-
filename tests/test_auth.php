<?php

declare(strict_types=1);

defined('ADMIN_USER') || define('ADMIN_USER', 'admin');
defined('ADMIN_HASH') || define('ADMIN_HASH', password_hash('Password123', PASSWORD_DEFAULT));

require_once dirname(__DIR__) . '/clinic-base/lib/auth.php';

test('authentication and role guards', static function (): void {
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
    assert_true(isset($_SESSION['remember_hash']), 'remember token hash is stored in the session');
    assert_true($_SESSION['remember_hash'] !== 'Password123', 'password is not stored as remember data');

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
        assert_true(!attempt_login('alextan', 'wrong password'), 'wrong password fails before lockout');
    }
    assert_true(!attempt_login('alextan', 'Password123'), 'correct password is refused while locked out');
    unset($_SESSION['auth_failures']);
    assert_true(attempt_login('alextan', 'Password123'), 'login works once the lockout window resets');
    logout();
});

<?php

declare(strict_types=1);

// The patient dashboard uses the signed-in user's id as a patient id, so it must
// be limited to patients. require_login() alone let a doctor with id N read
// patient N's appointments and notifications.
$root = dirname(__DIR__) . '/clinic-base/';
$auth = (string) file_get_contents($root . 'lib/auth.php');
$home = (string) file_get_contents($root . 'screens/patient/home.php');

assert_true(preg_match('/function require_patient\(\): void\s*\{[^}]*is_patient\(\)[^}]*401[^}]*403/s', $auth) === 1, 'require_patient() rejects guests with 401 and other roles with 403');
assert_true(strpos($home, 'require_patient();') !== false, 'patient/home.php calls require_patient()');
assert_true(strpos($home, 'require_login();') === false, 'patient/home.php no longer relies on require_login() alone');
assert_true(strpos($home, 'require_patient();') < strpos($home, 'current_user()'), 'guard runs before the user id is read');
echo "OK: patient dashboard is limited to patients\n";

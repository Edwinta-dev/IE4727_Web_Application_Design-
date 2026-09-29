<?php

declare(strict_types=1);

defined('ADMIN_USER') || define('ADMIN_USER', 'admin');
defined('ADMIN_HASH') || define('ADMIN_HASH', password_hash('Password123', PASSWORD_DEFAULT));

require_once dirname(__DIR__) . '/clinic-base/models/accounts.php';

$doctor = find_login('drsmith');
if ($doctor === null || $doctor['role'] !== 'doctor') {
    throw new RuntimeException('seeded doctor was not found by username');
}

$byEmail = find_login($doctor['email']);
if ($byEmail === null || $byEmail['id'] !== $doctor['id']) {
    throw new RuntimeException('seeded doctor was not found by email');
}

if (password_verify('wrong password', $doctor['hash'])) {
    throw new RuntimeException('wrong password was accepted');
}

$suffix = bin2hex(random_bytes(4));
$user = 'accounts_test_' . $suffix;
$email = $user . '@example.local';
$id = create_patient_account([
    'FullName' => 'Accounts Test Patient',
    'User' => $user,
    'Email' => $email,
    'password' => 'Secret123',
    'Allergies' => [],
]);

$created = find_login($user);
if ($created === null || $created['id'] !== $id || $created['hash'] === 'Secret123' || !password_verify('Secret123', $created['hash'])) {
    throw new RuntimeException('patient account was not stored with a password hash');
}

$duplicateRejected = false;
try {
    create_patient_account([
        'FullName' => 'Duplicate Account',
        'User' => $user,
        'Email' => 'other_' . $suffix . '@example.local',
        'password' => 'Secret123',
    ]);
} catch (PDOException $exception) {
    $duplicateRejected = true;
}

delete_patient($id);
if (!$duplicateRejected) {
    throw new RuntimeException('duplicate username was not rejected');
}

if (!defined('ADMIN_USER') || !defined('ADMIN_HASH') || !verify_admin((string) ADMIN_USER, 'Password123') || verify_admin((string) ADMIN_USER, 'wrong password')) {
    throw new RuntimeException('admin credential verification failed');
}

echo "PASS: account checks\n";

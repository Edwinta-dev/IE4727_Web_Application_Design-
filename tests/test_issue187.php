<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/validate.php';
require_once dirname(__DIR__) . '/clinic-base/models/accounts.php';
require_once dirname(__DIR__) . '/clinic-base/models/patients.php';

$root = dirname(__DIR__);
$choice = "I'd rather not disclose";
$page = file_get_contents($root . '/clinic-base/register.php');
$action = file_get_contents($root . '/clinic-base/actions/register.php');
$visit = file_get_contents($root . '/clinic-base/doctor/visit.php');
if ($page === false || $action === false || $visit === false) {
    throw new RuntimeException('Gender registration files could not be read');
}
assert_contains($page, $choice, 'patient Gender option');
assert_contains($action, "required|in:Male,Female,Other,$choice", 'server Gender rule');
assert_contains($visit, "e((string) (\$patient['Gender']", 'visit escapes stored Gender');

$rule = "required|in:Male,Female,Other,$choice";
if (validate(['Gender' => $choice], ['Gender' => $rule]) !== []) {
    throw new RuntimeException('Non-disclosure option failed server validation');
}
foreach (['', 'Undisclosed', 'Male,Other'] as $invalid) {
    if (!isset(validate(['Gender' => $invalid], ['Gender' => $rule])['Gender'])) {
        throw new RuntimeException('Invalid Gender passed server validation');
    }
}

$connection = db();
$connection->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(4));
    $id = create_patient_account([
        'FullName' => 'Gender Choice Test',
        'User' => 'gender187_' . $suffix,
        'Email' => 'gender187_' . $suffix . '@example.test',
        'password' => 'Password123',
        'Gender' => $choice,
        'Phone' => '81234567',
        'Allergies' => [],
    ]);
    $patient = find_patient($id);
    if ($patient === null || $patient['Gender'] !== $choice) {
        throw new RuntimeException('Gender did not round-trip through the database');
    }
} finally {
    $connection->rollBack();
}

echo "PASS: patient non-disclosure Gender option validates and round-trips\n";

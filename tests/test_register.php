<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/db.php';
require_once dirname(__DIR__) . '/clinic-base/lib/validate.php';

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/register.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/register.php');
$script = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/app.js');
if ($page === false || $action === false || $script === false) {
    throw new RuntimeException('registration files could not be read');
}

foreach (['name="FullName"', 'name="User"', 'name="Email"', 'name="Gender"', 'name="Phone"', 'name="Allergies"',
    'name="<?= e($field) ?>"', 'csrf_field()', 'onsubmit="return validateRegistrationForm(event)"'] as $needle) {
    assert_contains($page, $needle, 'registration page');
}
foreach (['csrf_check()', 'validate(', 'create_patient_account(', 'create_doctor_account(', 'beginTransaction()'] as $needle) {
    assert_contains($action, $needle, 'registration action');
}
foreach (['addEventListener', 'validateRegistrationForm', 'registrationFieldError', 'return false'] as $needle) {
    assert_contains($script, $needle, 'registration javascript');
}

$suffix = bin2hex(random_bytes(4));
$missingFieldUser = 'register_missing_' . $suffix;
$errors = validate(
    ['role' => 'patient', 'FullName' => '', 'User' => $missingFieldUser, 'Email' => $missingFieldUser . '@example.local', 'password' => 'Secret123', 'confirm_password' => 'Secret123', 'Gender' => 'Male', 'Phone' => '+65 61234567'],
    ['FullName' => 'required']
);
if ($errors === [] || q_one('SELECT `PatientID` FROM `patient` WHERE `User` = :user', ['user' => $missingFieldUser]) !== null) {
    throw new RuntimeException('missing registration field did not prevent account creation');
}

echo "PASS: registration structure and validation checks\n";

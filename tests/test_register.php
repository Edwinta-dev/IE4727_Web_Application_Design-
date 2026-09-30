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
    'name="<?= e($field) ?>"', 'csrf_field()', 'onsubmit="return validateRegistrationForm(event)"',
    'class="registration-layout"', 'class="registration-support"', 'What you’ll need', 'What happens next',
    'Clinic_Assisting_Elderly_woman.jpg', 'class="registration-role-options"'] as $needle) {
    assert_contains($page, $needle, 'registration page');
}
$styles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
if ($styles === false) {
    throw new RuntimeException('registration stylesheet could not be read');
}
foreach (['.registration-layout', 'grid-template-columns: minmax(0, 36rem)', '.registration-role-options', 'aspect-ratio: 4 / 3', '@media (max-width: 56rem)'] as $needle) {
    assert_contains($styles, $needle, 'registration layout styles');
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

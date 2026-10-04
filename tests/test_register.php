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
    'name="<?= e($field) ?>"', 'csrf_field()', 'id="registration-form" method="post"',
    'class="registration-layout"', 'class="registration-support"', 'What you’ll need', 'What happens next',
    'name="role" value="patient"', 'name="role" value="doctor"',
    'class="registration-role-switcher"', 'data-role-switch="patient"', 'data-role-switch="doctor"',
    'aria-label="Switch to doctor account"', 'class="registration-role-track" data-active-role="<?= e($registrationRole) ?>"',
    'errors_all()', 'class="error-summary"', 'data-error-for="role"', 'value="patient"<?= e($registrationRole === \'patient\' ? \' checked\' : \'\') ?>'] as $needle) {
    assert_contains($page, $needle, 'registration page');
}
$styles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
if (str_contains($page, 'registration-support-image')) {
    throw new RuntimeException('registration must not reuse a decorative hallway photo');
}
if ($styles === false) {
    throw new RuntimeException('registration stylesheet could not be read');
}
foreach (['.registration-layout', 'grid-template-columns: minmax(0, 36rem)', '.registration-role-switcher', 'transition: transform 190ms ease', 'prefers-reduced-motion: reduce', 'aspect-ratio: 4 / 3', '@media (max-width: 56rem)'] as $needle) {
    assert_contains($styles, $needle, 'registration layout styles');
}
foreach (['csrf_check()', 'validate(', 'create_patient_account(', 'create_doctor_account(', 'beginTransaction()'] as $needle) {
    assert_contains($action, $needle, 'registration action');
}
if (!preg_match('/\'Phone\'\s*=>\s*\[\'required\',\s*\'regex:([^\']+)\'\]/', $action, $phoneRule)) {
    throw new RuntimeException('patient phone must keep authoritative required and regex validation');
}
assert_contains($action, "\$errors['Phone'] = 'Use 7 to 20 characters:", 'specific server phone error');
foreach (['+65 6123 4567', '(65) 6123-4567', '61234567'] as $validPhone) {
    if (validate(['Phone' => $validPhone], ['Phone' => ['required', 'regex:' . $phoneRule[1]]]) !== []) {
        throw new RuntimeException('supported patient phone was rejected by PHP');
    }
}
foreach (['', '6123abc8', '6123/4567'] as $invalidPhone) {
    if (!isset(validate(['Phone' => $invalidPhone], ['Phone' => ['required', 'regex:' . $phoneRule[1]]])['Phone'])) {
        throw new RuntimeException('invalid patient phone passed PHP validation');
    }
}
assert_contains(file_get_contents(dirname(__DIR__) . '/clinic-base/partials/nav.php') ?: '', '$navigationRole', 'navigation role isolation');
foreach (['addEventListener', 'validateRegistrationForm', 'registrationFieldError', 'return false', 'data-role-switch', 'ArrowLeft', 'ArrowRight', 'setRegistrationRole(role)'] as $needle) {
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

$registrationRules = ['role' => 'required|in:patient,doctor', 'password' => 'required|min:8', 'confirm_password' => 'required|matches:password'];
foreach ([['role' => '', 'password' => 'Secret123', 'confirm_password' => 'Secret123'], ['role' => 'admin', 'password' => 'Secret123', 'confirm_password' => 'Secret123'], ['role' => 'patient', 'password' => 'Secret123', 'confirm_password' => 'Different123']] as $invalidRegistration) {
    if (validate($invalidRegistration, $registrationRules) === []) {
        throw new RuntimeException('missing/tampered role or mismatched passwords passed registration validation');
    }
}

echo "PASS: registration structure and validation checks\n";

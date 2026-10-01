<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'validate.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'accounts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/register.php');
}

csrf_check();

$data = [
    'role' => trim((string) ($_POST['role'] ?? '')),
    'FullName' => trim((string) ($_POST['FullName'] ?? '')),
    'User' => trim((string) ($_POST['User'] ?? '')),
    'Email' => trim((string) ($_POST['Email'] ?? '')),
    'password' => (string) ($_POST['password'] ?? ''),
    'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
    'Gender' => trim((string) ($_POST['Gender'] ?? '')),
    'Phone' => trim((string) ($_POST['Phone'] ?? '')),
    'Allergies' => trim((string) ($_POST['Allergies'] ?? '')),
    'Specialty' => trim((string) ($_POST['Specialty'] ?? '')),
    'Qualifications' => trim((string) ($_POST['Qualifications'] ?? '')),
    'WriteUp' => trim((string) ($_POST['WriteUp'] ?? '')),
    'Languages' => trim((string) ($_POST['Languages'] ?? '')),
];

$rules = [
    'role' => 'required|in:patient,doctor',
    'FullName' => 'required|max:100',
    'User' => 'required|max:100',
    'Email' => 'required|email|max:100',
    'password' => 'required|min:8',
    'confirm_password' => 'required|matches:password',
];
if ($data['role'] === 'doctor') {
    $rules += [
        'Specialty' => 'required|max:80',
        'Qualifications' => 'required|max:255',
        'WriteUp' => 'required|max:65535',
        'Languages' => 'required|max:120',
    ];
} else {
    $rules += [
        'Gender' => 'required|in:Male,Female,Other',
        'Phone' => ['required', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
    ];
}

$errors = validate($data, $rules);
if ($errors === [] && user_or_email_taken($data['User'])) {
    $errors['User'] = 'Choose a username that is not already registered';
}
if ($errors === [] && user_or_email_taken($data['Email'])) {
    $errors['Email'] = 'Use an email address that is not already registered';
}

if ($errors !== []) {
    validation_failed($errors, $data, '/register.php');
}

$connection = db();
try {
    $connection->beginTransaction();
    if ($data['role'] === 'doctor') {
        create_doctor_account($data);
    } else {
        $data['Allergies'] = $data['Allergies'] === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $data['Allergies']))));
        create_patient_account($data);
    }
    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    $errors = ['User' => 'That username or email is already registered'];
    validation_failed($errors, $data, '/register.php');
}

if (!attempt_login($data['User'], $data['password'])) {
    flash('Your account was created. Please sign in.', 'success');
    redirect('/index.php');
}

flash('Welcome to the Clinic Appointment Portal.', 'success');
redirect($data['role'] === 'doctor' ? '/doctor/home.php' : '/patient/home.php');

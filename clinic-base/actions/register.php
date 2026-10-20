<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'validate.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'accounts.php';
require_once dirname(__DIR__) . '/lib/specialties.php';

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
        'Specialty' => 'required|in:' . implode(',', canonical_specialties()),
        'Qualifications' => 'required|max:255',
        'WriteUp' => 'required|min:50|max:65535',
        'Languages' => 'required|max:120',
    ];
} else {
    $rules += [
        'Gender' => "required|in:Male,Female,Other,I'd rather not disclose",
        'Phone' => ['required', 'regex:/^[689][0-9]{7}$/D'],
    ];
}

$errors = validate($data, $rules);
if ($data['role'] === 'doctor') {
    $photoError = doctor_photo_error($_FILES['ProfilePhoto'] ?? null);
    if ($photoError !== null) {
        $errors['ProfilePhoto'] = $photoError;
    }
}
if ($data['role'] === 'doctor' && isset($errors['Specialty'])) {
    $errors['Specialty'] = 'Choose a specialty from the list of clinic services.';
}
if ($data['role'] === 'patient' && $data['Phone'] !== '' && isset($errors['Phone'])) {
    $errors['Phone'] = 'Enter 8 digits after +65, starting with 6, 8 or 9.';
}
if ($errors === [] && user_or_email_taken($data['User'])) {
    $errors['User'] = 'Choose a username that is not already registered';
}
if ($errors === [] && user_or_email_taken($data['Email'])) {
    $errors['Email'] = 'Use an email address that is not already registered';
}

if ($errors !== []) {
    validation_failed($errors, $data, '/register.php');
}

$savedPhoto = null;
if ($data['role'] === 'doctor') {
    $mime = (string) getimagesize($_FILES['ProfilePhoto']['tmp_name'])['mime'];
    $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    $filename = 'doctor-' . bin2hex(random_bytes(16)) . '.' . $extension;
    // Uploads live apart from the site's own images: git-ignored, outside the image size budget.
    $uploadDir = dirname(__DIR__) . '/' . DOCTOR_PHOTO_DIR;
    $savedPhoto = $uploadDir . '/' . $filename;
    if ((!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir))
        || !move_uploaded_file($_FILES['ProfilePhoto']['tmp_name'], $savedPhoto)) {
        validation_failed(['ProfilePhoto' => 'The profile photo could not be saved. Please try again.'], $data, '/register.php');
    }
    $data['ImageURL'] = DOCTOR_PHOTO_DIR . '/' . $filename;
}

$connection = db();
try {
    $connection->beginTransaction();
    if ($data['role'] === 'doctor') {
        $data['FullName'] = doctor_display_name($data['FullName']);
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
    if ($savedPhoto !== null) {
        unlink($savedPhoto);
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

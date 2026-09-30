<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/patient/home.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/appointment.php');
if ($page === false || $action === false) {
    throw new RuntimeException('patient appointment page or action could not be read');
}

foreach ([
    'require_login();',
    'appointments_for_patient($patientId, \'upcoming\')',
    'appointments_for_patient($patientId, \'past\')',
    'Specialty',
    'Reschedule',
    'appointment-action',
    'appointment-cancel',
    'name="cancel"',
    'csrf_field()',
    'confirm(',
    'empty-state',
    'Status\'] === \'Completed\'',
    'completed_appointment_for_patient',
] as $needle) {
    assert_contains($page, $needle);
}

foreach (['require_login();', 'csrf_check();', 'PatientID', 'cancel_appointment(', 'reschedule_appointment(', 'redirect('] as $needle) {
    assert_contains($action, $needle);
}

assert_true(strpos($page, 'SELECT ') === false, 'patient page must not contain SQL');
assert_true(strpos($action, 'fetch(') === false, 'appointment action must not use AJAX');

$book = file_get_contents(dirname(__DIR__) . '/clinic-base/book.php');
assert_true($book !== false && strpos($book, '/actions/appointment.php') !== false, 'reschedule uses appointment action');

echo "PASS: patient appointment page markup checks\n";

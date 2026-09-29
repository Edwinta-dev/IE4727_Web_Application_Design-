<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/doctor/home.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/appointment.php');
$book = file_get_contents(dirname(__DIR__) . '/clinic-base/book.php');
if ($page === false || $action === false || $book === false) {
    throw new RuntimeException('doctor page or appointment flow could not be read');
}

foreach ([
    'require_doctor();', 'appointments_for_doctor_day($doctorId, $date)',
    'type="date"', 'Future', 'Completed', 'No show', 'Reschedule',
    'Mark completed', 'Mark no-show', 'doctor/visit.php?appt=',
    'patient_history_for_doctor', 'empty-state', 'csrf_field()',
] as $needle) {
    assert_contains($page, $needle);
}
foreach (['is_doctor()', 'set_appointment_status(', 'reschedule_appointment($appointmentId, $slotId, is_doctor() ? \'doctor\' : \'patient\')'] as $needle) {
    assert_contains($action, $needle);
}
assert_contains($book, "is_doctor() ? 'doctor' : 'patient'");
assert_true(strpos($page, 'SELECT ') === false, 'doctor page must not contain SQL');
assert_true(strpos($action, 'fetch(') === false, 'doctor action must not use AJAX');

echo "PASS: doctor home markup checks\n";

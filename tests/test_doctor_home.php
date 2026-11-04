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
    'Start visit', 'Mark no-show', 'doctor/visit.php?appt=', 'row-actions',
    'patient_history_for_doctor', 'empty-state', 'csrf_field()',
] as $needle) {
    assert_contains($page, $needle);
}
foreach (['is_doctor()', 'set_appointment_status(', 'reschedule_appointment($appointmentId, $slotId, is_doctor() ? \'doctor\' : \'patient\', (int) $user[\'id\'], (int) $appointment[\'slotID\'])'] as $needle) {
    assert_contains($action, $needle);
}
assert_contains($book, "is_doctor() ? 'doctor' : 'patient'");
assert_true(strpos($page, 'SELECT ') === false, 'doctor page must not contain SQL');
assert_true(strpos($page, 'Mark completed') === false, 'Mark completed is gone: saving visit notes completes the appointment (#230)');
assert_true(strpos($page, 'Attendance actions open at') === false, 'the long unwrapped sentence is replaced by a short note (#224)');
assert_true(strpos($page, 'No upcoming appointments after this day') === false, 'misleading wording replaced (#224)');
assert_true(strpos($action, 'fetch(') === false, 'doctor action must not use AJAX');

echo "PASS: doctor home markup checks\n";

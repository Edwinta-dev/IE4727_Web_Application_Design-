<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/doctor/visit.php');
$appointments = file_get_contents(dirname(__DIR__) . '/clinic-base/models/appointments.php');
$booking = file_get_contents(dirname(__DIR__) . '/clinic-base/models/booking.php');
if ($page === false || $appointments === false || $booking === false) {
    throw new RuntimeException('visit files could not be read');
}

foreach ([
    'require_doctor();', 'csrf_check();', 'find_appointment(', 'DoctorID',
    'patient_history(', 'find_patient(', 'Allergies', 'Gender', 'Phone',
    'No previous visits recorded', 'name="Diagnosis"', 'name="Treatment"',
    'name="Prescription"', 'name="Remarks"', 'name="FollowUp"',
    'save_visit_notes(', 'doctor/home.php?date=', 'csrf_field()',
] as $needle) {
    assert_contains($page, $needle);
}

assert_contains($appointments, 'ORDER BY a.`appointmentDateTime` DESC', 'history newest first');
assert_contains($appointments, "a.`Status` = \'Completed\'", 'history only attended visits');
assert_contains($booking, "`Status` = 'Completed'", 'saving notes completes appointment');
assert_contains($booking, '`FollowUp` = :follow_up', 'saving notes stores follow-up flag');
assert_true(strpos($page, 'SELECT ') === false, 'visit page must not contain SQL');
assert_true(strpos($page, 'fetch(') === false, 'visit page must not use AJAX');

echo "PASS: doctor visit page checks\n";

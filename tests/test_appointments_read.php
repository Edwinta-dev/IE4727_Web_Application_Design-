<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';

$upcoming = appointments_for_patient(1, 'upcoming');
foreach ($upcoming as $appointment) {
    assert_true(
        strtotime((string) $appointment['appointmentDateTime']) >= time(),
        'upcoming appointments include a past datetime'
    );
    assert_true(
        in_array($appointment['Status'], ['Future', 'Rescheduled'], true),
        'upcoming appointments include an ineligible status'
    );
}

$past = appointments_for_patient(1, 'past');
foreach ($past as $appointment) {
    assert_true(
        (string) $appointment['Status'] !== 'Future' || strtotime((string) $appointment['appointmentDateTime']) < time(),
        'past appointments include an upcoming Future appointment'
    );
}

$history = patient_history(1);
foreach ($history as $appointment) {
    assert_eq($appointment['Status'], 'Completed', 'patient history status');
}

$all = admin_search();
$completed = admin_search(['status' => 'Completed']);
assert_true(count($all) >= count($completed), 'unfiltered admin search should include filtered rows');
foreach ($completed as $appointment) {
    assert_eq($appointment['Status'], 'Completed', 'admin status filter');
}

echo "PASS: appointment read model checks\n";

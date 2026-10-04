<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';
assert_eq(DB_NAME, 'ie4727db_test', 'seed regression uses isolated database');
assert_eq(q_val('SELECT DATABASE()'), 'ie4727db_test', 'resolved connection is isolated');

$previous = null;
for ($reset = 0; $reset < 2; $reset++) {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/db_reset.php') . ' --test 2>&1', $output, $exit);
    assert_eq($exit, 0, 'fresh seed reset succeeds');
    assert_true(in_array('Reset ie4727db_test (--test)', $output, true), 'reset confirms target');
    $counts = q_all('SELECT Status, COUNT(*) AS total FROM appointment GROUP BY Status ORDER BY Status');
    if ($previous !== null) assert_eq($counts, $previous, 'two resets produce consistent counts');
    $previous = $counts;
    assert_eq((int) q_val('SELECT COUNT(*) FROM appointment'), 27, 'all existing examples plus two pending visits');
    assert_eq((int) q_val('SELECT COUNT(*) FROM appointment WHERE CreatedAt IS NULL OR CreatedAt > appointmentDateTime OR CreatedAt > NOW()'), 0, 'genuine booking chronology');
    assert_eq((int) q_val('SELECT COUNT(*) FROM appointment a JOIN slots s ON s.slotID = a.slotID WHERE s.CreatedAt > a.CreatedAt'), 0, 'slot exists before booking');
    $pending = q_all("SELECT a.*, s.Status AS SlotStatus, s.DoctorID AS SlotDoctor, s.SlotDateTime, p.User AS PatientUser
        FROM appointment a JOIN doctor d ON d.DoctorID = a.DoctorID
        JOIN patient p ON p.PatientID = a.PatientID JOIN slots s ON s.slotID = a.slotID
        WHERE d.User = 'drsmith' AND a.Status = 'Future' AND a.appointmentDateTime < NOW()
        ORDER BY a.appointmentDateTime");
    assert_count($pending, 2, 'two actionable owned pending visits after each reset');
    assert_eq(array_column($pending, 'PatientUser'), ['alextan', 'bethong'], 'deterministic patient relations');
    $board = appointments_for_doctor_day((int) $pending[0]['DoctorID'], substr($pending[0]['appointmentDateTime'], 0, 10));
    assert_eq(array_column($board, 'appointmentID'), array_column($pending, 'appointmentID'), 'both visible through real board model');
    foreach ($pending as $row) {
        assert_true(appointment_outcome_eligible($row, (int) $row['DoctorID']), 'immediately actionable');
        assert_eq($row['SlotStatus'], 'Booked', 'linked slot booked');
        assert_eq($row['SlotDoctor'], $row['DoctorID'], 'slot owned by same doctor');
        assert_eq($row['SlotDateTime'], $row['appointmentDateTime'], 'exact slot time');
    }
}

$notices = q_all('SELECT * FROM notifications ORDER BY notificationID');
foreach (['Completed', 'No show'] as $index => $outcome) {
    $row = $pending[$index];
    assert_eq(set_appointment_status((int) $row['appointmentID'], $outcome, (int) $row['DoctorID']), true, 'seed outcome accepted');
    $saved = find_appointment((int) $row['appointmentID']);
    assert_eq($saved['Status'], $outcome, 'exact outcome enum');
    foreach (['PatientID', 'DoctorID', 'slotID', 'appointmentDateTime', 'CreatedAt', 'Diagnosis', 'Treatment', 'Prescription', 'FollowUp', 'Remarks'] as $field) {
        assert_eq($saved[$field], $row[$field], 'attendance preserves ' . $field);
    }
    assert_eq(q_val('SELECT Status FROM slots WHERE slotID = :id', ['id' => $row['slotID']]), 'Booked', 'attendance preserves booked slot');
    assert_eq(set_appointment_status((int) $row['appointmentID'], $outcome, (int) $row['DoctorID']), false, 'terminal attendance not reapplied');
}
assert_eq(q_all('SELECT * FROM notifications ORDER BY notificationID'), $notices, 'attendance emits no mail');
// Restore demo outcomes so later suite files still see the fresh fixtures.
foreach ($pending as $row) q('UPDATE appointment SET Status = :status WHERE appointmentID = :id', ['status' => 'Future', 'id' => $row['appointmentID']]);

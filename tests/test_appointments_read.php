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

test('patient dashboard separates cancelled visits from upcoming and past', static function (): void {
    q('INSERT INTO patient (FullName, User, HashPass, Email) VALUES (:name,:user,:hash,:email)', [
        'name' => 'Dashboard grouping test', 'user' => 'issue151-model',
        'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => 'issue151-model@example.local',
    ]);
    $patientId = (int) db()->lastInsertId();
    try {
        $expected = ['upcoming' => [], 'past' => [], 'cancelled' => []];
        foreach (['Future', 'Rescheduled', 'Cancelled', 'Completed', 'No show'] as $status) {
            foreach ([-2, 2] as $days) {
                q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status)
                   VALUES (1,:patient,TIMESTAMPADD(DAY,:days,NOW()),:status)',
                  ['patient' => $patientId, 'days' => $days, 'status' => $status]);
                $group = $status === 'Cancelled' ? 'cancelled'
                    : ($days > 0 && in_array($status, ['Future', 'Rescheduled'], true) ? 'upcoming' : 'past');
                $expected[$group][] = (int) db()->lastInsertId();
            }
        }
        foreach ($expected as $group => $ids) {
            $rows = appointments_for_patient($patientId, $group);
            $actual = array_map(static fn (array $row): int => (int) $row['appointmentID'], $rows);
            sort($ids);
            sort($actual);
            assert_eq($actual, $ids, $group . ' contains exactly the intended visits');
            foreach ($rows as $row) {
                assert_eq((int) $row['PatientID'], $patientId, 'group is restricted to the logged-in patient');
            }
        }
        assert_eq(count(appointments_for_patient($patientId)), 10, 'all visits retained');
    } finally {
        q('DELETE FROM patient WHERE PatientID = :id', ['id' => $patientId]);
    }
});

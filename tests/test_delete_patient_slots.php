<?php

declare(strict_types=1);

// Deleting a patient must release the slots of their upcoming appointments (they used to stay Booked
// forever), and must leave everything else alone: past visits, other patients, cancelled bookings.
require_once dirname(__DIR__) . '/clinic-base/models/accounts.php';

$doctorId = (int) q_val('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
assert_true($doctorId > 0, 'seed provides a doctor');

$created = ['patients' => [], 'slots' => []];
$slot = static function (string $when, string $status) use ($doctorId, &$created): int {
    q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor, :at, :status)', ['doctor' => $doctorId, 'at' => $when, 'status' => $status]);
    return $created['slots'][] = (int) db()->lastInsertId();
};
$patient = static function (string $name) use (&$created): int {
    $id = create_patient_account(['FullName' => 'Slot test ' . $name, 'User' => 'slottest-' . $name, 'Email' => 'slottest-' . $name . '@example.local',
        'password' => 'Password123', 'Gender' => 'Other', 'Phone' => '81234567', 'Allergies' => []]);
    return $created['patients'][] = $id;
};
$appointment = static function (int $patientId, ?int $slotId, string $when, string $status) use ($doctorId): int {
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`, `Status`) VALUES (:doctor, :patient, :slot, :at, :status)',
        ['doctor' => $doctorId, 'patient' => $patientId, 'slot' => $slotId, 'at' => $when, 'status' => $status]);
    return (int) db()->lastInsertId();
};
$status = static fn (int $slotId): string => (string) q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $slotId]);

try {
    $leaving = $patient('leaving');
    $staying = $patient('staying');

    // Far-future slots cannot collide with the rolling seed window; year-2000 slots are "past".
    $future = $slot('2099-03-02 09:00:00', 'Booked');        // Future appointment of the leaving patient
    $moved = $slot('2099-03-02 09:30:00', 'Booked');         // Rescheduled appointment of the leaving patient
    $cancelled = $slot('2099-03-02 10:00:00', 'Available');  // Cancelled earlier, slot already released
    $finished = $slot('2000-03-02 09:00:00', 'Booked');      // Completed visit in the past
    $awaiting = $slot('2000-03-02 09:30:00', 'Booked');      // Past visit still marked Future ("Awaiting outcome")
    $theirs = $slot('2099-03-02 11:00:00', 'Booked');        // Someone else's upcoming booking

    $appointment($leaving, $future, '2099-03-02 09:00:00', 'Future');
    $appointment($leaving, $moved, '2099-03-02 09:30:00', 'Rescheduled');
    $appointment($leaving, $cancelled, '2099-03-02 10:00:00', 'Cancelled');
    $appointment($leaving, $finished, '2000-03-02 09:00:00', 'Completed');
    $appointment($leaving, $awaiting, '2000-03-02 09:30:00', 'Future');
    $keptAppointment = $appointment($staying, $theirs, '2099-03-02 11:00:00', 'Future');

    delete_patient($leaving);

    assert_eq(status_of_patient($leaving), 0, 'the patient is gone');
    assert_eq((int) q_val('SELECT COUNT(*) FROM `appointment` WHERE `PatientID` = :id', ['id' => $leaving]), 0, 'their appointments are gone');
    assert_eq($status($future), 'Available', 'the slot of a Future appointment is released');
    assert_eq($status($moved), 'Available', 'the slot of a Rescheduled appointment is released');
    assert_eq($status($cancelled), 'Available', 'an already released slot stays Available');
    assert_eq($status($finished), 'Booked', 'a completed past visit keeps its slot as it was');
    assert_eq($status($awaiting), 'Booked', 'a past, unrecorded visit keeps its slot as it was');
    assert_eq($status($theirs), 'Booked', "another patient's booking is untouched");
    assert_eq((int) q_val('SELECT COUNT(*) FROM `appointment` WHERE `appointmentID` = :id', ['id' => $keptAppointment]), 1, "another patient's appointment is untouched");

    // The released slot can be booked again.
    $rebook = q('UPDATE `slots` SET `Status` = \'Booked\' WHERE `slotID` = :id AND `Status` = \'Available\'', ['id' => $future]);
    assert_eq($rebook->rowCount(), 1, 'the released slot can be claimed by someone else');

    // Edge cases: a patient with no appointments, and an id that does not exist, both succeed quietly.
    $empty = $patient('empty');
    delete_patient($empty);
    assert_eq(status_of_patient($empty), 0, 'a patient with no appointments is deleted');
    delete_patient(999999999);
} finally {
    foreach ($created['patients'] as $id) {
        q('DELETE FROM `patient` WHERE `PatientID` = :id', ['id' => $id]);
    }
    foreach ($created['slots'] as $id) {
        q('DELETE FROM `slots` WHERE `slotID` = :id', ['id' => $id]);
    }
}

function status_of_patient(int $id): int
{
    return (int) q_val('SELECT COUNT(*) FROM `patient` WHERE `PatientID` = :id', ['id' => $id]);
}

echo "OK: deleting a patient releases the slots of their upcoming appointments only\n";

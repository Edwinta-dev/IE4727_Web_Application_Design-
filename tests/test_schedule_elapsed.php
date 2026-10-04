<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';
require_once dirname(__DIR__) . '/clinic-base/models/slots.php';

$doctors = q_all('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 2');
$patientId = (int) q_val('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
$doctorId = (int) $doctors[0]['DoctorID'];
$foreignId = (int) $doctors[1]['DoctorID'];
$now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));

function schedule_elapsed_slot(int $doctorId, string $when, string $status): int
{
    q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :when, :status)',
        ['doctor_id' => $doctorId, 'when' => $when, 'status' => $status]);
    return (int) db()->lastInsertId();
}

$past = $now->modify('-2 days')->format('Y-m-d H:i:s');
$pastFree = schedule_elapsed_slot($doctorId, $past, 'Available');
$pastBlocked = schedule_elapsed_slot($doctorId, $now->modify('-3 days')->format('Y-m-d H:i:s'), 'Blocked');
$pastBooked = schedule_elapsed_slot($doctorId, $now->modify('-4 days')->format('Y-m-d H:i:s'), 'Booked');
q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`, `Status`)
   VALUES (:doctor_id, :patient_id, :slot_id, :when, \'Future\')',
   ['doctor_id' => $doctorId, 'patient_id' => $patientId, 'slot_id' => $pastBooked,
    'when' => $now->modify('-4 days')->format('Y-m-d H:i:s')]);
$appointmentId = (int) db()->lastInsertId();
$notificationsBefore = (int) q_val('SELECT COUNT(*) FROM `notifications`');

assert_eq(block_slot($pastFree, $doctorId), false, 'past free slot cannot block');
assert_eq(block_slot($pastBlocked, $doctorId), false, 'past blocked slot cannot block');
assert_eq(unblock_slot($pastBlocked, $doctorId), false, 'past blocked slot cannot reopen');
assert_eq(block_slot($pastBooked, $doctorId), false, 'past booked slot cannot cancel');
assert_eq(unblock_slot($pastBooked, $doctorId), false, 'past booked slot cannot reopen');
foreach ([$pastFree => 'Available', $pastBlocked => 'Blocked', $pastBooked => 'Booked'] as $id => $status) {
    assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $id]), $status, 'rejected edit keeps slot state');
}
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $appointmentId]), 'Future', 'past booking remains');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications`'), $notificationsBefore, 'rejected edits send no notices');

$future = schedule_elapsed_slot($doctorId, $now->modify('+40 days')->format('Y-m-d H:i:s'), 'Available');
assert_eq(block_slot($future, $foreignId), false, 'foreign doctor cannot block');
assert_eq(block_slot($future, $doctorId), true, 'future owned slot blocks');
assert_eq(unblock_slot($future, $foreignId), false, 'foreign doctor cannot reopen');
assert_eq(unblock_slot($future, $doctorId), true, 'future owned slot reopens');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $future]), 'Available', 'future state survives fresh query');

// Strict boundary: the slot must start after now, never at or before now.
$boundary = ['DoctorID' => $doctorId, 'SlotDateTime' => $now->format('Y-m-d H:i:s')];
assert_eq(schedule_slot_editable($boundary, $doctorId, $now), false, 'start instant is elapsed');
assert_eq(schedule_slot_editable($boundary, $doctorId, $now->modify('-1 second')), true, 'one second before start is editable');
assert_eq(schedule_slot_editable($boundary, $foreignId, $now->modify('-1 second')), false, 'foreign slot denied before cutoff');

// A form rendered before the cutoff must be rejected after the persisted time passes.
$crossing = schedule_elapsed_slot($doctorId, $now->modify('-1 second')->format('Y-m-d H:i:s'), 'Available');
assert_eq(block_slot($crossing, $doctorId), false, 'stale form cannot block elapsed slot');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $crossing]), 'Available', 'stale form keeps row');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications`'), $notificationsBefore, 'stale form sends no notices');

echo "PASS: schedule elapsed-slot guards\n";

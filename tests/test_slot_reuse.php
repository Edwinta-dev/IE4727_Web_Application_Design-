<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patients = q_all('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 2');
if ($doctor === null || count($patients) !== 2) {
    throw new RuntimeException('seed did not provide a doctor and two patients');
}

$doctorId = (int) $doctor['DoctorID'];
$patientA = (int) $patients[0]['PatientID'];
$patientB = (int) $patients[1]['PatientID'];
$slotDateTime = (new DateTimeImmutable('today'))->modify('+120 days')->setTime(23, 58)->format('Y-m-d H:i:s');
q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => $doctorId, 'slot_date_time' => $slotDateTime]
);
$slotId = (int) db()->lastInsertId();

$firstBooking = book_appointment($patientA, $slotId, 'Slot reuse first patient');
assert_true($firstBooking['ok'], 'patient A should book the test slot');
$historicalId = (int) $firstBooking['appointment_id'];
assert_true(cancel_appointment($historicalId, 'patient'), 'patient A should cancel');
$historical = q_one('SELECT * FROM `appointment` WHERE `appointmentID` = :id', ['id' => $historicalId]);
assert_eq($historical['Status'] ?? null, 'Cancelled', 'patient A appointment remains historical and cancelled');
$historicalNoticeCount = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $historicalId]);
$patientANoticesBeforeBlock = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = (SELECT `Email` FROM `patient` WHERE `PatientID` = :patient_id)', ['patient_id' => $patientA]);

$secondBooking = book_appointment($patientB, $slotId, 'Slot reuse second patient');
assert_true($secondBooking['ok'], 'patient B should rebook the released slot');
$activeId = (int) $secondBooking['appointment_id'];
$historicalBeforeBlock = q_one('SELECT * FROM `appointment` WHERE `appointmentID` = :id', ['id' => $historicalId]);
$patientBNoticesBeforeBlock = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = (SELECT `Email` FROM `patient` WHERE `PatientID` = :patient_id)', ['patient_id' => $patientB]);
$doctorNoticesBeforeBlock = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = (SELECT `Email` FROM `doctor` WHERE `DoctorID` = :doctor_id)', ['doctor_id' => $doctorId]);

assert_true(block_slot($slotId, $doctorId), 'doctor should block the rebooked slot');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $slotId]), 'Blocked', 'rebooked slot is blocked');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $activeId]), 'Cancelled', 'active patient B appointment is cancelled');
assert_eq(q_one('SELECT * FROM `appointment` WHERE `appointmentID` = :id', ['id' => $historicalId]), $historicalBeforeBlock, 'historical patient A appointment row is unchanged');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $historicalId]), $historicalNoticeCount, 'patient A appointment receives no new notices');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = (SELECT `Email` FROM `patient` WHERE `PatientID` = :patient_id)', ['patient_id' => $patientA]), $patientANoticesBeforeBlock, 'patient A receives no availability notice');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = (SELECT `Email` FROM `patient` WHERE `PatientID` = :patient_id)', ['patient_id' => $patientB]), $patientBNoticesBeforeBlock + 1, 'patient B receives the availability notice');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = (SELECT `Email` FROM `doctor` WHERE `DoctorID` = :doctor_id)', ['doctor_id' => $doctorId]), $doctorNoticesBeforeBlock + 1, 'doctor receives the availability notice');
$availabilityNotices = (int) q_val(
    "SELECT COUNT(*) FROM `notifications`
     WHERE `appointmentID` = :appointment_id
       AND `Subject` LIKE 'Appointment availability changed%'
       AND `recipient` IN (
           (SELECT `Email` FROM `patient` WHERE `PatientID` = :patient_id),
           (SELECT `Email` FROM `doctor` WHERE `DoctorID` = :doctor_id)
       )",
    ['appointment_id' => $activeId, 'patient_id' => $patientB, 'doctor_id' => $doctorId]
);
assert_eq($availabilityNotices, 2, 'availability notice is logged for patient B and the doctor');

echo "PASS: slot reuse cancellation notices target the active booking\n";

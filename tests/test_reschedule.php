<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

function reschedule_test_datetime(int $days): string
{
    return (new DateTimeImmutable('today'))
        ->modify('+' . $days . ' days')
        ->setTime(random_int(8, 17), random_int(0, 59), random_int(1, 59))
        ->format('Y-m-d H:i:s');
}

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
if ($doctor === null || $patient === null) {
    throw new RuntimeException('seed did not provide rescheduling participants');
}

$doctorId = (int) $doctor['DoctorID'];
$patientId = (int) $patient['PatientID'];

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => $doctorId, 'slot_date_time' => reschedule_test_datetime(10)]
);
$oldSlotId = (int) db()->lastInsertId();
$booking = book_appointment($patientId, $oldSlotId, 'Reschedule test');
assert_true($booking['ok'], 'reschedule test appointment should book');
$appointmentId = (int) $booking['appointment_id'];
$oldDateTime = (string) q_val(
    'SELECT `appointmentDateTime` FROM `appointment` WHERE `appointmentID` = :appointment_id',
    ['appointment_id' => $appointmentId]
);

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => $doctorId, 'slot_date_time' => reschedule_test_datetime(11)]
);
$newSlotId = (int) db()->lastInsertId();
$newDateTime = (string) q_val(
    'SELECT `SlotDateTime` FROM `slots` WHERE `slotID` = :slot_id',
    ['slot_id' => $newSlotId]
);

$rescheduled = reschedule_appointment($appointmentId, $newSlotId, 'patient');
assert_true($rescheduled['ok'], 'free slot should reschedule');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id', ['slot_id' => $oldSlotId]), 'Available', 'old slot released');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id', ['slot_id' => $newSlotId]), 'Booked', 'new slot booked');
assert_eq(q_val('SELECT `slotID` FROM `appointment` WHERE `appointmentID` = :appointment_id', ['appointment_id' => $appointmentId]), $newSlotId, 'appointment points to new slot');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :appointment_id', ['appointment_id' => $appointmentId]), 'Rescheduled', 'appointment status');
assert_eq(q_val('SELECT `appointmentDateTime` FROM `appointment` WHERE `appointmentID` = :appointment_id', ['appointment_id' => $appointmentId]), $newDateTime, 'appointment time');

$bodies = q_all(
    'SELECT `Body` FROM `notifications` WHERE `appointmentID` = :appointment_id ORDER BY `notificationID` DESC LIMIT 2',
    ['appointment_id' => $appointmentId]
);
assert_count($bodies, 2, 'reschedule notifications');
foreach ($bodies as $notification) {
    assert_contains((string) $notification['Body'], $oldDateTime, 'notification old time');
    assert_contains((string) $notification['Body'], $newDateTime, 'notification new time');
}

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => $doctorId, 'slot_date_time' => reschedule_test_datetime(12)]
);
$takenSlotId = (int) db()->lastInsertId();
$takenBooking = book_appointment($patientId, $takenSlotId, 'Taken slot');
assert_true($takenBooking['ok'], 'taken slot setup should book');
$beforeSlotId = (int) q_val('SELECT `slotID` FROM `appointment` WHERE `appointmentID` = :appointment_id', ['appointment_id' => $appointmentId]);
$taken = reschedule_appointment($appointmentId, $takenSlotId, 'doctor');
assert_eq($taken['ok'], false, 'taken slot should fail');
assert_eq($taken['error'], 'That slot has just been taken. Please choose another.', 'taken slot error');
assert_eq(q_val('SELECT `slotID` FROM `appointment` WHERE `appointmentID` = :appointment_id', ['appointment_id' => $appointmentId]), $beforeSlotId, 'failed reschedule keeps appointment');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id', ['slot_id' => $beforeSlotId]), 'Booked', 'failed reschedule keeps old slot');

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => $doctorId, 'slot_date_time' => reschedule_test_datetime(13)]
);
$rollbackSlotId = (int) db()->lastInsertId();
$failed = reschedule_appointment(999999, $rollbackSlotId, 'patient');
assert_eq($failed['ok'], false, 'invalid appointment should fail');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id', ['slot_id' => $rollbackSlotId]), 'Available', 'mid-transaction failure rolls back claim');

echo "PASS: reschedule checks\n";

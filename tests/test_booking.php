<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

function booking_test_datetime(int $minimumDays, int $maximumDays): string
{
    $date = (new DateTimeImmutable('today'))->modify('+' . random_int($minimumDays, $maximumDays) . ' days');

    return $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', random_int(8, 17), random_int(0, 59));
}

function booking_test_past_datetime(): string
{
    $date = (new DateTimeImmutable('2000-01-01'))->modify('+' . random_int(0, 1000) . ' days');

    return $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', random_int(8, 17), random_int(0, 59));
}

$doctor = q_one('SELECT `DoctorID`, `Email` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patient = q_one('SELECT `PatientID`, `Email` FROM `patient` ORDER BY `PatientID` LIMIT 1');
if ($doctor === null || $patient === null) {
    throw new RuntimeException('seed did not provide booking participants');
}

$suffix = bin2hex(random_bytes(5));
$futureDate = booking_test_datetime(2, 1000);
q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => (int) $doctor['DoctorID'], 'slot_date_time' => $futureDate]
);
$slotId = (int) db()->lastInsertId();

$booking = book_appointment((int) $patient['PatientID'], $slotId, 'Routine check ' . $suffix);
assert_true($booking['ok'], 'free slot should book');
assert_true($booking['appointment_id'] !== null, 'successful booking should return an appointment id');

$second = book_appointment((int) $patient['PatientID'], $slotId, 'Duplicate ' . $suffix);
assert_eq($second['ok'], false, 'second booking should fail');
assert_eq($second['error'], 'That slot has just been taken. Please choose another.', 'double booking error');
assert_eq(
    (int) q_val('SELECT COUNT(*) FROM `appointment` WHERE `slotID` = :slot_id', ['slot_id' => $slotId]),
    1,
    'double booking appointment count'
);

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Blocked\')',
    ['doctor_id' => (int) $doctor['DoctorID'], 'slot_date_time' => booking_test_datetime(2, 1000)]
);
$blockedId = (int) db()->lastInsertId();
$blocked = book_appointment((int) $patient['PatientID'], $blockedId, 'Blocked ' . $suffix);
assert_eq($blocked['ok'], false, 'blocked slot should fail');

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => (int) $doctor['DoctorID'], 'slot_date_time' => booking_test_past_datetime()]
);
$pastId = (int) db()->lastInsertId();
$past = book_appointment((int) $patient['PatientID'], $pastId, 'Past ' . $suffix);
assert_eq($past['ok'], false, 'past slot should fail');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id', ['slot_id' => $pastId]), 'Available', 'past slot release');

$notificationCount = (int) q_val(
    'SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :appointment_id',
    ['appointment_id' => $booking['appointment_id']]
);
assert_eq($notificationCount, 2, 'booking notification count');

q(
    'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :slot_date_time, \'Available\')',
    ['doctor_id' => (int) $doctor['DoctorID'], 'slot_date_time' => booking_test_datetime(2, 1000)]
);
$failureId = (int) db()->lastInsertId();
$failure = book_appointment(999999, $failureId, 'Invalid patient ' . $suffix);
assert_eq($failure['ok'], false, 'mid-transaction failure should fail cleanly');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :slot_id', ['slot_id' => $failureId]), 'Available', 'failed booking releases slot');

echo "PASS: booking checks\n";

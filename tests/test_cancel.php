<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

function cancel_test_datetime(int $days): string
{
    return (new DateTimeImmutable('today'))
        ->modify('+' . $days . ' days')
        // Keep fixtures outside the rolling seed window and deterministic so
        // repeated acceptance runs cannot collide with uq_slot.
        ->setTime(23, 59, 0)
        ->format('Y-m-d H:i:s');
}

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
if ($doctor === null || $patient === null) {
    throw new RuntimeException('seed did not provide cancellation participants');
}

$doctorId = (int) $doctor['DoctorID'];
$patientId = (int) $patient['PatientID'];

function cancel_test_slot(int $doctorId, int $days): int
{
    $candidate = cancel_test_datetime($days);
    $latest = q_val(
        'SELECT MAX(`SlotDateTime`)
         FROM `slots`
         WHERE `DoctorID` = :doctor_id
           AND `SlotDateTime` >= :minimum_date_time',
        [
            'doctor_id' => $doctorId,
            'minimum_date_time' => cancel_test_datetime(100),
        ]
    );

    if (is_string($latest) && strtotime($latest) >= strtotime($candidate)) {
        $candidate = (new DateTimeImmutable($latest))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');
    }

    q(
        'INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`)
         VALUES (:doctor_id, :slot_date_time, \'Available\')',
        ['doctor_id' => $doctorId, 'slot_date_time' => $candidate]
    );

    return (int) db()->lastInsertId();
}

$slotId = cancel_test_slot($doctorId, 100);
$booking = book_appointment($patientId, $slotId, 'Cancellation test');
assert_true($booking['ok'], 'cancellation setup should book');
$appointmentId = (int) $booking['appointment_id'];
$beforeNotifications = (int) q_val(
    'SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :appointment_id',
    ['appointment_id' => $appointmentId]
);
assert_true(cancel_appointment($appointmentId, 'patient'), 'cancellation should succeed');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $appointmentId]), 'Cancelled', 'cancel status');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $slotId]), 'Available', 'cancel releases slot');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $appointmentId]), $beforeNotifications + 2, 'cancel notifies both');

$freeSlotId = cancel_test_slot($doctorId, 101);
$notificationTotal = (int) q_val('SELECT COUNT(*) FROM `notifications`');
assert_true(block_slot($freeSlotId), 'free slot should block');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $freeSlotId]), 'Blocked', 'free slot blocked');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications`'), $notificationTotal, 'free block writes no notification');
assert_true(unblock_slot($freeSlotId), 'blocked slot should unblock');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $freeSlotId]), 'Available', 'slot unblocked');

$bookedSlotId = cancel_test_slot($doctorId, 102);
$booked = book_appointment($patientId, $bookedSlotId, 'Block booked test');
assert_true($booked['ok'], 'block booked setup should book');
$bookedAppointmentId = (int) $booked['appointment_id'];
$bookedNotifications = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $bookedAppointmentId]);
assert_true(block_slot($bookedSlotId), 'booked slot should block');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $bookedAppointmentId]), 'Cancelled', 'blocking booked slot cancels appointment');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $bookedSlotId]), 'Blocked', 'booked slot becomes blocked');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $bookedAppointmentId]), $bookedNotifications + 2, 'blocking booked slot notifies both');
assert_true(unblock_slot($bookedSlotId), 'cancelled blocked slot can be reopened');

$noShowSlotId = cancel_test_slot($doctorId, 103);
$noShow = book_appointment($patientId, $noShowSlotId, 'No show test');
assert_true($noShow['ok'], 'no show setup should book');
$noShowAppointmentId = (int) $noShow['appointment_id'];
set_appointment_status($noShowAppointmentId, 'No show');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $noShowAppointmentId]), 'No show', 'no show status');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $noShowSlotId]), 'Booked', 'no show keeps slot booked');

save_visit_notes($noShowAppointmentId, [
    'Diagnosis' => 'Routine review',
    'Prescription' => 'Rest',
    'Treatment' => 'Observation',
    'FollowUp' => 1,
    'Remarks' => 'Follow up in one week',
]);
$notes = q_one('SELECT `Status`, `Diagnosis`, `FollowUp`, `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $noShowAppointmentId]);
assert_eq($notes['Status'], 'Completed', 'visit notes complete appointment');
assert_eq($notes['FollowUp'], 1, 'follow up flag');

// Keep the script isolated when the complete suite runs after this test.
q(
    'DELETE FROM `notifications`
     WHERE `appointmentID` IN (:cancelled_id, :blocked_id, :no_show_id)',
    [
        'cancelled_id' => $appointmentId,
        'blocked_id' => $bookedAppointmentId,
        'no_show_id' => $noShowAppointmentId,
    ]
);
q(
    'DELETE FROM `appointment`
     WHERE `appointmentID` IN (:cancelled_id, :blocked_id, :no_show_id)',
    [
        'cancelled_id' => $appointmentId,
        'blocked_id' => $bookedAppointmentId,
        'no_show_id' => $noShowAppointmentId,
    ]
);
q(
    'DELETE FROM `slots`
     WHERE `slotID` IN (:cancelled_slot_id, :free_slot_id, :blocked_slot_id, :no_show_slot_id)',
    [
        'cancelled_slot_id' => $slotId,
        'free_slot_id' => $freeSlotId,
        'blocked_slot_id' => $bookedSlotId,
        'no_show_slot_id' => $noShowSlotId,
    ]
);

echo "PASS: cancellation, blocking, attendance and visit-note checks\n";

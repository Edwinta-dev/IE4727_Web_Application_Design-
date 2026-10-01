<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

function cancel_test_datetime(int $days): string
{
    return (new DateTimeImmutable('today'))
        ->modify(($days < 0 ? '' : '+') . $days . ' days')
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
assert_true(block_slot($freeSlotId, $doctorId), 'free slot should block');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $freeSlotId]), 'Blocked', 'free slot blocked');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications`'), $notificationTotal, 'free block writes no notification');
assert_true(unblock_slot($freeSlotId, $doctorId), 'blocked slot should unblock');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $freeSlotId]), 'Available', 'slot unblocked');

$bookedSlotId = cancel_test_slot($doctorId, 102);
$booked = book_appointment($patientId, $bookedSlotId, 'Block booked test');
assert_true($booked['ok'], 'block booked setup should book');
$bookedAppointmentId = (int) $booked['appointment_id'];
$bookedNotifications = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $bookedAppointmentId]);
assert_true(block_slot($bookedSlotId, $doctorId), 'booked slot should block');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $bookedAppointmentId]), 'Cancelled', 'blocking booked slot cancels appointment');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $bookedSlotId]), 'Blocked', 'booked slot becomes blocked');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $bookedAppointmentId]), $bookedNotifications + 2, 'blocking booked slot notifies both');
assert_true(unblock_slot($bookedSlotId, $doctorId), 'cancelled blocked slot can be reopened');

function cancel_test_past_appointment(int $doctorId, int $patientId, int $days): array
{
    $dateTime = cancel_test_datetime($days);
    q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor_id, :when, \'Booked\')', ['doctor_id' => $doctorId, 'when' => $dateTime]);
    $slotId = (int) db()->lastInsertId();
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`, `CreatedAt`, `Status`) VALUES (:doctor_id, :patient_id, :slot_id, :when, NOW(), \'Future\')', ['doctor_id' => $doctorId, 'patient_id' => $patientId, 'slot_id' => $slotId, 'when' => $dateTime]);
    return [$slotId, (int) db()->lastInsertId()];
}

[$noShowSlotId, $noShowAppointmentId] = cancel_test_past_appointment($doctorId, $patientId, -100);
assert_true(set_appointment_status($noShowAppointmentId, 'No show', $doctorId), 'eligible appointment can be marked no show');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $noShowAppointmentId]), 'No show', 'no show status');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $noShowSlotId]), 'Booked', 'no show keeps slot booked');

// Eligibility is inclusive at the persisted appointment start in APP_TIMEZONE.
$eligibilityNow = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
$eligibilityFixture = [
    'DoctorID' => $doctorId,
    'Status' => 'Future',
    'appointmentDateTime' => $eligibilityNow->format('Y-m-d H:i:s'),
];
assert_true(appointment_outcome_eligible($eligibilityFixture, $doctorId, $eligibilityNow), 'start instant is eligible');
assert_true(!appointment_outcome_eligible($eligibilityFixture, $doctorId, $eligibilityNow->modify('-1 second')), 'one second before start is ineligible');
assert_true(!appointment_outcome_eligible($eligibilityFixture, PHP_INT_MAX, $eligibilityNow), 'foreign doctor is ineligible');
assert_true(!appointment_outcome_eligible(array_merge($eligibilityFixture, ['Status' => 'Cancelled']), $doctorId, $eligibilityNow), 'cancelled appointment is ineligible');

$notesBefore = q_one('SELECT `Diagnosis`, `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $noShowAppointmentId]);
assert_eq(save_visit_notes($noShowAppointmentId, $doctorId, [
    'Diagnosis' => 'Routine review',
    'Prescription' => 'Rest',
    'Treatment' => 'Observation',
    'FollowUp' => 1,
    'Remarks' => 'Follow up in one week',
]), false, 'visit notes cannot overwrite a no-show outcome');
$notes = q_one('SELECT `Status`, `Diagnosis`, `FollowUp`, `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $noShowAppointmentId]);
assert_eq($notes['Status'], $notesBefore['Status'], 'rejected visit keeps status');
assert_eq($notes['Diagnosis'], $notesBefore['Diagnosis'], 'rejected visit keeps notes');

[$completedSlotId, $completedId] = cancel_test_past_appointment($doctorId, $patientId, -101);
assert_true(set_appointment_status($completedId, 'Completed', $doctorId), 'eligible appointment can be completed');
assert_eq(q_val('SELECT `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $completedId]), 'Completed', 'completed enum is exact');

[$visitSlotId, $visitId] = cancel_test_past_appointment($doctorId, $patientId, -102);
assert_true(save_visit_notes($visitId, $doctorId, [
    'Diagnosis' => 'Visit-save review',
    'Prescription' => 'Continue current care',
    'Treatment' => 'Review completed',
    'FollowUp' => 1,
    'Remarks' => 'Return in one week',
]), 'eligible past visit can be saved');
$savedVisit = q_one('SELECT `Status`, `Diagnosis`, `FollowUp`, `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $visitId]);
assert_eq($savedVisit['Status'], 'Completed', 'eligible visit-save sets exact Completed enum');
assert_eq($savedVisit['Diagnosis'], 'Visit-save review', 'eligible visit-save stores notes');
assert_eq((int) $savedVisit['FollowUp'], 1, 'eligible visit-save stores follow-up flag');

$futureSlotId = cancel_test_slot($doctorId, 1);
$futureBooking = book_appointment($patientId, $futureSlotId, 'Future outcome test');
assert_true($futureBooking['ok'], 'future setup should book');
$futureId = (int) $futureBooking['appointment_id'];
$beforeRows = q_one('SELECT `Status`, `Diagnosis`, `Prescription`, `Treatment`, `FollowUp`, `Remarks`, `slotID` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $futureId]);
$beforeSlotStatus = q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $futureSlotId]);
$beforeAppointmentNotifications = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $futureId]);
$beforeNotifications = (int) q_val('SELECT COUNT(*) FROM `notifications`');
assert_eq(set_appointment_status($futureId, 'Completed', $doctorId), false, 'future appointment cannot complete');
assert_eq(set_appointment_status($futureId, 'No show', $doctorId), false, 'future appointment cannot become no-show');
assert_eq(save_visit_notes($futureId, $doctorId, ['Diagnosis' => 'tampered']), false, 'future visit cannot complete through notes');
assert_eq(set_appointment_status($futureId, 'Completed', PHP_INT_MAX), false, 'foreign doctor cannot set outcome');
assert_eq(save_visit_notes($futureId, PHP_INT_MAX, ['Diagnosis' => 'foreign']), false, 'foreign doctor cannot save visit notes');
$afterRows = q_one('SELECT `Status`, `Diagnosis`, `Prescription`, `Treatment`, `FollowUp`, `Remarks`, `slotID` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $futureId]);
assert_eq($afterRows, $beforeRows, 'rejected outcome leaves appointment unchanged');
assert_eq(q_val('SELECT `Status` FROM `slots` WHERE `slotID` = :id', ['id' => $futureSlotId]), $beforeSlotStatus, 'rejected outcome leaves slot unchanged');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $futureId]), $beforeAppointmentNotifications, 'rejected outcomes add no appointment notifications');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications`'), $beforeNotifications, 'rejected outcomes add no notifications');
q("UPDATE `appointment` SET `Status` = 'Cancelled' WHERE `appointmentID` = :id", ['id' => $futureId]);
assert_eq(set_appointment_status($futureId, 'No show', $doctorId), false, 'cancelled status cannot be overwritten');

// Keep the script isolated when the complete suite runs after this test.
q(
    'DELETE FROM `notifications`
     WHERE `appointmentID` IN (:cancelled_id, :blocked_id, :no_show_id, :completed_id, :visit_id, :future_id)',
    [
        'cancelled_id' => $appointmentId,
        'blocked_id' => $bookedAppointmentId,
        'no_show_id' => $noShowAppointmentId,
        'completed_id' => $completedId,
        'visit_id' => $visitId,
        'future_id' => $futureId,
    ]
);
q(
    'DELETE FROM `appointment`
     WHERE `appointmentID` IN (:cancelled_id, :blocked_id, :no_show_id, :completed_id, :visit_id, :future_id)',
    [
        'cancelled_id' => $appointmentId,
        'blocked_id' => $bookedAppointmentId,
        'no_show_id' => $noShowAppointmentId,
        'completed_id' => $completedId,
        'visit_id' => $visitId,
        'future_id' => $futureId,
    ]
);
q(
    'DELETE FROM `slots`
     WHERE `slotID` IN (:cancelled_slot_id, :free_slot_id, :blocked_slot_id, :no_show_slot_id, :completed_slot_id, :visit_slot_id, :future_slot_id)',
    [
        'cancelled_slot_id' => $slotId,
        'free_slot_id' => $freeSlotId,
        'blocked_slot_id' => $bookedSlotId,
        'no_show_slot_id' => $noShowSlotId,
        'completed_slot_id' => $completedSlotId,
        'visit_slot_id' => $visitSlotId,
        'future_slot_id' => $futureSlotId,
    ]
);

echo "PASS: cancellation, blocking, attendance and visit-note checks\n";

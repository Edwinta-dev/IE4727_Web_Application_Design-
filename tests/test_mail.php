<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/mail.php';
require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';
require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

$recipient = 'mail_test_' . bin2hex(random_bytes(4)) . '@example.local';
[$subject, $body] = mail_booking_confirmed('Test Patient', 'Dr Test', '2099-10-01', '10:30');
$notificationId = send_mail($recipient, $subject, $body);

$row = q_one(
    'SELECT `recipient`, `Subject`, `Body`, `deliveryStatus`
     FROM `notifications`
     WHERE `notificationID` = :notification_id',
    ['notification_id' => $notificationId]
);

if ($row === null
    || $row['recipient'] !== $recipient
    || $row['Subject'] !== $subject
    || $row['Body'] !== $body
    || !in_array($row['deliveryStatus'], ['logged', 'sent', 'failed'], true)) {
    throw new RuntimeException('mail notification was not logged correctly');
}

if (strpos($body, 'Dr Test') === false || strpos($body, '10:30') === false) {
    throw new RuntimeException('booking template omitted the doctor or appointment time');
}

$recent = recent_notifications(1);
if ($recent === [] || (int) $recent[0]['notificationID'] !== $notificationId) {
    throw new RuntimeException('recent notifications did not return the new row');
}

echo "PASS: mail checks\n";

// Exercise real notification producers, not just the standalone templates.
$mailDoctor = q_one('SELECT `DoctorID`, `FullName`, `Email` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$mailPatient = q_one('SELECT `PatientID`, `FullName`, `Email` FROM `patient` ORDER BY `PatientID` LIMIT 1');
$mailSlots = q_all(
    "SELECT `slotID`, `SlotDateTime` FROM `slots`
     WHERE `DoctorID` = :doctor_id AND `Status` = 'Available' AND `SlotDateTime` > NOW()
       AND TIME(`SlotDateTime`) = '09:00:00'
     ORDER BY `SlotDateTime` LIMIT 4",
    ['doctor_id' => (int) $mailDoctor['DoctorID']]
);
assert_count($mailSlots, 4, 'mail workflow slots');
$mailBooking = book_appointment((int) $mailPatient['PatientID'], (int) $mailSlots[0]['slotID'], 'Mail regression');
assert_true($mailBooking['ok'], 'mail booking');
$mailAppointmentId = (int) $mailBooking['appointment_id'];
assert_true(reschedule_appointment($mailAppointmentId, (int) $mailSlots[1]['slotID'], 'patient')['ok'], 'patient reschedule mail');
assert_true(reschedule_appointment($mailAppointmentId, (int) $mailSlots[2]['slotID'], 'doctor')['ok'], 'doctor reschedule mail');
assert_true(cancel_appointment($mailAppointmentId, 'patient'), 'patient cancellation mail');
$mailBlockedBooking = book_appointment((int) $mailPatient['PatientID'], (int) $mailSlots[3]['slotID'], 'Blocked mail regression');
assert_true($mailBlockedBooking['ok'], 'booking to block');
$mailBlockedAppointmentId = (int) $mailBlockedBooking['appointment_id'];
assert_true(block_slot((int) $mailSlots[3]['slotID'], (int) $mailDoctor['DoctorID']), 'availability cancellation mail');
$mailRows = q_all(
    'SELECT `recipient`, `Subject`, `Body`, `appointmentID`, `deliveryStatus` FROM `notifications`
     WHERE `appointmentID` IN (:appointment_id, :blocked_id) ORDER BY `notificationID`',
    ['appointment_id' => $mailAppointmentId, 'blocked_id' => $mailBlockedAppointmentId]
);
assert_count($mailRows, 12, 'two recipients for every mail event');
foreach ($mailRows as $mailIndex => $mailRow) {
    $mailIsDoctor = $mailIndex % 2 === 1;
    $mailRecipient = $mailIsDoctor ? $mailDoctor : $mailPatient;
    assert_eq($mailRow['recipient'], $mailRecipient['Email'], 'mail recipient address');
    assert_contains($mailRow['Body'], 'Dear ' . $mailRecipient['FullName'] . ',', 'recipient greeting');
    assert_contains($mailRow['Body'], $mailDoctor['FullName'], 'doctor name');
    assert_contains($mailRow['Body'], APP_NAME, 'clinic name');
    assert_true(!preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $mailRow['Body']), 'no raw SQL datetime');
    assert_true(in_array($mailRow['deliveryStatus'], ['logged', 'sent', 'failed'], true), 'delivery recorded');
    if ($mailIsDoctor) {
        assert_contains($mailRow['Body'], $mailPatient['FullName'], 'doctor copy names patient');
        assert_true(!str_contains($mailRow['Body'], 'with ' . $mailDoctor['FullName']), 'doctor copy avoids appointment with self');
    }
    $mailSlotIndex = match (true) {
        $mailIndex < 2 => 0,
        $mailIndex < 4 => 1,
        $mailIndex < 8 => 2,
        default => 3,
    };
    $mailTimestamp = strtotime($mailSlots[$mailSlotIndex]['SlotDateTime']);
    assert_contains($mailRow['Body'], date('d M Y', $mailTimestamp) . ' at ' . date('g:i A', $mailTimestamp), 'formatted appointment time');
    if ($mailIndex >= 2 && $mailIndex < 6) {
        $mailOldTimestamp = strtotime($mailSlots[$mailSlotIndex - 1]['SlotDateTime']);
        assert_contains($mailRow['Body'], date('d M Y', $mailOldTimestamp) . ' at ' . date('g:i A', $mailOldTimestamp), 'formatted old time');
        assert_contains($mailRow['Body'], 'by the ' . ($mailIndex < 4 ? 'patient' : 'doctor'), 'reschedule actor');
    }
    if ($mailIndex >= 10) {
        assert_eq($mailRow['Subject'], 'Appointment availability changed - ' . APP_NAME, 'availability subject');
        assert_contains($mailRow['Body'], 'no longer available', 'availability reason');
        if (!$mailIsDoctor) assert_contains($mailRow['Body'], 'book another', 'patient rebooking hint');
    }
}
foreach ([mail_booking_confirmed('Patient', 'Dr Test', '2099-10-01', '10:30'),
          mail_cancelled('Patient', 'Dr Test', '2099-10-01', '10:30'),
          mail_availability_cancelled('Patient', 'Dr Test', '2099-10-01', '10:30')] as $mailTemplate) {
    assert_contains($mailTemplate[1], '01 Oct 2099 at 10:30 AM', 'split date/time inputs are formatted');
}
echo "OK: recipient-specific mail workflows\n";


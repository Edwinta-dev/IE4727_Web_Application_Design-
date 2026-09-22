<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/mail.php';
require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';

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


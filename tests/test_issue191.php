<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';
require_once dirname(__DIR__) . '/clinic-base/lib/mail.php';

[, $body] = mail_cancelled('Alex', 'Dr. Tan', '2026-10-20', '09:00:00');
assert_contains($body, 'Clinic: ' . APP_NAME, 'stored bodies sign off with the "Clinic:" prefix');
$shown = patient_notification_body($body);
assert_true(str_ends_with($shown, "\nClinic Appointment Service"), 'sign-off shows the service name');
assert_true(strpos($shown, 'Clinic:') === false, 'no "Clinic:" prefix is displayed');
assert_eq(patient_notification_body("Dear Alex,\r\nClinic: Clinic"), "Dear Alex,\r\nClinic Appointment Service", 'historical CRLF sign-off');
assert_eq(patient_notification_body('Visit the Clinic: room 3.'), 'Visit the Clinic: room 3.', 'mid-line text is left alone');

db()->beginTransaction();
try {
    $email = 'issue191@example.local';
    q('INSERT INTO patient (FullName, User, HashPass, Email) VALUES (:name, :user, :hash, :email)',
        ['name' => 'Sender test', 'user' => 'issue191-patient', 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => $email]);
    $patientId = (int) db()->lastInsertId();
    $notificationId = log_notification($email, 'Sender display', $body);
    $rows = notifications_for_patient($patientId);
    assert_eq((int) $rows[0]['notificationID'], $notificationId, 'queried the inserted notification');
    assert_eq(q_val('SELECT Body FROM notifications WHERE notificationID = :id', ['id' => $notificationId]), $body, 'stored body is not rewritten');
} finally {
    db()->rollBack();
}

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/patient/home.php');
assert_true($page !== false, 'patient page is readable');
assert_contains($page, "nl2br(e(patient_notification_body((string) \$notification['Body'])), false)", 'sign-off is mapped, then escaped, at render time');

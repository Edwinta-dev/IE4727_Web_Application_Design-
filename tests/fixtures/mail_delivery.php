<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/assert.php';

if (getenv('CLINIC_DB_NAME') !== 'ie4727db_test') {
    throw new RuntimeException('Mail regression requires ie4727db_test');
}

$mode = $argv[1] ?? 'default';
if ($mode !== 'default') {
    define('MAIL_DELIVERY', $mode);
}
require_once dirname(__DIR__, 2) . '/clinic-base/models/booking.php';
assert_eq(DB_NAME, 'ie4727db_test', 'resolved database');
assert_eq(MAIL_DELIVERY, $mode === 'default' ? 'on' : $mode, 'delivery configuration');

// The child disables the built-in mail function, so this double never delivers
// off-box and verifies the notification exists before every attempted delivery.
$attempts = 0;
$deliveryRows = [];
if (!function_exists('mail')) {
    function mail(string $to, string $subject, string $message, string $headers): bool
    {
        global $attempts, $deliveryRows;
        $attempts++;
        $deliveryRows[] = [recent_notifications(1)[0], $to, $subject, $message, $headers];
        return ($GLOBALS['argv'][2] ?? '') === 'sent';
    }
} else {
    throw new RuntimeException('Disable built-in mail for this fixture');
}

$slot = q_one("SELECT `slotID` FROM `slots` WHERE `Status` = 'Available' AND `SlotDateTime` > NOW() ORDER BY `SlotDateTime`, `slotID` LIMIT 1");
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
$start = microtime(true);
$booking = book_appointment((int) $patient['PatientID'], (int) $slot['slotID'], 'Mail delivery regression');
$elapsed = microtime(true) - $start;
assert_true($booking['ok'], 'booking succeeds');
$id = (int) $booking['appointment_id'];
try {
    $rows = notifications_for_appointment($id);
    assert_count($rows, 2, 'patient and doctor notifications');
    assert_eq($attempts, $mode === 'off' ? 0 : 2, 'mail attempt count');
    foreach ($deliveryRows as [$logged, $to, $subject, $message, $headers]) {
        assert_eq($logged['recipient'], $to, 'row before delivery');
        assert_eq($logged['Subject'], $subject, 'logged subject');
        assert_eq($logged['Body'], $message, 'logged body');
        assert_eq($logged['deliveryStatus'], 'logged', 'row-first status');
        assert_eq($headers, 'From: ' . CLINIC_EMAIL, 'sender header');
    }
    foreach ($rows as $row) {
        assert_eq((int) $row['appointmentID'], $id, 'appointment link');
        assert_eq($row['deliveryStatus'], $mode === 'off' ? 'logged' : ($argv[2] ?? 'failed'), 'delivery status');
        assert_true($row['recipient'] !== '' && $row['Subject'] !== '' && $row['Body'] !== '', 'notification content preserved');
    }
    if ($mode === 'off') {
        assert_true($elapsed < 1, 'delivery-off booking under one second: ' . $elapsed);
    }
    echo 'OK: ' . $mode . ' booking ' . number_format($elapsed, 3) . 's; ' . $attempts . " mail attempts\n";
} finally {
    q('DELETE FROM `notifications` WHERE `appointmentID` = :id', ['id' => $id]);
    q('DELETE FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]);
    q("UPDATE `slots` SET `Status` = 'Available' WHERE `slotID` = :id", ['id' => (int) $slot['slotID']]);
}

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
        $deliveryRows[] = [q_one('SELECT `recipient`, `Subject`, `Body`, `deliveryStatus` FROM `notifications` WHERE `recipient` = :to AND `Subject` = :subject ORDER BY `notificationID` DESC LIMIT 1', ['to' => $to, 'subject' => $subject]), $to, $subject, $message, $headers];
        $second = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $visible = $second->prepare('SELECT COUNT(*) FROM `appointment` a JOIN `notifications` n ON n.`appointmentID` = a.`appointmentID` WHERE n.`Subject` = ? AND n.`recipient` = ?');
        $visible->execute([$subject, $to]);
        assert_true((int) $visible->fetchColumn() > 0, 'second connection sees committed appointment and notification');
        assert_true(!db()->inTransaction(), 'mail transport runs after commit');
        if (($GLOBALS['argv'][2] ?? '') === 'throw') throw new RuntimeException('simulated transport exception');
        return ($GLOBALS['argv'][2] ?? '') === 'sent';
    }
} else {
    throw new RuntimeException('Disable built-in mail for this fixture');
}

$slot = q_one("SELECT `slotID` FROM `slots` WHERE `Status` = 'Available' AND `SlotDateTime` > NOW() ORDER BY `SlotDateTime`, `slotID` LIMIT 1");
$rollbackSlot = q_one("SELECT `slotID` FROM `slots` WHERE `Status` = 'Available' AND `SlotDateTime` > NOW() AND `slotID` <> :id ORDER BY `SlotDateTime`, `slotID` LIMIT 1", ['id' => (int) $slot['slotID']]);
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
$beforeNotifications = (int) q_val('SELECT COUNT(*) FROM `notifications`');
$failed = book_appointment(PHP_INT_MAX, (int) $rollbackSlot['slotID'], 'Forced rollback before commit');
assert_true(!$failed['ok'], 'forced booking failure rolls back');
assert_eq($attempts, 0, 'rollback sends no mail');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications`'), $beforeNotifications, 'rollback leaves no notification rows');
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
        assert_eq($row['deliveryStatus'], $mode === 'off' ? 'logged' : (in_array(($argv[2] ?? ''), ['sent'], true) ? 'sent' : 'failed'), 'delivery status');
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

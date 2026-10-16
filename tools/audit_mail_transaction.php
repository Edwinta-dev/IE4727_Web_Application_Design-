<?php

declare(strict_types=1);

putenv('CLINIC_DB_NAME=ie4727db_test');
putenv('MAIL_DELIVERY=on');
require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

if (DB_NAME !== 'ie4727db_test') throw new RuntimeException('Probe is restricted to ie4727db_test.');
$seen = [];
// Run with -d disable_functions=mail: this double replaces mail() as tests/fixtures/mail_delivery.php does.
if (function_exists('mail')) throw new RuntimeException('Run with php -d disable_functions=mail');
if (!function_exists('mail')) {
    function mail(string $to, string $subject, string $message, string $headers = ''): bool
    {
        global $seen;
        $second = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $check = $second->prepare('SELECT COUNT(*) FROM `notifications` WHERE `recipient` = ? AND `Subject` = ?');
        $check->execute([$to, $subject]);
        $visible = (int) $check->fetchColumn() > 0;
        $seen[] = !db()->inTransaction() && $visible;
        echo 'inTransaction=' . (db()->inTransaction() ? 'true' : 'false') . PHP_EOL;
        return false;
    }
}
$slot = q_one("SELECT `slotID` FROM `slots` WHERE `Status` = 'Available' AND `SlotDateTime` > NOW() ORDER BY `SlotDateTime`, `slotID` LIMIT 1");
$patient = q_one('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
if ($slot === null || $patient === null) throw new RuntimeException('Test seed is missing a patient or available future slot.');
$result = book_appointment((int) $patient['PatientID'], (int) $slot['slotID'], 'Mail transaction audit probe');
if (!$result['ok'] || count($seen) !== 2 || in_array(false, $seen, true)) throw new RuntimeException('Mail transaction probe failed.');
q('DELETE FROM `notifications` WHERE `appointmentID` = :id', ['id' => $result['appointment_id']]);
q('DELETE FROM `appointment` WHERE `appointmentID` = :id', ['id' => $result['appointment_id']]);
q("UPDATE `slots` SET `Status` = 'Available' WHERE `slotID` = :id", ['id' => $slot['slotID']]);
echo "OK: committed appointment and notifications visible before delivery\n";

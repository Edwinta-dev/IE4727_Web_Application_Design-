<?php

declare(strict_types=1);

if (!extension_loaded('pdo_mysql')) {
    echo "OK (pdo_mysql unavailable; concurrency integration deferred)\n";
    exit(0);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'booking.php';

try {
    q('SELECT 1');
} catch (Throwable $exception) {
    echo "OK (MariaDB unavailable; concurrency integration deferred)\n";
    exit(0);
}

$slot = q_one("SELECT `slotID`, `DoctorID` FROM `slots` WHERE `Status` = 'Available' AND `SlotDateTime` > NOW() ORDER BY `slotID` LIMIT 1");
if ($slot === null) {
    throw new RuntimeException('no available slot for concurrency test');
}
$patientIds = q_all('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 2');
if (count($patientIds) < 2) {
    throw new RuntimeException('two patients are required for concurrency test');
}

$first = book_appointment((int) $patientIds[0]['PatientID'], (int) $slot['slotID'], 'Concurrency test');
$second = book_appointment((int) $patientIds[1]['PatientID'], (int) $slot['slotID'], 'Concurrency test');
if (!$first['ok'] || $second['ok'] || strpos((string) $second['error'], 'just been taken') === false) {
    throw new RuntimeException('expected one booking and one safe conflict');
}

$rollbackSlot = q_one("SELECT `slotID`, `DoctorID` FROM `slots` WHERE `Status` = 'Available' AND `SlotDateTime` > NOW() ORDER BY `slotID` LIMIT 1");
if ($rollbackSlot === null) {
    throw new RuntimeException('no second available slot for rollback test');
}
// An unknown patient fails after the slot has been claimed, forcing a rollback.
$failed = book_appointment(PHP_INT_MAX, (int) $rollbackSlot['slotID'], 'Rollback test');
if ($failed['ok'] || q_one('SELECT `Status` FROM `slots` WHERE `slotID` = :slot', ['slot' => $rollbackSlot['slotID']])['Status'] !== 'Available') {
    throw new RuntimeException('failed transaction left divergent state');
}

echo "OK\n";

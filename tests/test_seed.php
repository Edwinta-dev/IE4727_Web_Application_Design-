<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';

$root = dirname(__DIR__);
$seed = file_get_contents($root . '/schema/003_seed.sql');
if ($seed === false) {
    throw new RuntimeException('seed file is unreadable');
}

foreach (['INSERT INTO `doctor`', 'INSERT INTO `patient`', 'INSERT INTO `slots`', 'INSERT INTO `appointment`', 'Password123', '10:30:00'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('seed is missing ' . $needle);
    }
}

if (substr_count($seed, "'Completed'") < 2 || substr_count($seed, "'No show'") < 1 || substr_count($seed, "'Cancelled'") < 1 || substr_count($seed, "'Future'") < 1) {
    throw new RuntimeException('seed does not cover appointment states');
}

$rows = q_all(
    'SELECT `recipient`, `appointmentID`, `deliveryStatus`, `Body`, `SentAt`
     FROM `notifications` WHERE `Subject` = :subject
     ORDER BY `SentAt` DESC, `notificationID` DESC',
    ['subject' => 'Demo appointment notice']
);
assert_count($rows, 2, 'seed has two synthetic outbox rows');
assert_true(notifications_generated_today() >= 2, 'today count includes fixtures');
assert_true(count(outbox_notifications('logged')) >= 2, 'logged filter includes fixtures');
$appointment = q_one(
    'SELECT a.`appointmentID`, p.`Email` AS patientEmail, d.`Email` AS doctorEmail
     FROM `appointment` a
     JOIN `patient` p ON p.`PatientID` = a.`PatientID`
     JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
     WHERE a.`Status` = :status ORDER BY a.`appointmentID` LIMIT 1',
    ['status' => 'Future']
);
assert_true($appointment !== null, 'future seed appointment exists');
assert_eq(array_column($rows, 'recipient'), [$appointment['doctorEmail'], $appointment['patientEmail']], 'fixture recipients and newest-first order');
foreach ($rows as $row) {
    assert_eq((int) $row['appointmentID'], (int) $appointment['appointmentID'], 'fixture appointment FK');
    assert_eq($row['deliveryStatus'], 'logged', 'fixture has no claimed delivery');
    assert_contains($row['Body'], 'This fixture was not delivered.', 'fixture body explains delivery state');
}
assert_true($rows[0]['SentAt'] > $rows[1]['SentAt'], 'newest fixture is first');

$runbook = file_get_contents($root . '/docs/RUNBOOK.md');
if ($runbook === false || strpos($runbook, "password_hash('Password123', PASSWORD_DEFAULT)") === false) {
    throw new RuntimeException('runbook does not document demo hashes');
}

echo "PASS: seed checks\n";

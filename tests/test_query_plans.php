<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . '_test;charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$queries = [
    'login-doctor' => 'SELECT `DoctorID` FROM `doctor` WHERE `User` = :user LIMIT 1',
    'login-patient' => 'SELECT `PatientID` FROM `patient` WHERE `User` = :user LIMIT 1',
    'slot' => 'SELECT `slotID` FROM `slots` WHERE `DoctorID` = :doctor AND `Status` = \'Available\' AND `SlotDateTime` >= NOW() ORDER BY `SlotDateTime` LIMIT 3',
    'history' => 'SELECT `appointmentID` FROM `appointment` WHERE `PatientID` = :patient ORDER BY `appointmentDateTime` DESC LIMIT 100',
    'outbox' => 'SELECT `notificationID` FROM `notifications` ORDER BY `notificationID` DESC LIMIT 100',
    'dashboard' => 'SELECT `appointmentID` FROM `appointment` WHERE `DoctorID` = :doctor ORDER BY `appointmentDateTime` DESC LIMIT 100',
];

$params = [
    'login-doctor' => ['user' => '__audit_missing__'],
    'login-patient' => ['user' => '__audit_missing__'],
    'slot' => ['doctor' => 1],
    'history' => ['patient' => 1],
    'outbox' => [],
    'dashboard' => ['doctor' => 1],
];
$indexes = $pdo->query("SHOW INDEX FROM `slots`")->fetchAll();
$indexNames = array_column($indexes, 'Key_name');
foreach (['uq_slot', 'idx_slot_lookup'] as $required) {
    if (!in_array($required, $indexNames, true)) {
        throw new RuntimeException("missing fixed-schema index: {$required}");
    }
}

$appointmentIndexes = $pdo->query("SHOW INDEX FROM `appointment`")->fetchAll();
$appointmentNames = array_column($appointmentIndexes, 'Key_name');
foreach (['idx_appt_patient_dt', 'idx_appt_doctor_dt'] as $required) {
    if (!in_array($required, $appointmentNames, true)) {
        throw new RuntimeException("missing fixed-schema index: {$required}");
    }
}

foreach ($queries as $name => $sql) {
    $start = microtime(true);
    $statement = $pdo->prepare('EXPLAIN ' . $sql);
    $statement->execute($params[$name]);
    $explain = $statement->fetchAll();
    $elapsedMs = (microtime(true) - $start) * 1000;
    if ($explain === []) {
        throw new RuntimeException("no plan returned for {$name}");
    }
    foreach ($explain as $row) {
        if (($row['type'] ?? '') === 'ALL' && ($row['rows'] ?? 0) > 100) {
            throw new RuntimeException("unbounded table scan in {$name}");
        }
    }
    if ($elapsedMs >= 250) {
        throw new RuntimeException(sprintf('%s exceeded 250 ms (%.2f ms)', $name, $elapsedMs));
    }
    $access = implode(',', array_map(static fn (array $row): string => (string) ($row['type'] ?? '?'), $explain));
    echo sprintf("PLAN %-16s type=%-8s time=%.2fms\n", $name, $access, $elapsedMs);
}

echo "OK: issue #71 query plans and thresholds recorded\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'config.php';

$database = DB_NAME . '_test';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $database . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('doctor','patient','slots','appointment','notifications')")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
if ($tables !== ['appointment', 'doctor', 'notifications', 'patient', 'slots']) {
    throw new RuntimeException('schema tables do not match the fixed contract');
}

$columns = static function (PDO $pdo, string $table): array {
    $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
};
$patient = $columns($pdo, 'patient');
$slots = $columns($pdo, 'slots');
$appointment = $columns($pdo, 'appointment');
$notifications = $columns($pdo, 'notifications');
if (!isset($patient['Allergies']) || !isset($slots['SlotDateTime'], $appointment['appointmentDateTime']) || !isset($notifications['recipient']) || isset($notifications['receipient'])) {
    throw new RuntimeException('required JSON, DATETIME, or recipient columns are incorrect');
}
if ($patient['Allergies'] !== 'longtext' || $slots['Status'] !== "enum('Available','Booked','Blocked')" || $appointment['Status'] !== "enum('Future','Cancelled','No show','Completed','Rescheduled')" || $notifications['deliveryStatus'] !== "enum('logged','sent','failed')") {
    throw new RuntimeException('fixed enum or Allergies type mismatch');
}

$uniqueSlot = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'slots' AND INDEX_NAME = 'uq_slot' AND NON_UNIQUE = 0 AND COLUMN_NAME IN ('DoctorID','SlotDateTime')")->fetchColumn();
if ((int) $uniqueSlot !== 2) {
    throw new RuntimeException('unique slot guard is missing');
}
$foreignKeys = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('slots','appointment','notifications')")->fetchColumn();
if ($foreignKeys < 4) {
    throw new RuntimeException('foreign-key coverage is incomplete');
}

echo "PASS: schema integrity checks\n";

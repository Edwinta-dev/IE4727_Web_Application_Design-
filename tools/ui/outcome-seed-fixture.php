<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/models/appointments.php';

if (q_val('SELECT DATABASE()') !== 'ie4727db_test') {
    throw new RuntimeException('Outcome fixture requires resolved ie4727db_test.');
}
// Read only: these are the real demo rows, never hand-inserted substitutes.
$appointments = q_all('SELECT * FROM appointment ORDER BY appointmentID');
$slots = q_all('SELECT s.* FROM slots s JOIN appointment a ON a.slotID = s.slotID ORDER BY s.slotID');
$notifications = q_all('SELECT * FROM notifications ORDER BY notificationID');
$doctor = (int) q_val('SELECT DoctorID FROM doctor WHERE User = :user', ['user' => 'drsmith']);
$now = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
$pending = array_values(array_filter($appointments, static fn(array $a): bool =>
    (int) $a['DoctorID'] === $doctor && $a['Status'] === 'Future' && $a['appointmentDateTime'] < $now));
$future = array_values(array_filter($appointments, static fn(array $a): bool =>
    (int) $a['DoctorID'] === $doctor && $a['Status'] === 'Future' && $a['appointmentDateTime'] > $now));
$foreign = array_values(array_filter($appointments, static fn(array $a): bool => (int) $a['DoctorID'] !== $doctor));
$counts = [];
foreach (['doctor', 'patient', 'slots', 'appointment', 'notifications'] as $table) {
    $counts[$table] = (int) q_val('SELECT COUNT(*) FROM `' . $table . '`');
}
echo json_encode(compact('appointments', 'slots', 'notifications', 'doctor', 'now', 'pending', 'future', 'foreign', 'counts'), JSON_THROW_ON_ERROR);

<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/models/slots.php';
if (q_val('SELECT DATABASE()') !== 'ie4727db_test') {
    throw new RuntimeException('Reschedule fixtures require resolved ie4727db_test.');
}
$mode = $argv[1] ?? 'state';
$today = new DateTimeImmutable('today');
$day = static fn (int $offset): string => $today->modify('+' . $offset . ' days')->format('Y-m-d');
if ($mode === 'setup') {
    q('INSERT INTO doctor (FullName, User, HashPass, Email, Specialty, ImageURL)
       VALUES (?, ?, ?, ?, ?, ?)', ['Dr Day Fixture', 'reschedule_day_fixture', password_hash('Password123', PASSWORD_DEFAULT),
        'reschedule-day@example.test', 'General Practice', 'assets/img/General_Practice_in_Action.jpg']);
}
$doctorId = (int) q_val('SELECT DoctorID FROM doctor WHERE User = ?', ['reschedule_day_fixture']);
if ($mode === 'setup') {
    foreach ([[0, '00:00', 'Available'], [1, '10:00', 'Booked'], [1, '11:00', 'Blocked'],
        [3, '10:00', 'Available'], [4, '11:00', 'Available'], [4, '12:00', 'Booked'], [10, '12:00', 'Booked']] as [$offset, $time, $status]) {
        q('INSERT INTO slots (DoctorID, SlotDateTime, Status) VALUES (?, ?, ?)', [$doctorId, $day($offset) . ' ' . $time . ':00', $status]);
    }
    foreach ([4, 10] as $offset) {
        q('INSERT INTO appointment (DoctorID, PatientID, slotID, appointmentDateTime, Status)
           VALUES (?, ?, ?, ?, ?)', [$doctorId, (int) q_val('SELECT PatientID FROM patient WHERE User = ?', ['alextan']),
            (int) q_val('SELECT slotID FROM slots WHERE DoctorID = ? AND SlotDateTime = ?', [$doctorId, $day($offset) . ' 12:00:00']),
            $day($offset) . ' 12:00:00', 'Future']);
    }
}
if ($mode === 'unavailable') {
    q("UPDATE slots SET Status = 'Blocked' WHERE DoctorID = ? AND Status = 'Available' AND SlotDateTime > ?", [$doctorId, $today->format('Y-m-d H:i:s')]);
}
if ($mode === 'stale') {
    q("UPDATE slots SET Status = 'Booked' WHERE DoctorID = ? AND SlotDateTime = ?", [$doctorId, $day(3) . ' 10:00:00']);
}
echo json_encode([
    'database' => q_val('SELECT DATABASE()'), 'today' => $day(0), 'doctor' => $doctorId,
    'appointments' => q_all('SELECT appointmentID, slotID, appointmentDateTime, Status FROM appointment WHERE DoctorID = ? ORDER BY appointmentDateTime', [$doctorId]),
    'slots' => q_all('SELECT slotID, SlotDateTime, Status FROM slots WHERE DoctorID = ? ORDER BY SlotDateTime', [$doctorId]),
    'notifications' => (int) q_val('SELECT COUNT(*) FROM notifications'),
], JSON_THROW_ON_ERROR);

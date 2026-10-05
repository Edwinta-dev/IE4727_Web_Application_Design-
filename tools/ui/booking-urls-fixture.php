<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/models/doctors.php';
ui_test_database_guard((string) q_val('SELECT DATABASE()'));
if (($argv[1] ?? '') === 'state') {
    echo json_encode(q_all('SELECT Body FROM notifications WHERE appointmentID = ? ORDER BY notificationID', [(int) $argv[2]]), JSON_THROW_ON_ERROR);
    exit;
}
ob_start();
require __DIR__ . '/specialty-hover-fixture.php';
ob_end_clean();
$count = (int) $argv[1];
q('DELETE FROM appointment');
q('DELETE FROM slots');
$doctorId = (int) q_val('SELECT DoctorID FROM doctor WHERE User = ?', ['drsmith']);
$tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$slots = [];
for ($i = 0; $i < $count; $i++) {
    $time = (new DateTimeImmutable($tomorrow . ' 09:00:00'))->modify('+' . ($i * 30) . ' minutes')->format('Y-m-d H:i:s');
    q('INSERT INTO slots (DoctorID, SlotDateTime) VALUES (?, ?)', [$doctorId, $time]);
    $slots[] = (int) db()->lastInsertId();
}
$others = array_values(array_filter(all_doctors(), static fn (array $doctor): bool => (int) $doctor['DoctorID'] !== $doctorId));
// One doctor has no slots; another has a slot just outside the seven-day window.
q('INSERT INTO slots (DoctorID, SlotDateTime) VALUES (?, ?)',
    [$others[1]['DoctorID'], (new DateTimeImmutable('today +7 days'))->format('Y-m-d') . ' 00:00:00']);
if (count($others) > 2) {
    q('INSERT INTO slots (DoctorID, SlotDateTime) VALUES (?, ?)',
        [$others[2]['DoctorID'], (new DateTimeImmutable('today +6 days'))->format('Y-m-d') . ' 23:30:00']);
}
$originalTime = (new DateTimeImmutable('today +2 days'))->format('Y-m-d') . ' 15:00:00';
q("INSERT INTO slots (DoctorID, SlotDateTime, Status) VALUES (?, ?, 'Booked')", [$doctorId, $originalTime]);
$originalSlot = (int) db()->lastInsertId();
q("INSERT INTO appointment (DoctorID, PatientID, slotID, appointmentDateTime, Status) VALUES (?, ?, ?, ?, 'Future')",
    [$doctorId, (int) q_val('SELECT PatientID FROM patient WHERE User = ?', ['alextan']), $originalSlot, $originalTime]);
echo json_encode(['doctor' => $doctorId, 'date' => $tomorrow, 'slots' => $slots,
    'appointment' => (int) db()->lastInsertId(), 'originalSlot' => $originalSlot,
    'unavailable' => array_column(array_slice($others, 0, 2), 'DoctorID')], JSON_THROW_ON_ERROR);

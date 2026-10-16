<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/clinic-base/lib/db.php';

if (DB_NAME !== 'ie4727db_test' || q_val('SELECT DATABASE()') !== 'ie4727db_test') {
    throw new RuntimeException('Mobile audit fixture requires ie4727db_test');
}

$slot = q_one("SELECT slotID, DoctorID, SlotDateTime FROM slots WHERE Status = 'Available' AND SlotDateTime > DATE_ADD(NOW(), INTERVAL 1 DAY) ORDER BY SlotDateTime, slotID LIMIT 1");
if ($slot === null) {
    throw new RuntimeException('No future slot for mobile audit fixture');
}
q("UPDATE slots SET Status = 'Booked' WHERE slotID = :slot AND Status = 'Available'", ['slot' => $slot['slotID']]);
q("INSERT INTO appointment (DoctorID, PatientID, slotID, appointmentDateTime, Status) VALUES (:doctor, 1, :slot, :time, 'Future')", [
    'doctor' => $slot['DoctorID'],
    'slot' => $slot['slotID'],
    'time' => $slot['SlotDateTime'],
]);

echo "OK: future appointment for mobile action audit\n";

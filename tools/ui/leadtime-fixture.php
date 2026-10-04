<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/models/stats.php';
if (q_val('SELECT DATABASE()') !== 'ie4727db_test') {
    throw new RuntimeException('Lead-time fixtures require resolved ie4727db_test.');
}

if (($argv[1] ?? '') === 'samples') {
    // Dedicated synthetic range, never timestamps repaired in existing records.
    $samples = [
        ['2040-01-10 09:00:00', '2040-01-10 09:00:00', 'Future'],
        ['2040-01-11 09:00:00', '2040-01-10 09:00:00', 'Completed'],
        ['2040-01-13 09:00:00', '2040-01-10 09:00:00', 'No show'],
        ['2040-01-14 09:00:00', '2040-01-15 09:00:00', 'Cancelled'],
        ['2040-01-16 09:00:00', null, 'Rescheduled'],
    ];
    foreach ($samples as [$start, $created, $status]) {
        q('INSERT INTO appointment (DoctorID, PatientID, appointmentDateTime, CreatedAt, Status)
           VALUES (1, 1, :start, :created, :status)', compact('start', 'created', 'status'));
    }
}

echo json_encode([
    'database' => q_val('SELECT DATABASE()'),
    'lead' => mean_booking_lead_time(),
    'rows' => q_all('SELECT appointmentID, CreatedAt, appointmentDateTime FROM appointment ORDER BY appointmentID'),
], JSON_THROW_ON_ERROR);

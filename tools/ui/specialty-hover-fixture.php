<?php

declare(strict_types=1);

// Reuse the guarded 3/7/12 doctor fixture; each doctor supplies a distinct specialty.
require __DIR__ . '/featured-doctors-fixture.php';
foreach (all_doctors() as $index => $doctor) {
    $specialty = ['General Practice', 'Dental', 'Paediatrics', 'Dermatology', 'Physiotherapy'][$index]
        ?? 'Specialty care ' . ($index + 1);
    q('UPDATE doctor SET Specialty = :specialty WHERE DoctorID = :id', [
        'specialty' => $specialty, 'id' => $doctor['DoctorID'],
    ]);
}
echo 'OK: ' . count(all_specialties()) . " specialties in ie4727db_test\n";

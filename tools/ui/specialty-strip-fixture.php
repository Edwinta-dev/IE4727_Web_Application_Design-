<?php

declare(strict_types=1);

$requested = (int) ($argv[1] ?? 0);
if (!in_array($requested, [3, 6, 7, 10, 12], true)) {
    throw new RuntimeException('Expected 3, 6, 7, 10 or 12 specialties.');
}
$argv[1] = (string) ($requested <= 3 ? 3 : ($requested <= 7 ? 7 : 12));
require __DIR__ . '/featured-doctors-fixture.php';
foreach (array_slice(all_doctors(), $requested) as $doctor) {
    q('DELETE FROM doctor WHERE DoctorID = :id', ['id' => $doctor['DoctorID']]);
}
foreach (all_doctors() as $index => $doctor) {
    $specialty = ['Dental', 'Dentistry', 'Dermatology', 'General Practice', 'Paediatrics', 'Physiotherapy'][$index]
        ?? 'Specialty care ' . ($index + 1);
    q('UPDATE doctor SET Specialty = :specialty WHERE DoctorID = :id', [
        'specialty' => $specialty, 'id' => $doctor['DoctorID'],
    ]);
}
echo 'OK: ' . count(all_specialties()) . " specialties in ie4727db_test\n";

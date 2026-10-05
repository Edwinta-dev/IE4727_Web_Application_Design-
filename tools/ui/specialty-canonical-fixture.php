<?php

declare(strict_types=1);
require __DIR__ . '/featured-doctors-fixture.php';
foreach (all_doctors() as $index => $doctor) {
    q('UPDATE doctor SET Specialty = :specialty, FullName = :name WHERE DoctorID = :id', [
        'specialty' => ['Dental', 'Dentistry', 'Dermatology', 'General Practice', 'Paediatrics', 'Physiotherapy', 'Legacy care'][$index % 7],
        'name' => $index === 1 ? 'dr edwin' : $doctor['FullName'],
        'id' => $doctor['DoctorID'],
    ]);
}
echo json_encode(['doctors' => count(all_doctors()), 'specialties' => array_column(all_specialties(), 'Specialty')], JSON_THROW_ON_ERROR);

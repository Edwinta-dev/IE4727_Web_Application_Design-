<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';
require_once dirname(__DIR__) . '/clinic-base/models/patients.php';

$doctors = all_doctors();
assert_count($doctors, 5);
assert_true((string) $doctors[0]['FullName'] <= (string) $doctors[1]['FullName']);

$specialty = (string) $doctors[0]['Specialty'];
$filtered = all_doctors($specialty);
assert_true(count($filtered) >= 1);
foreach ($filtered as $doctor) {
    assert_eq($doctor['Specialty'], $specialty);
}

$patient = find_patient(1);
if ($patient === null || !is_array($patient['Allergies'])) {
    throw new RuntimeException('patient allergies were not decoded as an array');
}

$originalDoctor = find_doctor(1);
$originalPatient = $patient;
if ($originalDoctor === null) {
    throw new RuntimeException('seeded doctor was not found');
}

update_doctor_profile(1, [
    'WriteUp' => 'Model test write-up',
    'Specialty' => 'Model Test Specialty',
    'Qualifications' => 'Model Test Qualifications',
    'Languages' => 'Model Test Language',
    'ImageURL' => 'model-test.jpg',
]);
update_patient_profile(1, [
    'FullName' => $originalPatient['FullName'],
    'Gender' => $originalPatient['Gender'],
    'Phone' => $originalPatient['Phone'],
    'Allergies' => ['Model Test Allergy', 'Another Allergy'],
]);

$updatedPatient = find_patient(1);
if ($updatedPatient === null || $updatedPatient['Allergies'] !== ['Model Test Allergy', 'Another Allergy']) {
    throw new RuntimeException('patient allergies did not round-trip through JSON');
}

update_doctor_profile(1, $originalDoctor);
update_patient_profile(1, [
    'FullName' => $originalPatient['FullName'],
    'Gender' => $originalPatient['Gender'],
    'Phone' => $originalPatient['Phone'],
    'Allergies' => $originalPatient['Allergies'],
]);

echo "PASS: doctor and patient model checks\n";

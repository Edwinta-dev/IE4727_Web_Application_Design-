<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/models/doctors.php';
ui_test_database_guard((string) q_val('SELECT DATABASE()'));
$count = (int) ($argv[1] ?? 0);
if (!in_array($count, [3, 7, 12], true)) {
    throw new RuntimeException('Expected 3, 7 or 12 doctors.');
}
$doctors = all_doctors();
foreach (array_slice($doctors, $count) as $doctor) {
    q('DELETE FROM doctor WHERE DoctorID = :id', ['id' => $doctor['DoctorID']]);
}
for ($i = count($doctors); $i < $count; $i++) {
    $doctor = $doctors[$i % count($doctors)];
    q('INSERT INTO doctor (FullName, User, HashPass, Email, Specialty, WriteUp, ImageURL)
       VALUES (:name, :user, :hash, :email, :specialty, :bio, :image)', [
        'name' => $doctor['FullName'], 'user' => 'carousel' . $i,
        'hash' => password_hash('Password123', PASSWORD_DEFAULT),
        'email' => 'carousel' . $i . '@example.test', 'specialty' => $doctor['Specialty'],
        'bio' => $doctor['WriteUp'], 'image' => $doctor['ImageURL'],
    ]);
}
if (in_array('--tiles', $argv, true)) {
    foreach (all_doctors() as $i => $doctor) {
        $specialty = ['Physiotherapy', 'Dermatology', 'General Practice', 'Dental', 'Paediatrics'][$i % 5];
        q('UPDATE doctor SET Specialty = :specialty, WriteUp = :bio, ImageURL = :image WHERE DoctorID = :id', [
            'specialty' => $specialty,
            'bio' => $i % 3 === 0 ? '' : str_repeat('Advice and care for your health. ', $i % 3 === 1 ? 1 : 8),
            'image' => $i % 2 === 0 ? '' : $doctor['ImageURL'],
            'id' => $doctor['DoctorID'],
        ]);
    }
}

echo 'OK: ' . count(all_doctors()) . " doctors in ie4727db_test\n";

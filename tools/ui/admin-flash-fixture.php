<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';

require_once dirname(__DIR__, 2) . '/clinic-base/lib/db.php';
require_once dirname(__DIR__, 2) . '/clinic-base/lib/helpers.php';

$action = $argv[1] ?? '';
if ($action === 'seed') {
    $suffix = bin2hex(random_bytes(5));
    q('INSERT INTO `doctor` (`FullName`, `User`, `HashPass`, `Email`) VALUES (:name, :user, :hash, :email)', [
        'name' => 'Flash Test Doctor', 'user' => 'flashdoctor_' . $suffix,
        'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => 'flashdoctor_' . $suffix . '@example.local',
    ]);
    $doctor = (int) db()->lastInsertId();
    q('INSERT INTO `patient` (`FullName`, `User`, `HashPass`, `Email`, `Allergies`) VALUES (:name, :user, :hash, :email, :allergies)', [
        'name' => 'Flash Test Patient', 'user' => 'flashpatient_' . $suffix,
        'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => 'flashpatient_' . $suffix . '@example.local', 'allergies' => '[]',
    ]);
    $patient = (int) db()->lastInsertId();
    $seedPatient = (int) q_val('SELECT `PatientID` FROM `patient` WHERE `User` = :user', ['user' => 'alextan']);
    $seedDoctor = (int) q_val('SELECT `DoctorID` FROM `doctor` WHERE `User` = :user', ['user' => 'drsmith']);
    q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`) VALUES (:doctor, DATE_ADD(NOW(), INTERVAL 120 DAY))', ['doctor' => $doctor]);
    $slot = (int) db()->lastInsertId();
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`) VALUES (:doctor, :patient, :slot, DATE_ADD(NOW(), INTERVAL 120 DAY))', [
        'doctor' => $doctor, 'patient' => $seedPatient, 'slot' => $slot,
    ]);
    $doctorAppointment = (int) db()->lastInsertId();
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`) VALUES (:doctor, :patient, DATE_ADD(NOW(), INTERVAL 121 DAY))', [
        'doctor' => $seedDoctor, 'patient' => $patient,
    ]);
    $patientAppointment = (int) db()->lastInsertId();
    foreach ([$doctorAppointment, $patientAppointment] as $appointment) {
        q('INSERT INTO `notifications` (`sender`, `recipient`, `Subject`, `Body`, `appointmentID`) VALUES (:sender, :recipient, :subject, :body, :appointment)', [
            'sender' => 'clinic@example.local', 'recipient' => 'fixture@example.local', 'subject' => 'Fixture', 'body' => 'Fixture only', 'appointment' => $appointment,
        ]);
    }
    echo json_encode(compact('doctor', 'patient', 'slot', 'doctorAppointment', 'patientAppointment'), JSON_THROW_ON_ERROR);
} elseif ($action === 'counts') {
    [$doctor, $patient, $slot, $doctorAppointment, $patientAppointment] = array_map('intval', array_slice($argv, 2, 5));
    $counts = [];
    $counts['doctor'] = (int) q_val('SELECT COUNT(*) FROM `doctor` WHERE `DoctorID` = :id', ['id' => $doctor]);
    $counts['patient'] = (int) q_val('SELECT COUNT(*) FROM `patient` WHERE `PatientID` = :id', ['id' => $patient]);
    $counts['slot'] = (int) q_val('SELECT COUNT(*) FROM `slots` WHERE `slotID` = :id', ['id' => $slot]);
    $counts['doctorAppointment'] = (int) q_val('SELECT COUNT(*) FROM `appointment` WHERE `appointmentID` = :id', ['id' => $doctorAppointment]);
    $counts['patientAppointment'] = (int) q_val('SELECT COUNT(*) FROM `appointment` WHERE `appointmentID` = :id', ['id' => $patientAppointment]);
    $counts['orphanNotifications'] = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `Subject` = :subject AND `appointmentID` IS NULL', ['subject' => 'Fixture']);
    $counts['notifications'] = (int) q_val('SELECT COUNT(*) FROM `notifications`');
    echo json_encode($counts, JSON_THROW_ON_ERROR);
} elseif ($action === 'flash') {
    $sessionPath = (string) ($argv[2] ?? '');
    $sessionId = (string) ($argv[3] ?? '');
    if ($sessionPath === '' || $sessionId === '') {
        throw new RuntimeException('Session path and ID required');
    }
    session_save_path($sessionPath);
    session_id($sessionId);
    flash('Outbox fixture <once>', 'success');
    session_write_close();
} else {
    throw new InvalidArgumentException('Unknown fixture action');
}

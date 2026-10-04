<?php

declare(strict_types=1);

require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/models/booking.php';

$action = $argv[1] ?? '';
if ($action === 'seed') {
    $suffix = bin2hex(random_bytes(6));
    $user = 'visitdoctor_' . $suffix;
    q('INSERT INTO `doctor` (`FullName`, `User`, `HashPass`, `Email`) VALUES (:name, :user, :hash, :email)',
        ['name' => 'Visit State Doctor', 'user' => $user, 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => $user . '@example.local']);
    $doctor = (int) db()->lastInsertId();
    q('INSERT INTO `patient` (`FullName`, `User`, `HashPass`, `Email`, `Allergies`) VALUES (:name, :user, :hash, :email, :allergies)',
        ['name' => 'Visit State Patient', 'user' => 'visitpatient_' . $suffix, 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => 'visitpatient_' . $suffix . '@example.local', 'allergies' => '[]']);
    $patient = (int) db()->lastInsertId();
    $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $visits = [];
    foreach (['Future', 'Rescheduled', 'Completed', 'Cancelled', 'No show'] as $status) {
        foreach (['past' => '-2 days', 'future' => '+2 days'] as $period => $offset) {
            q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`, `Status`, `Remarks`, `Diagnosis`) VALUES (:doctor, :patient, :start, :status, :remarks, :diagnosis)',
                ['doctor' => $doctor, 'patient' => $patient, 'start' => $now->modify($offset)->format('Y-m-d H:i:s'), 'status' => $status, 'remarks' => encode_visit_remarks('Patient reason', 'Earlier doctor notes'), 'diagnosis' => 'Earlier diagnosis']);
            $visits[] = ['id' => (int) db()->lastInsertId(), 'status' => $status, 'period' => $period];
        }
    }
    $foreignDoctor = (int) q_val('SELECT `DoctorID` FROM `doctor` WHERE `User` = :user', ['user' => 'drsmith']);
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`) VALUES (:doctor, :patient, :start)',
        ['doctor' => $foreignDoctor, 'patient' => $patient, 'start' => $now->modify('-2 days')->format('Y-m-d H:i:s')]);
    $foreign = (int) db()->lastInsertId();
    echo json_encode(compact('doctor', 'patient', 'user', 'now', 'visits', 'foreign'), JSON_THROW_ON_ERROR);
} elseif ($action === 'snapshot') {
    $appointment = q_one('SELECT * FROM `appointment` WHERE `appointmentID` = :id', ['id' => (int) ($argv[2] ?? 0)]);
    $remarks = $appointment === null ? null : decode_visit_remarks($appointment['Remarks'], (string) $appointment['Status']);
    $notifications = q_all('SELECT * FROM `notifications` ORDER BY `notificationID`');
    echo json_encode(compact('appointment', 'remarks', 'notifications'), JSON_THROW_ON_ERROR);
} elseif ($action === 'cleanup') {
    q('DELETE FROM `doctor` WHERE `DoctorID` = :id AND `User` LIKE :prefix', ['id' => (int) ($argv[2] ?? 0), 'prefix' => 'visitdoctor_%']);
    q('DELETE FROM `patient` WHERE `PatientID` = :id AND `User` LIKE :prefix', ['id' => (int) ($argv[3] ?? 0), 'prefix' => 'visitpatient_%']);
} else {
    throw new InvalidArgumentException('Unknown fixture action');
}

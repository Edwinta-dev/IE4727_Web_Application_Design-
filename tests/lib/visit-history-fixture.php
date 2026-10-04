<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/clinic-base/models/appointments.php';

function seed_visit_history(): array
{
    if (DB_NAME !== 'ie4727db_test' || q_val('SELECT DATABASE()') !== 'ie4727db_test') {
        throw new RuntimeException('Visit history fixtures require the resolved test database.');
    }
    $suffix = bin2hex(random_bytes(6));
    $accounts = [];
    foreach (['doctor', 'patient'] as $role) {
        foreach (['own', 'foreign'] as $scope) {
            $user = 'history_' . $role . '_' . $scope . '_' . $suffix;
            if ($role === 'doctor') {
                q('INSERT INTO `doctor` (`FullName`, `User`, `HashPass`, `Email`) VALUES (:name, :user, :hash, :email)',
                    ['name' => 'History doctor', 'user' => $user, 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => $user . '@example.local']);
            } else {
                q('INSERT INTO `patient` (`FullName`, `User`, `HashPass`, `Email`, `Allergies`) VALUES (:name, :user, :hash, :email, :allergies)',
                    ['name' => 'History patient', 'user' => $user, 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => $user . '@example.local', 'allergies' => '[]']);
            }
            $accounts[$role][$scope] = ['id' => (int) db()->lastInsertId(), 'user' => $user];
        }
    }
    $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
    $visits = [];
    foreach ([
        'earliest' => ['-5 days', 'Completed', 'own', 'own'],
        'earlier' => ['-4 days', 'Completed', 'own', 'own'],
        'current' => ['-3 days', 'Future', 'own', 'own'],
        'same_time' => ['-3 days', 'Completed', 'own', 'own'],
        'later' => ['-2 days', 'Completed', 'own', 'own'],
        'foreign_doctor' => ['-4 days', 'Completed', 'foreign', 'own'],
        'foreign_patient' => ['-4 days', 'Completed', 'own', 'foreign'],
        'cancelled' => ['-4 days', 'Cancelled', 'own', 'own'],
        'no_show' => ['-4 days', 'No show', 'own', 'own'],
        'future_status' => ['-4 days', 'Future', 'own', 'own'],
        'rescheduled' => ['-4 days', 'Rescheduled', 'own', 'own'],
        'upcoming' => ['+2 days', 'Future', 'own', 'own'],
    ] as $name => [$offset, $status, $doctor, $patient]) {
        q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`, `Status`, `Diagnosis`, `Remarks`) VALUES (:doctor, :patient, :start, :status, :diagnosis, :remarks)',
            ['doctor' => $accounts['doctor'][$doctor]['id'], 'patient' => $accounts['patient'][$patient]['id'],
                'start' => $now->modify($offset)->format('Y-m-d H:i:s'), 'status' => $status,
                'diagnosis' => 'History ' . $name, 'remarks' => encode_visit_remarks('Reason ' . $name, 'Remarks ' . $name)]);
        $visits[$name] = (int) db()->lastInsertId();
    }
    return compact('accounts', 'visits') + ['now' => $now->format(DATE_ATOM)];
}

function cleanup_visit_history(array $seed): void
{
    foreach ($seed['accounts']['doctor'] as $account) {
        q('DELETE FROM `doctor` WHERE `DoctorID` = :id AND `User` = :user', $account);
    }
    foreach ($seed['accounts']['patient'] as $account) {
        q('DELETE FROM `patient` WHERE `PatientID` = :id AND `User` = :user', $account);
    }
}

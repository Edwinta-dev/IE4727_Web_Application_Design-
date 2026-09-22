<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'mail.php';

/**
 * Book an available slot for a patient and log the confirmation notification.
 */
function book_appointment(int $patientId, int $slotId): int
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $slot = q_one(
            'SELECT `slotID`, `DoctorID`, `SlotDateTime`, `Status`
             FROM `slots`
             WHERE `slotID` = :slot_id
             FOR UPDATE',
            ['slot_id' => $slotId]
        );

        if ($slot === null || $slot['Status'] !== 'Available') {
            throw new RuntimeException('Slot is no longer available.');
        }

        $patient = q_one(
            'SELECT `FullName`, `Email`
             FROM `patient`
             WHERE `PatientID` = :patient_id
             LIMIT 1',
            ['patient_id' => $patientId]
        );
        $doctor = q_one(
            'SELECT `FullName`
             FROM `doctor`
             WHERE `DoctorID` = :doctor_id
             LIMIT 1',
            ['doctor_id' => $slot['DoctorID']]
        );

        if ($patient === null || $doctor === null) {
            throw new RuntimeException('Booking participant was not found.');
        }

        q(
            'UPDATE `slots`
             SET `Status` = \'Booked\'
             WHERE `slotID` = :slot_id AND `Status` = \'Available\'',
            ['slot_id' => $slotId]
        );

        if ((int) q_val('SELECT ROW_COUNT()') !== 1) {
            throw new RuntimeException('Slot could not be reserved.');
        }

        q(
            'INSERT INTO `appointment`
                (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`, `Status`)
             VALUES (:doctor_id, :patient_id, :slot_id, :appointment_date_time, \'Future\')',
            [
                'doctor_id' => (int) $slot['DoctorID'],
                'patient_id' => $patientId,
                'slot_id' => $slotId,
                'appointment_date_time' => $slot['SlotDateTime'],
            ]
        );

        $appointmentId = (int) db()->lastInsertId();
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    [$subject, $body] = mail_booking_confirmed(
        (string) $patient['FullName'],
        (string) $doctor['FullName'],
        (string) $slot['SlotDateTime']
    );
    send_mail((string) $patient['Email'], $subject, $body, $appointmentId);

    return $appointmentId;
}

/** @return list<array<string, mixed>> */
function appointments_for_patient(int $patientId, string $when = 'all'): array
{
    if (!in_array($when, ['upcoming', 'past', 'all'], true)) {
        throw new InvalidArgumentException('Invalid appointment time range.');
    }

    $where = ['a.`PatientID` = :patient_id'];
    $params = ['patient_id' => $patientId];

    if ($when === 'upcoming') {
        $where[] = "a.`appointmentDateTime` >= NOW()";
        $where[] = "a.`Status` IN ('Future', 'Rescheduled')";
    } elseif ($when === 'past') {
        $where[] = "(a.`appointmentDateTime` < NOW()
                     OR a.`Status` NOT IN ('Future', 'Rescheduled'))";
    }

    return q_all(
        'SELECT a.`appointmentID`, a.`DoctorID`, a.`PatientID`, a.`slotID`,
                a.`appointmentDateTime`, a.`CreatedAt`, a.`updatedAt`, a.`Status`,
                a.`Diagnosis`, a.`Prescription`, a.`Treatment`, a.`FollowUp`, a.`Remarks`,
                d.`FullName` AS `DoctorName`, d.`Email` AS `DoctorEmail`
         FROM `appointment` a
         INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY a.`appointmentDateTime`',
        $params
    );
}

/** @return list<array<string, mixed>> */
function appointments_for_doctor_day(int $doctorId, string $date): array
{
    return q_all(
        'SELECT a.`appointmentID`, a.`DoctorID`, a.`PatientID`, a.`slotID`,
                a.`appointmentDateTime`, a.`CreatedAt`, a.`updatedAt`, a.`Status`,
                a.`Diagnosis`, a.`Prescription`, a.`Treatment`, a.`FollowUp`, a.`Remarks`,
                p.`FullName` AS `PatientName`, p.`Email` AS `PatientEmail`
         FROM `appointment` a
         INNER JOIN `patient` p ON p.`PatientID` = a.`PatientID`
         WHERE a.`DoctorID` = :doctor_id
           AND DATE(a.`appointmentDateTime`) = :appointment_date
         ORDER BY a.`appointmentDateTime`',
        ['doctor_id' => $doctorId, 'appointment_date' => $date]
    );
}

/** @return array<string, mixed>|null */
function find_appointment(int $id): ?array
{
    return q_one(
        'SELECT a.`appointmentID`, a.`DoctorID`, a.`PatientID`, a.`slotID`,
                a.`appointmentDateTime`, a.`CreatedAt`, a.`updatedAt`, a.`Status`,
                a.`Diagnosis`, a.`Prescription`, a.`Treatment`, a.`FollowUp`, a.`Remarks`,
                d.`FullName` AS `DoctorName`, d.`Email` AS `DoctorEmail`,
                p.`FullName` AS `PatientName`, p.`Email` AS `PatientEmail`
         FROM `appointment` a
         INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
         INNER JOIN `patient` p ON p.`PatientID` = a.`PatientID`
         WHERE a.`appointmentID` = :appointment_id
         LIMIT 1',
        ['appointment_id' => $id]
    );
}

/** @return list<array<string, mixed>> */
function patient_history(int $patientId): array
{
    return q_all(
        'SELECT a.`appointmentID`, a.`DoctorID`, a.`PatientID`, a.`slotID`,
                a.`appointmentDateTime`, a.`CreatedAt`, a.`updatedAt`, a.`Status`,
                a.`Diagnosis`, a.`Treatment`, a.`Prescription`, a.`FollowUp`, a.`Remarks`,
                d.`FullName` AS `DoctorName`
         FROM `appointment` a
         INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
         WHERE a.`PatientID` = :patient_id
           AND a.`Status` = \'Completed\'
         ORDER BY a.`appointmentDateTime` DESC',
        ['patient_id' => $patientId]
    );
}

/**
 * Search appointments for the administrator.
 *
 * Supported filters are doctor/doctorId, patient/patientId, status, and
 * date_from/date_to (with from/to accepted as aliases).
 *
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function admin_search(array $filters = []): array
{
    $where = [];
    $params = [];

    $doctorId = $filters['doctorId'] ?? $filters['doctor'] ?? null;
    if ($doctorId !== null && $doctorId !== '') {
        $where[] = 'a.`DoctorID` = ?';
        $params[] = $doctorId;
    }

    $patientId = $filters['patientId'] ?? $filters['patient'] ?? null;
    if ($patientId !== null && $patientId !== '') {
        $where[] = 'a.`PatientID` = ?';
        $params[] = $patientId;
    }

    if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
        $where[] = 'a.`Status` = ?';
        $params[] = $filters['status'];
    }

    $dateFrom = $filters['date_from'] ?? $filters['from'] ?? null;
    if ($dateFrom !== null && $dateFrom !== '') {
        $where[] = 'DATE(a.`appointmentDateTime`) >= ?';
        $params[] = $dateFrom;
    }

    $dateTo = $filters['date_to'] ?? $filters['to'] ?? null;
    if ($dateTo !== null && $dateTo !== '') {
        $where[] = 'DATE(a.`appointmentDateTime`) <= ?';
        $params[] = $dateTo;
    }

    $sql = 'SELECT a.`appointmentID`, a.`DoctorID`, a.`PatientID`, a.`slotID`,
                   a.`appointmentDateTime`, a.`CreatedAt`, a.`updatedAt`, a.`Status`,
                   a.`Diagnosis`, a.`Prescription`, a.`Treatment`, a.`FollowUp`, a.`Remarks`,
                   d.`FullName` AS `DoctorName`, p.`FullName` AS `PatientName`
            FROM `appointment` a
            INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
            INNER JOIN `patient` p ON p.`PatientID` = a.`PatientID`';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY a.`appointmentDateTime` DESC';

    return q_all($sql, $params);
}

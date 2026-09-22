<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'mail.php';

/**
 * Claim a slot and create its appointment atomically.
 *
 * @return array{ok: bool, appointment_id: int|null, error: string|null}
 */
function book_appointment(int $patientId, int $slotId, string $reason): array
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $claim = q(
            "UPDATE `slots`
             SET `Status` = 'Booked'
             WHERE `slotID` = :slot_id AND `Status` = 'Available'",
            ['slot_id' => $slotId]
        );

        if ($claim->rowCount() !== 1) {
            $connection->rollBack();

            return [
                'ok' => false,
                'appointment_id' => null,
                'error' => 'That slot has just been taken. Please choose another.',
            ];
        }

        $slot = q_one(
            'SELECT `DoctorID`, `SlotDateTime`
             FROM `slots`
             WHERE `slotID` = :slot_id
             LIMIT 1',
            ['slot_id' => $slotId]
        );

        if ($slot === null) {
            throw new RuntimeException('The selected slot was not found.');
        }

        if (strtotime((string) $slot['SlotDateTime']) <= time()) {
            throw new RuntimeException('That slot is in the past. Please choose another.');
        }

        $patient = q_one(
            'SELECT `FullName`, `Email`
             FROM `patient`
             WHERE `PatientID` = :patient_id
             LIMIT 1',
            ['patient_id' => $patientId]
        );
        $doctor = q_one(
            'SELECT `FullName`, `Email`
             FROM `doctor`
             WHERE `DoctorID` = :doctor_id
             LIMIT 1',
            ['doctor_id' => (int) $slot['DoctorID']]
        );

        if ($patient === null || $doctor === null) {
            throw new RuntimeException('Booking participant was not found.');
        }

        q(
            "INSERT INTO `appointment`
                (`PatientID`, `DoctorID`, `appointmentDateTime`, `Status`, `Remarks`, `slotID`)
             VALUES (:patient_id, :doctor_id, :appointment_date_time, 'Future', :reason, :slot_id)",
            [
                'patient_id' => $patientId,
                'doctor_id' => (int) $slot['DoctorID'],
                'appointment_date_time' => $slot['SlotDateTime'],
                'reason' => $reason,
                'slot_id' => $slotId,
            ]
        );

        $appointmentId = (int) $connection->lastInsertId();
        [$subject, $patientBody] = mail_booking_confirmed(
            (string) $patient['FullName'],
            (string) $doctor['FullName'],
            (string) $slot['SlotDateTime']
        );
        send_mail((string) $patient['Email'], $subject, $patientBody, $appointmentId);

        [$doctorSubject, $doctorBody] = mail_booking_confirmed(
            (string) $doctor['FullName'],
            (string) $doctor['FullName'],
            (string) $slot['SlotDateTime']
        );
        send_mail((string) $doctor['Email'], $doctorSubject, $doctorBody, $appointmentId);

        $connection->commit();

        return [
            'ok' => true,
            'appointment_id' => $appointmentId,
            'error' => null,
        ];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        return [
            'ok' => false,
            'appointment_id' => null,
            'error' => $exception->getMessage(),
        ];
    }
}

/**
 * Move an appointment to another available slot atomically.
 *
 * @return array{ok: bool, appointment_id: int|null, error: string|null}
 */
function reschedule_appointment(int $appointmentId, int $newSlotId, string $actorRole): array
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $claim = q(
            "UPDATE `slots`
             SET `Status` = 'Booked'
             WHERE `slotID` = :slot_id AND `Status` = 'Available'",
            ['slot_id' => $newSlotId]
        );

        if ($claim->rowCount() !== 1) {
            $connection->rollBack();

            return [
                'ok' => false,
                'appointment_id' => null,
                'error' => 'That slot has just been taken. Please choose another.',
            ];
        }

        $appointment = q_one(
            'SELECT a.`appointmentID`, a.`DoctorID`, a.`PatientID`, a.`slotID`,
                    a.`appointmentDateTime`, d.`FullName` AS `DoctorName`,
                    d.`Email` AS `DoctorEmail`, p.`FullName` AS `PatientName`,
                    p.`Email` AS `PatientEmail`
             FROM `appointment` a
             INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
             INNER JOIN `patient` p ON p.`PatientID` = a.`PatientID`
             WHERE a.`appointmentID` = :appointment_id
             LIMIT 1',
            ['appointment_id' => $appointmentId]
        );
        $newSlot = q_one(
            'SELECT `DoctorID`, `SlotDateTime`
             FROM `slots`
             WHERE `slotID` = :slot_id
             LIMIT 1',
            ['slot_id' => $newSlotId]
        );

        if ($appointment === null || $newSlot === null) {
            throw new RuntimeException('The appointment or selected slot was not found.');
        }

        if ((int) $newSlot['DoctorID'] !== (int) $appointment['DoctorID']) {
            throw new RuntimeException('The selected slot belongs to another doctor.');
        }

        if (strtotime((string) $newSlot['SlotDateTime']) <= time()) {
            throw new RuntimeException('That slot is in the past. Please choose another.');
        }

        $oldDateTime = (string) $appointment['appointmentDateTime'];
        $newDateTime = (string) $newSlot['SlotDateTime'];
        $oldSlotId = $appointment['slotID'] === null ? null : (int) $appointment['slotID'];

        if ($oldSlotId !== null) {
            q(
                "UPDATE `slots`
                 SET `Status` = 'Available'
                 WHERE `slotID` = :slot_id",
                ['slot_id' => $oldSlotId]
            );
        }

        q(
            "UPDATE `appointment`
             SET `slotID` = :new_slot_id,
                 `appointmentDateTime` = :appointment_date_time,
                 `Status` = 'Rescheduled'
             WHERE `appointmentID` = :appointment_id",
            [
                'new_slot_id' => $newSlotId,
                'appointment_date_time' => $newDateTime,
                'appointment_id' => $appointmentId,
            ]
        );

        $actor = $actorRole === 'doctor' ? 'doctor' : 'patient';
        $message = "Your appointment was rescheduled by the {$actor} from "
            . $oldDateTime . ' to ' . $newDateTime . ".\n"
            . 'Clinic: ' . APP_NAME;
        $subject = 'Appointment rescheduled - ' . APP_NAME;

        send_mail((string) $appointment['PatientEmail'], $subject, $message, $appointmentId);
        send_mail((string) $appointment['DoctorEmail'], $subject, $message, $appointmentId);

        $connection->commit();

        return [
            'ok' => true,
            'appointment_id' => $appointmentId,
            'error' => null,
        ];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        return [
            'ok' => false,
            'appointment_id' => null,
            'error' => $exception->getMessage(),
        ];
    }
}

/**
 * Cancel an appointment and release the exact slot it booked.
 */
function cancel_appointment(int $appointmentId, string $actorRole): bool
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $appointment = q_one(
            'SELECT a.`appointmentID`, a.`slotID`, a.`appointmentDateTime`,
                    d.`FullName` AS `DoctorName`, d.`Email` AS `DoctorEmail`,
                    p.`FullName` AS `PatientName`, p.`Email` AS `PatientEmail`
             FROM `appointment` a
             INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
             INNER JOIN `patient` p ON p.`PatientID` = a.`PatientID`
             WHERE a.`appointmentID` = :appointment_id
             LIMIT 1
             FOR UPDATE',
            ['appointment_id' => $appointmentId]
        );

        if ($appointment === null) {
            throw new RuntimeException('The appointment was not found.');
        }

        q(
            "UPDATE `appointment`
             SET `Status` = 'Cancelled'
             WHERE `appointmentID` = :appointment_id",
            ['appointment_id' => $appointmentId]
        );

        if ($appointment['slotID'] !== null) {
            q(
                "UPDATE `slots`
                 SET `Status` = 'Available'
                 WHERE `slotID` = :slot_id",
                ['slot_id' => (int) $appointment['slotID']]
            );
        }

        notify_cancelled_appointment($appointment, $actorRole, $appointmentId);
        $connection->commit();

        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        return false;
    }
}

/**
 * Set attendance status from the doctor's day board.
 */
function set_appointment_status(int $appointmentId, string $status): void
{
    if (!in_array($status, ['Completed', 'No show'], true)) {
        throw new InvalidArgumentException('Invalid appointment status.');
    }

    q(
        'UPDATE `appointment`
         SET `Status` = :status
         WHERE `appointmentID` = :appointment_id',
        ['status' => $status, 'appointment_id' => $appointmentId]
    );
}

/**
 * Mark a slot unavailable. A booked slot is cancelled first so its patient
 * receives the same cancellation notification as an explicit cancellation.
 */
function block_slot(int $slotId): bool
{
    $connection = db();
    $connection->beginTransaction();

    try {
        $slot = q_one(
            'SELECT `Status`
             FROM `slots`
             WHERE `slotID` = :slot_id
             LIMIT 1
             FOR UPDATE',
            ['slot_id' => $slotId]
        );

        if ($slot === null) {
            throw new RuntimeException('The slot was not found.');
        }

        if ((string) $slot['Status'] === 'Booked') {
            $appointment = q_one(
                "SELECT a.`appointmentID`, a.`slotID`, a.`appointmentDateTime`,
                        d.`FullName` AS `DoctorName`, d.`Email` AS `DoctorEmail`,
                        p.`FullName` AS `PatientName`, p.`Email` AS `PatientEmail`
                 FROM `appointment` a
                 INNER JOIN `doctor` d ON d.`DoctorID` = a.`DoctorID`
                 INNER JOIN `patient` p ON p.`PatientID` = a.`PatientID`
                 WHERE a.`slotID` = :slot_id
                 LIMIT 1
                 FOR UPDATE",
                ['slot_id' => $slotId]
            );

            if ($appointment !== null) {
                q(
                    "UPDATE `appointment`
                     SET `Status` = 'Cancelled'
                     WHERE `appointmentID` = :appointment_id",
                    ['appointment_id' => (int) $appointment['appointmentID']]
                );
                notify_cancelled_appointment(
                    $appointment,
                    'doctor',
                    (int) $appointment['appointmentID']
                );
            }
        }

        q(
            "UPDATE `slots` SET `Status` = 'Blocked' WHERE `slotID` = :slot_id",
            ['slot_id' => $slotId]
        );
        $connection->commit();

        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        return false;
    }
}

/**
 * Make a blocked slot available again. Booked slots are never unblocked.
 */
function unblock_slot(int $slotId): bool
{
    $statement = q(
        "UPDATE `slots`
         SET `Status` = 'Available'
         WHERE `slotID` = :slot_id AND `Status` = 'Blocked'",
        ['slot_id' => $slotId]
    );

    return $statement->rowCount() === 1;
}

/**
 * Save the doctor's visit notes and complete the appointment.
 *
 * @param array<string, mixed> $fields
 */
function save_visit_notes(int $appointmentId, array $fields): void
{
    q(
        "UPDATE `appointment`
         SET `Diagnosis` = :diagnosis,
             `Prescription` = :prescription,
             `Treatment` = :treatment,
             `FollowUp` = :follow_up,
             `Remarks` = :remarks,
             `Status` = 'Completed'
         WHERE `appointmentID` = :appointment_id",
        [
            'diagnosis' => $fields['Diagnosis'] ?? null,
            'prescription' => $fields['Prescription'] ?? null,
            'treatment' => $fields['Treatment'] ?? null,
            'follow_up' => !empty($fields['FollowUp']) ? 1 : 0,
            'remarks' => $fields['Remarks'] ?? null,
            'appointment_id' => $appointmentId,
        ]
    );
}

/** @param array<string, mixed> $appointment */
function notify_cancelled_appointment(array $appointment, string $actorRole, int $appointmentId): void
{
    $actor = $actorRole === 'doctor' ? 'doctor' : 'patient';
    [$subject, $body] = mail_cancelled(
        (string) $appointment['PatientName'],
        (string) $appointment['DoctorName'],
        (string) $appointment['appointmentDateTime']
    );
    $body .= "\nThis cancellation was made by the {$actor}.";
    send_mail((string) $appointment['PatientEmail'], $subject, $body, $appointmentId);

    [$subject, $body] = mail_cancelled(
        (string) $appointment['DoctorName'],
        (string) $appointment['DoctorName'],
        (string) $appointment['appointmentDateTime']
    );
    $body .= "\nThis cancellation was made by the {$actor}.";
    send_mail((string) $appointment['DoctorEmail'], $subject, $body, $appointmentId);
}

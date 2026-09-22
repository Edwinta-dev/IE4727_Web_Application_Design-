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

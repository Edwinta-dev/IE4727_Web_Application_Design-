<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/**
 * Claim a slot and create its appointment as one unit.
 *
 * The optional failure point exists solely for deterministic transaction tests;
 * it must never be supplied by a request handler.
 *
 * @return array{ok: bool, appointment_id?: int, reason?: string}
 */
function book_appointment(int $doctorId, int $patientId, int $slotId, ?string $failurePoint = null): array
{
    try {
        $appointmentId = db_transaction(static function () use ($doctorId, $patientId, $slotId, $failurePoint): int {
            // Lock the exact slot row before changing it; uq_slot and this transaction
            // together prevent two requests from claiming the same appointment time.
            $slot = q_one(
                'SELECT `slotID`, `DoctorID`, `SlotDateTime`, `Status`
                 FROM `slots` WHERE `slotID` = :slot_id FOR UPDATE',
                ['slot_id' => $slotId]
            );
            if ($slot === null || (int) $slot['DoctorID'] !== $doctorId || $slot['Status'] !== 'Available') {
                throw new BookingConflict('slot is no longer available');
            }
            if ($failurePoint === 'after_lock') {
                throw new RuntimeException('injected booking failure');
            }

            $changed = q(
                'UPDATE `slots` SET `Status` = \'Booked\'
                 WHERE `slotID` = :slot_id AND `Status` = \'Available\'',
                ['slot_id' => $slotId]
            )->rowCount();
            if ($changed !== 1) {
                throw new BookingConflict('slot claim was lost');
            }
            if ($failurePoint === 'after_claim') {
                throw new RuntimeException('injected booking failure');
            }

            q(
                'INSERT INTO `appointment`
                    (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`, `Status`)
                 VALUES (:doctor_id, :patient_id, :slot_id, :appointment_datetime, \'Future\')',
                [
                    'doctor_id' => $doctorId,
                    'patient_id' => $patientId,
                    'slot_id' => $slotId,
                    'appointment_datetime' => $slot['SlotDateTime'],
                ]
            );
            if ($failurePoint === 'after_insert') {
                throw new RuntimeException('injected booking failure');
            }
            return (int) db()->lastInsertId();
        });

        return ['ok' => true, 'appointment_id' => $appointmentId];
    } catch (BookingConflict $exception) {
        return ['ok' => false, 'reason' => 'conflict'];
    } catch (RuntimeException $exception) {
        return ['ok' => false, 'reason' => 'failed'];
    } catch (PDOException $exception) {
        $code = isset($exception->errorInfo[1])
            ? (string) $exception->errorInfo[1]
            : (string) $exception->getCode();
        if (in_array($code, ['1062', '1205', '1213'], true) || $exception->getCode() === '40001') {
            return ['ok' => false, 'reason' => 'conflict'];
        }
        throw $exception;
    }
}

final class BookingConflict extends RuntimeException
{
}

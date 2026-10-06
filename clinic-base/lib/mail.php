<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';

/** Log only. Call inside the transaction that performs the related mutation. */
function send_mail(string $toEmail, string $subject, string $body, ?int $appointmentId = null): int
{
    return log_notification($toEmail, $subject, $body, $appointmentId);
}

/** Attempt delivery after the mutation commits; transport and status errors are non-fatal. */
function deliver_notifications(array $ids): void
{
    if (MAIL_DELIVERY === 'off') return;

    foreach (array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)) as $id) {
        try {
            $row = notification_for_delivery($id);
            if ($row === null) continue;
            try {
                $transport = $GLOBALS['clinic_mail_transport'] ?? null;
                $delivered = is_callable($transport)
                    ? (bool) $transport($row['recipient'], $row['Subject'], $row['Body'], 'From: ' . CLINIC_EMAIL)
                    : @mail($row['recipient'], $row['Subject'], $row['Body'], 'From: ' . CLINIC_EMAIL);
            } catch (Throwable $exception) {
                error_log('Clinic mail transport failed: ' . $exception->getMessage());
                $delivered = false;
            }
            try {
                mark_notification_delivery($id, $delivered);
            } catch (Throwable $exception) {
                error_log('Clinic mail delivery status update failed: ' . $exception->getMessage());
            }
        } catch (Throwable $exception) {
            error_log('Clinic mail delivery failed: ' . $exception->getMessage());
        }
    }
}

/** @return array{0: string, 1: string} */
function mail_booking_confirmed(
    string $recipientName,
    string $doctorName,
    string $appointmentDate,
    ?string $appointmentTime = null,
    ?string $patientName = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $subject = 'Appointment confirmed - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . ($patientName === null
            ? "Your appointment with {$doctorName} is confirmed for {$date} at {$time}.\n"
            : "Your appointment with patient {$patientName} is confirmed for {$date} at {$time}.\n")
        . "Thank you for choosing " . APP_NAME . ".\n\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_rescheduled(
    string $recipientName,
    string $doctorName,
    string $oldDateTime,
    string $newDateTime,
    string $actorRole,
    ?string $patientName = null
): array {
    [$oldDate, $oldTime] = mail_date_time($oldDateTime, null);
    [$newDate, $newTime] = mail_date_time($newDateTime, null);
    $actor = $actorRole === 'doctor' ? 'doctor' : 'patient';
    $participant = $patientName === null ? $doctorName : 'patient ' . $patientName;
    $subject = 'Appointment rescheduled - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "Your appointment with {$participant} has been rescheduled by the {$actor}\n"
        . "from {$oldDate} at {$oldTime} to {$newDate} at {$newTime}.\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_cancelled(
    string $recipientName,
    string $doctorName,
    string $appointmentDate,
    ?string $appointmentTime = null,
    ?string $patientName = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $participant = $patientName === null ? $doctorName : 'patient ' . $patientName;
    $subject = 'Appointment cancelled - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "Your appointment with {$participant} on {$date} at {$time} has been cancelled.\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_availability_cancelled(
    string $recipientName,
    string $doctorName,
    string $appointmentDate,
    ?string $appointmentTime = null,
    ?string $patientName = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $participant = $patientName === null ? $doctorName : 'patient ' . $patientName;
    $subject = 'Appointment availability changed - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "The appointment with {$participant} on {$date} at {$time} is no longer available.\n"
        . "The doctor blocked this time and the appointment has been cancelled.\n"
        . ($patientName === null
            ? "Please sign in and book another available time with {$doctorName}, or contact " . APP_NAME . ".\n\n"
            : "The patient has been asked to book another available time.\n\n")
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_date_time(string $date, ?string $time): array
{
    $timestamp = strtotime($date . ($time !== null && $time !== '' ? ' ' . $time : ''));
    if ($timestamp === false) {
        return [$date, ''];
    }

    return [date('d M Y', $timestamp), date('g:i A', $timestamp)];
}

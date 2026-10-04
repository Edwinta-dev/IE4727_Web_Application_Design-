<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';

/**
 * Log a notification before attempting local mail delivery.
 *
 * Delivery failure is deliberately non-fatal: the outbox remains the source
 * of truth for the demo even when PHP mailtodisk/mail() is not configured.
 */
function send_mail(string $toEmail, string $subject, string $body, ?int $appointmentId = null): int
{
    $notificationId = log_notification($toEmail, $subject, $body, $appointmentId);
    if (MAIL_DELIVERY === 'off') {
        return $notificationId;
    }

    $delivered = false;

    try {
        $delivered = @mail($toEmail, $subject, $body, 'From: ' . CLINIC_EMAIL);
    } catch (Throwable) {
        $delivered = false;
    }

    mark_notification_delivery($notificationId, $delivered);

    return $notificationId;
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

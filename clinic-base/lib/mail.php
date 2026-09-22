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
    ?string $appointmentTime = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $subject = 'Appointment confirmed - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "Your appointment with {$doctorName} is confirmed for {$date} at {$time}.\n"
        . "Thank you for choosing " . APP_NAME . ".\n\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_rescheduled(
    string $recipientName,
    string $doctorName,
    string $appointmentDate,
    ?string $appointmentTime = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $subject = 'Appointment rescheduled - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "Your appointment with {$doctorName} has been rescheduled to {$date} at {$time}.\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_cancelled(
    string $recipientName,
    string $doctorName,
    string $appointmentDate,
    ?string $appointmentTime = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $subject = 'Appointment cancelled - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "Your appointment with {$doctorName} on {$date} at {$time} has been cancelled.\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_availability_cancelled(
    string $recipientName,
    string $doctorName,
    string $appointmentDate,
    ?string $appointmentTime = null
): array {
    [$date, $time] = mail_date_time($appointmentDate, $appointmentTime);
    $subject = 'Appointment availability changed - ' . APP_NAME;
    $body = "Dear {$recipientName},\n\n"
        . "The appointment with {$doctorName} on {$date} at {$time} is no longer available.\n"
        . "Please contact " . APP_NAME . " to arrange another time.\n\n"
        . "Clinic: " . APP_NAME;

    return [$subject, $body];
}

/** @return array{0: string, 1: string} */
function mail_date_time(string $date, ?string $time): array
{
    if ($time !== null && $time !== '') {
        return [$date, $time];
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return [$date, ''];
    }

    return [date('d M Y', $timestamp), date('g:i A', $timestamp)];
}

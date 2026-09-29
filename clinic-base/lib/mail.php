<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

/** Mail is best-effort: the notification row remains the durable audit record. */
function send_mail(string $sender, string $recipient, string $subject, string $body, ?int $appointmentId = null): bool
{
    try {
        $row = q('INSERT INTO `notifications` (`sender`, `recipient`, `Subject`, `Body`, `appointmentID`, `deliveryStatus`, `SentAt`)
                  VALUES (:sender, :recipient, :subject, :body, :appointment, \'logged\', NOW())', [
            'sender' => $sender, 'recipient' => $recipient, 'subject' => $subject, 'body' => $body, 'appointment' => $appointmentId,
        ]);
        $sent = @mail($recipient, $subject, $body);
        q('UPDATE `notifications` SET `deliveryStatus` = :status, `SentAt` = NOW() WHERE `notificationID` = LAST_INSERT_ID()', ['status' => $sent ? 'sent' : 'failed']);
        if (!$sent) {
            app_log('mail delivery failed', ['recipient' => $recipient, 'subject' => $subject]);
        }
        return $sent;
    } catch (Throwable $exception) {
        app_log('mail operation failed', ['exception' => $exception, 'recipient' => $recipient]);
        return false;
    }
}

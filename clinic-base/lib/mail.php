<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

/** Mail is best-effort: the notification row remains the durable audit record. */
function send_mail(string $sender, string $recipient, string $subject, string $body, ?int $appointmentId = null): bool
{
    $notificationId = null;
    try {
        $row = q('INSERT INTO `notifications` (`sender`, `recipient`, `Subject`, `Body`, `appointmentID`, `deliveryStatus`, `SentAt`)
                  VALUES (:sender, :recipient, :subject, :body, :appointment, \'logged\', NULL)', [
            'sender' => $sender, 'recipient' => $recipient, 'subject' => $subject, 'body' => $body, 'appointment' => $appointmentId,
        ]);
        $notificationId = (int) db()->lastInsertId();
        $sent = @mail($recipient, $subject, $body);
        q('UPDATE `notifications` SET `deliveryStatus` = :status, `SentAt` = NOW() WHERE `notificationID` = :notification_id', [
            'status' => $sent ? 'sent' : 'failed', 'notification_id' => $notificationId,
        ]);
        if (!$sent) {
            app_log('mail delivery failed', ['recipient' => $recipient, 'subject' => $subject]);
        }
        return $sent;
    } catch (Throwable $exception) {
        if ($notificationId !== null) {
            try {
                q('UPDATE `notifications` SET `deliveryStatus` = \'failed\', `SentAt` = NOW() WHERE `notificationID` = :notification_id', ['notification_id' => $notificationId]);
            } catch (Throwable $updateException) {
                app_log('notification status update failed', ['exception' => $updateException, 'notification_id' => $notificationId]);
            }
        }
        app_log('mail operation failed', ['exception' => $exception, 'recipient' => $recipient]);
        return false;
    }
}

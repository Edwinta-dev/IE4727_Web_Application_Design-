<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** Latest messages addressed to this patient's stored email, including unlinked notices.
 * @return list<array<string, mixed>>
 */
function notifications_for_patient(int $patientId): array
{
    return q_all(
        'SELECT n.`notificationID`, n.`Subject`, n.`Body`, n.`SentAt`
         FROM `notifications` n
         INNER JOIN `patient` p ON p.`Email` = n.`recipient`
         WHERE p.`PatientID` = :patient_id
         ORDER BY n.`SentAt` DESC, n.`notificationID` DESC
         LIMIT 20',
        ['patient_id' => $patientId]
    );
}

/** @return list<array<string, mixed>> */
function recent_notifications(int $limit = 20): array
{
    $limit = max(1, min($limit, 100));

    return q_all(
        'SELECT `notificationID`, `sender`, `recipient`, `Subject`, `Body`,
                `appointmentID`, `deliveryStatus`, `SentAt`
         FROM `notifications`
         ORDER BY `SentAt` DESC, `notificationID` DESC
         LIMIT ' . $limit
    );
}

/**
 * Return the notification log for the administrator outbox.
 *
 * @return list<array<string, mixed>>
 */
function outbox_notifications(?string $deliveryStatus = null): array
{
    $sql = 'SELECT `notificationID`, `sender`, `recipient`, `Subject`, `Body`,
                   `appointmentID`, `deliveryStatus`, `SentAt`
            FROM `notifications`';
    $params = [];

    if ($deliveryStatus !== null) {
        $sql .= ' WHERE `deliveryStatus` = :delivery_status';
        $params['delivery_status'] = $deliveryStatus;
    }

    $sql .= ' ORDER BY `SentAt` DESC, `notificationID` DESC';

    return q_all($sql, $params);
}

function notifications_generated_today(): int
{
    return (int) (q_val(
        'SELECT COUNT(*) FROM `notifications` WHERE DATE(`SentAt`) = CURDATE()'
    ) ?? 0);
}

/** @return list<array<string, mixed>> */
function notifications_for_appointment(int $id): array
{
    return q_all(
        'SELECT `notificationID`, `sender`, `recipient`, `Subject`, `Body`,
                `appointmentID`, `deliveryStatus`, `SentAt`
         FROM `notifications`
         WHERE `appointmentID` = :appointment_id
         ORDER BY `SentAt` DESC, `notificationID` DESC',
        ['appointment_id' => $id]
    );
}

function log_notification(
    string $toEmail,
    string $subject,
    string $body,
    ?int $appointmentId = null
): int {
    q(
        'INSERT INTO `notifications`
            (`sender`, `recipient`, `Subject`, `Body`, `appointmentID`, `deliveryStatus`)
         VALUES (:sender, :recipient, :subject, :body, :appointment_id, \'logged\')',
        [
            'sender' => CLINIC_EMAIL,
            'recipient' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'appointment_id' => $appointmentId,
        ]
    );

    return (int) db()->lastInsertId();
}

function mark_notification_delivery(int $notificationId, bool $delivered): void
{
    q(
        'UPDATE `notifications`
         SET `deliveryStatus` = :delivery_status
         WHERE `notificationID` = :notification_id',
        [
            'delivery_status' => $delivered ? 'sent' : 'failed',
            'notification_id' => $notificationId,
        ]
    );
}

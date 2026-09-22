<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

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

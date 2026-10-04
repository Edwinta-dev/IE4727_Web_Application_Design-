<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';

$marker = 'outbox_test_' . bin2hex(random_bytes(4));
q(
    'INSERT INTO `notifications`
        (`sender`, `recipient`, `Subject`, `Body`, `deliveryStatus`, `SentAt`)
     VALUES (:sender, :recipient, :subject, :body, :status, NOW())',
    [
        'sender' => 'test@example.local',
        'recipient' => $marker . '@example.local',
        'subject' => $marker,
        'body' => "First line\nSecond line",
        'status' => 'failed',
    ]
);
$notificationId = (int) db()->lastInsertId();

try {
    $failed = outbox_notifications('failed');
    if ($failed === [] || (int) $failed[0]['notificationID'] !== $notificationId) {
        throw new RuntimeException('outbox status filter did not return the inserted message');
    }
    if (notifications_generated_today() < 1) {
        throw new RuntimeException('outbox today count did not include the inserted message');
    }
} finally {
    q('DELETE FROM `notifications` WHERE `notificationID` = :notification_id', [
        'notification_id' => $notificationId,
    ]);
}

$source = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/admin/outbox.php');
foreach (['require_admin()', 'details', 'recipient', 'empty-state'] as $needle) {
    if (strpos($source, $needle) === false) {
        throw new RuntimeException("outbox page is missing {$needle}");
    }
}

echo "PASS: outbox checks\n";

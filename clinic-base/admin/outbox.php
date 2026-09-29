<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';
require_admin();
$notifications = list_notifications();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Notification outbox</title></head>
<body>
<main>
<h1>Notification outbox</h1>
<p>Local delivery audit for appointment and cancellation notifications.</p>
<img src="/assets/img/clinic-logo.svg" alt="Clinic logo">
<?php if ($notifications === []): ?>
<p class="empty-state">No notifications have been logged.</p>
<?php else: ?>
<table><thead><tr><th>Recipient</th><th>Subject</th><th>Appointment</th><th>Status</th><th>Sent at</th></tr></thead><tbody>
<?php foreach ($notifications as $notification): ?>
<tr><td><?= e($notification['recipient']) ?></td><td><?= e($notification['Subject']) ?></td><td><?= e((string) ($notification['appointmentID'] ?? '')) ?></td><td><?= e($notification['deliveryStatus']) ?></td><td><?= e((string) ($notification['SentAt'] ?? '')) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</main>
</body>
</html>

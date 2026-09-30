<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';

require_admin();

$allowedStatuses = ['logged', 'sent', 'failed'];
$status = trim((string) ($_GET['deliveryStatus'] ?? ''));
$statusFilter = in_array($status, $allowedStatuses, true) ? $status : null;
$notifications = outbox_notifications($statusFilter);
$todayCount = notifications_generated_today();

$pageTitle = 'Notification Outbox';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
?>
<main class="admin-outbox">
    <img src="<?= e(url('/assets/img/clinic-logo.svg')) ?>" width="96" height="96" loading="eager" decoding="async" alt="Clinic logo">
    <h1>Notification outbox</h1>
    <p>Messages generated today: <strong><?= e((string) $todayCount) ?></strong></p>

    <section class="outbox-filters" aria-labelledby="outbox-filters-heading">
        <h2 id="outbox-filters-heading">Filter messages</h2>
        <form method="get" action="<?= e(url('/admin/outbox.php')) ?>">
            <?= csrf_field() ?>
            <label for="deliveryStatus">Delivery status</label>
            <select id="deliveryStatus" name="deliveryStatus">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= e($option) ?>"<?= e($statusFilter === $option ? ' selected' : '') ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Apply filter</button>
            <a href="<?= e(url('/admin/outbox.php')) ?>">Clear</a>
        </form>
    </section>

    <section class="outbox-list" aria-labelledby="outbox-list-heading">
        <h2 id="outbox-list-heading">Message log</h2>
        <?php if ($notifications === []): ?>
            <p class="empty-state">No messages match this filter.</p>
        <?php else: ?>
        <table><caption>Logged appointment notification delivery status</caption>
                <thead>
                    <tr>
                        <th scope="col">Sent at</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Delivery status</th>
                        <th scope="col">Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td><?= e($notification['SentAt']) ?></td>
                            <td><?= e($notification['recipient']) ?></td>
                            <td><?= e($notification['Subject']) ?></td>
                            <td><?= e($notification['deliveryStatus']) ?></td>
                            <td>
                                <details class="notification-details">
                                    <summary>View full message</summary>
                                    <div class="notification-body"><?= nl2br(e($notification['Body']), false) ?></div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';

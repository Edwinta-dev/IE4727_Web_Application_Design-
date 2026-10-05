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
    <section class="page-intro">
        <div class="page-intro-copy">
            <h1>Notification outbox</h1>
            <p>Messages generated today: <strong><?= e((string) $todayCount) ?></strong></p>
        </div>
    </section>
    <?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'flash.php'; ?>

    <section class="outbox-filters" aria-labelledby="outbox-filters-heading">
        <h2 id="outbox-filters-heading">Filter messages</h2>
        <form method="get" action="<?= e(url('/admin/outbox.php')) ?>">
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
        <?php if (MAIL_DELIVERY === 'off'): ?>
            <p class="outbox-delivery-legend">Logged - local mail delivery not configured</p>
        <?php endif; ?>
        <?php if ($notifications === []): ?>
            <p class="empty-state">No messages match this filter.</p>
        <?php else: ?>
        <div class="outbox-table-scroll" role="region" aria-label="Message log table" tabindex="0">
            <table aria-labelledby="outbox-list-heading">
                <colgroup>
                    <col class="outbox-date-column">
                    <col class="outbox-recipient-column">
                    <col class="outbox-subject-column">
                    <col class="outbox-status-column">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Sent at</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Delivery status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td><time datetime="<?= e($notification['SentAt']) ?>"><span><?= e(date('d M Y', strtotime($notification['SentAt']))) ?></span><span><?= e(date('H:i', strtotime($notification['SentAt']))) ?></span></time></td>
                            <td><?= e($notification['recipient']) ?></td>
                            <td>
                                <details class="notification-details">
                                    <summary><?= e(preg_replace('/ - Clinic Appointment Portal$/', '', (string) $notification['Subject'])) ?></summary>
                                    <div class="notification-body"><?= nl2br(e($notification['Body']), false) ?></div>
                                </details>
                            </td>
                            <td><span class="status-label status-<?= e((string) $notification['deliveryStatus']) ?>"><?= e($notification['deliveryStatus']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';

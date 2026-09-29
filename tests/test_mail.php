<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'mail.php';
require_once $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'notifications.php';

$source = file_get_contents($root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'mail.php');
foreach (['`recipient`', '`appointmentID`', '`deliveryStatus`', '`SentAt`', 'mail('] as $needle) {
    if ($source === false || strpos($source, $needle) === false) {
        throw new RuntimeException("mail boundary missing {$needle}");
    }
}

$rows = list_notifications();
foreach ($rows as $row) {
    if (!array_key_exists('recipient', $row) || !array_key_exists('appointmentID', $row)
        || !array_key_exists('deliveryStatus', $row) || !array_key_exists('SentAt', $row)) {
        throw new RuntimeException('outbox row is missing an audit field');
    }
    if (!in_array($row['deliveryStatus'], ['logged', 'sent', 'failed'], true)) {
        throw new RuntimeException('invalid delivery status');
    }
}

echo "OK\n";

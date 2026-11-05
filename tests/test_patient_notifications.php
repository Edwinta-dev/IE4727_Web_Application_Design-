<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';

db()->beginTransaction();
try {
    $email = 'issue143-model@example.local';
    q('INSERT INTO patient (FullName, User, HashPass, Email) VALUES (:name, :user, :hash, :email)',
        ['name' => 'Message test', 'user' => 'issue143-model', 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => $email]);
    $patientId = (int) db()->lastInsertId();
    assert_eq(notifications_for_patient($patientId), [], 'empty inbox');
    $ids = [];
    for ($i = 0; $i < 22; $i++) {
        $ids[] = log_notification($email, 'Subject ' . $i, '<script>unsafe</script>');
        q('UPDATE notifications SET SentAt = :time WHERE notificationID = :id',
            ['time' => '2040-01-01 09:00:00', 'id' => $ids[$i]]);
    }
    log_notification('other143@example.local', 'Private other message', 'Private body');
    $rows = notifications_for_patient($patientId);
    assert_eq(array_map('intval', array_column($rows, 'notificationID')), array_slice(array_reverse($ids), 0, 20), 'latest 20; timestamp ties use id; recipients isolated');
    q('UPDATE notifications SET SentAt = :time WHERE notificationID = :id',
        ['time' => '2041-01-01 09:00:00', 'id' => $ids[0]]);
    assert_eq((int) notifications_for_patient($patientId)[0]['notificationID'], $ids[0], 'time takes precedence over id');
    assert_eq(notifications_for_patient(0), [], 'unknown patient sees nothing');
} finally {
    db()->rollBack();
}
echo "OK: #143 patient notification isolation, empty state, latest limit and stable ordering\n";

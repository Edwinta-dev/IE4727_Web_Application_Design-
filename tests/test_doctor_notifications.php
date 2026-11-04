<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/notifications.php';

db()->beginTransaction();
try {
    $email = 'doctor-notify@example.local';
    q('INSERT INTO doctor (FullName, User, HashPass, Email, Specialty, Qualifications, Languages, WriteUp, ImageURL) VALUES (:name, :user, :hash, :email, :specialty, :qual, :lang, :write, :img)',
        ['name' => 'Dr Notify', 'user' => 'doctor-notify', 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => $email,
         'specialty' => 'General Practice', 'qual' => 'MBBS', 'lang' => 'English', 'write' => 'Test doctor.', 'img' => '']);
    $doctorId = (int) db()->lastInsertId();
    assert_eq(notifications_for_doctor($doctorId), [], 'empty inbox');
    $ids = [];
    for ($i = 0; $i < 22; $i++) {
        $ids[] = log_notification($email, 'Subject ' . $i, 'Body');
        q('UPDATE notifications SET SentAt = :time WHERE notificationID = :id', ['time' => '2040-01-01 09:00:00', 'id' => $ids[$i]]);
    }
    log_notification('someone-else@example.local', 'Private other message', 'Private body');
    $rows = notifications_for_doctor($doctorId);
    assert_eq(array_map('intval', array_column($rows, 'notificationID')), array_slice(array_reverse($ids), 0, 20), 'latest 20, ties by id, recipients isolated');
    assert_eq(notifications_for_doctor(0), [], 'unknown doctor sees nothing');
} finally {
    db()->rollBack();
}
echo "OK: doctor notification isolation, empty state, latest limit and stable ordering\n";

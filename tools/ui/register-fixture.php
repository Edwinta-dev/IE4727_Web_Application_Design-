<?php

declare(strict_types=1);
require_once __DIR__ . '/test-database.php';
require_once dirname(__DIR__, 2) . '/clinic-base/lib/db.php';

$action = $argv[1] ?? '';
$suffix = $argv[2] ?? '';
if (!preg_match('/^[0-9]+_[a-f0-9]+$/D', $suffix)) {
    throw new RuntimeException('An exact registration run suffix is required.');
}
$users = ['ui_patient_js_' . $suffix, 'ui_patient_nojs_' . $suffix,
    'ui_patient_crafted-js_' . $suffix, 'ui_patient_crafted-nojs_' . $suffix];
$doctor = 'ui_doctor__' . $suffix;
if ($action === 'cleanup') {
    foreach ($users as $user) {
        q('DELETE FROM `patient` WHERE `User` = :user', ['user' => $user]);
    }
    q('DELETE FROM `doctor` WHERE `User` = :user', ['user' => $doctor]);
} elseif ($action !== 'counts') {
    throw new RuntimeException('Unknown fixture action.');
}
$patients = 0;
foreach ($users as $user) {
    $patients += (int) q('SELECT COUNT(*) FROM `patient` WHERE `User` = :user', ['user' => $user])->fetchColumn();
}
echo json_encode(['database' => q('SELECT DATABASE()')->fetchColumn(),
    'patient' => $patients, 'doctor' => (int) q('SELECT COUNT(*) FROM `doctor` WHERE `User` = :user', ['user' => $doctor])->fetchColumn()], JSON_THROW_ON_ERROR);

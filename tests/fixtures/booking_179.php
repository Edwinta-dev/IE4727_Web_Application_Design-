<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/clinic-base/models/slots.php';

$date = (new DateTimeImmutable('today'))->modify('+3 days')->format('Y-m-d');
$suffix = bin2hex(random_bytes(4));
$doctors = [];
// Dr. Ben Vale keeps the legacy "Dentistry" value: specialty browsing must
// still list him under Dental (#196).
foreach (['Dr. Ada River' => 'Dental', 'Dr. Ben Vale' => 'Dentistry'] as $doctorName => $specialty) {
    q('INSERT INTO `doctor` (`FullName`, `User`, `HashPass`, `Email`, `Specialty`)
       VALUES (:name, :user, :hash, :email, :specialty)',
        ['name' => $doctorName, 'user' => 'issue179' . $suffix . count($doctors),
            'hash' => password_hash('Password123', PASSWORD_DEFAULT),
            'email' => 'issue179' . $suffix . count($doctors) . '@example.test', 'specialty' => $specialty]);
    $doctors[] = (int) db()->lastInsertId();
}
$slotIds = [];
foreach ([
    [$doctors[0], '06:30:00', 'Available'],
    [$doctors[0], '07:00:00', 'Available'],
    [$doctors[0], '07:30:00', 'Blocked'],
    [$doctors[1], '07:00:00', 'Available'],
    [$doctors[1], '07:45:00', 'Available'],
] as [$doctor, $time, $status]) {
    q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`)
       VALUES (:doctor, :start, :status)',
        ['doctor' => $doctor, 'start' => $date . ' ' . $time, 'status' => $status]);
    $slotIds[$doctor . ':' . $time] = (int) db()->lastInsertId();
}
return ['date' => $date, 'doctors' => $doctors, 'slots' => $slotIds];

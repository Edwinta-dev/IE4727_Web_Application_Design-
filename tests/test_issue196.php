<?php

declare(strict_types=1);

// #196: changing the specialty while rescheduling must not drop the pinned
// doctor from the page (null lookup, empty data-doctor-id, Confirm never enabled).
require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';

test('reschedule keeps its doctor when another specialty is requested', static function (): void {
    $suffix = bin2hex(random_bytes(4));
    q('INSERT INTO `doctor` (`FullName`, `User`, `HashPass`, `Email`, `Specialty`)
       VALUES (:name, :user, :hash, :email, :specialty)',
        ['name' => 'Dr. Issue Onenine', 'user' => 'issue196d' . $suffix,
            'hash' => password_hash('Password123', PASSWORD_DEFAULT),
            'email' => 'issue196d' . $suffix . '@example.test', 'specialty' => 'General Practice']);
    $doctorId = (int) db()->lastInsertId();
    q('INSERT INTO `patient` (`FullName`, `User`, `HashPass`, `Email`, `Gender`, `Phone`, `Allergies`)
       VALUES (:name, :user, :hash, :email, :gender, :phone, :allergies)',
        ['name' => 'Issue Patient', 'user' => 'issue196p' . $suffix,
            'hash' => password_hash('Password123', PASSWORD_DEFAULT),
            'email' => 'issue196p' . $suffix . '@example.test', 'gender' => 'Other',
            'phone' => '81234567', 'allergies' => '[]']);
    $patientId = (int) db()->lastInsertId();
    $day = (new DateTimeImmutable('today'))->modify('+2 days')->format('Y-m-d');
    foreach (['09:00:00' => 'Booked', '10:00:00' => 'Available'] as $time => $status) {
        q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor, :start, :status)',
            ['doctor' => $doctorId, 'start' => $day . ' ' . $time, 'status' => $status]);
    }
    $bookedSlot = (int) q_one('SELECT `slotID` FROM `slots` WHERE `DoctorID` = :doctor AND `SlotDateTime` = :start',
        ['doctor' => $doctorId, 'start' => $day . ' 09:00:00'])['slotID'];
    q("INSERT INTO `appointment` (`DoctorID`, `PatientID`, `slotID`, `appointmentDateTime`, `Status`)
       VALUES (:doctor, :patient, :slot, :at, 'Future')",
        ['doctor' => $doctorId, 'patient' => $patientId, 'slot' => $bookedSlot, 'at' => $day . ' 09:00:00']);
    $appointmentId = (int) db()->lastInsertId();

    $runner = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ie4727-issue196-' . getmypid() . '.php';
    $book = var_export(dirname(__DIR__) . '/clinic-base/book.php', true);
    $get = var_export(['reschedule' => (string) $appointmentId, 'doctor' => (string) $doctorId,
        'specialty' => 'Dental', 'date' => $day], true);
    file_put_contents($runner, "<?php\nini_set('session.save_path', sys_get_temp_dir());\nsession_start();\n"
        . "\$_SESSION['role'] = 'patient';\n\$_SESSION['id'] = {$patientId};\n"
        . "\$_SERVER['REQUEST_METHOD'] = 'GET';\n\$_GET = {$get};\nrequire {$book};\n");
    try {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1', $output, $status);
    } finally {
        @unlink($runner);
    }
    $html = implode("\n", $output);
    assert_true($status === 0, 'reschedule page renders: ' . substr($html, 0, 400));
    assert_true(!preg_match('/\b(Warning|Notice|Deprecated|Fatal error)\b/', $html), 'no PHP diagnostics in the reschedule page');
    assert_true(!str_contains($html, 'data-doctor-id=""'), 'no doctor entry without an id');
    assert_contains($html, 'data-doctor-id="' . $doctorId . '"', 'the pinned doctor is still offered');
    assert_contains($html, 'id="specialty-locked"', 'the specialty filter explains why it is locked');
    assert_contains($html, '<option value="General Practice" selected>', 'the specialty follows the pinned doctor');
});

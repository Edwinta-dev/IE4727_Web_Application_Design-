<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/remarks_inspection.php';
require_once dirname(__DIR__) . '/clinic-base/models/booking.php';

test('remarks inspection: readable fixtures, lossless edits and zero writes', static function (): void {
assert_true(DB_NAME === 'ie4727db_test' && q_val('SELECT DATABASE()') === 'ie4727db_test', 'inspection fixtures require resolved test DB');
$doctor = (int) q_val('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patient = (int) q_val('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
$past = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('-1 day')->format('Y-m-d H:i:s');
q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`) VALUES (:doctor, :patient, :past)', compact('doctor', 'patient', 'past'));
$id = (int) db()->lastInsertId();
$empty = ['reason' => '', 'doctor_remarks' => '', 'legacy' => null];
$unicode = ['reason' => "Synthetic 咳嗽 / café\nSecond line", 'doctor_remarks' => "Synthetic 医生\nReview", 'legacy' => "Synthetic earlier\nUnattributed"];
$malformed = VISIT_REMARKS_PREFIX . '{"reason":"synthetic incomplete"}';
$fixtures = [
    [encode_visit_remarks('Synthetic reason', 'Synthetic remarks'), 'Completed', ['reason' => 'Synthetic reason', 'doctor_remarks' => 'Synthetic remarks', 'legacy' => null]],
    [encode_visit_remarks(...array_values($unicode)), 'Completed', $unicode],
    ['Synthetic plain', 'Future', ['reason' => 'Synthetic plain', 'doctor_remarks' => '', 'legacy' => null]],
    ['Synthetic plain', 'Completed', ['reason' => '', 'doctor_remarks' => '', 'legacy' => 'Synthetic plain']],
    ['', 'Completed', $empty],
    [null, 'Completed', $empty],
    [encode_visit_remarks(''), 'Completed', $empty],
    [$malformed, 'Completed', ['reason' => '', 'doctor_remarks' => '', 'legacy' => $malformed]],
    [$malformed, 'Future', ['reason' => $malformed, 'doctor_remarks' => '', 'legacy' => null]],
    [VISIT_REMARKS_PREFIX . '{broken', 'Completed', ['reason' => '', 'doctor_remarks' => '', 'legacy' => VISIT_REMARKS_PREFIX . '{broken']],
    [VISIT_REMARKS_PREFIX . '{"reason":"synthetic","doctor_remarks":"","legacy":null }', 'Completed', ['reason' => '', 'doctor_remarks' => '', 'legacy' => VISIT_REMARKS_PREFIX . '{"reason":"synthetic","doctor_remarks":"","legacy":null }']],
    ["\x1Eclinic-remarks:2:{}", 'Completed', ['reason' => '', 'doctor_remarks' => '', 'legacy' => "\x1Eclinic-remarks:2:{}"]],
    ["Synthetic plain\nUnicode 你好", 'Completed', ['reason' => '', 'doctor_remarks' => '', 'legacy' => "Synthetic plain\nUnicode 你好"]],
];
$fingerprint = static function (): string {
    // Hash all five tables without emitting their contents in test/log output.
    $rows = [q_all('SELECT * FROM `appointment` ORDER BY `appointmentID`'),
        q_all('SELECT * FROM `slots` ORDER BY `slotID`'), q_all('SELECT * FROM `notifications` ORDER BY `notificationID`'),
        q_all('SELECT * FROM `patient` ORDER BY `PatientID`'), q_all('SELECT * FROM `doctor` ORDER BY `DoctorID`')];
    return hash('sha256', serialize($rows));
};
$command = static function (string $arguments): array {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/inspect_remarks.php') . ' ' . $arguments . ' 2>&1', $output, $status);
    return [$status, implode("\n", $output) . "\n"];
};
try {
    foreach ($fixtures as $index => [$stored, $status, $expected]) {
        q('UPDATE `appointment` SET `Remarks` = :remarks, `Status` = :status WHERE `appointmentID` = :id', ['remarks' => $stored, 'status' => $status, 'id' => $id]);
        $before = $fingerprint();
        $parts = inspect_appointment_remarks($id, 'ie4727db_test');
        assert_true($parts === $expected, "fixture {$index}: inspection matches expected decoded fields");
        assert_true(decode_visit_remarks($stored, $status) === $expected, "fixture {$index}: app decoder agrees");
        [$exit, $report] = $command('--database=ie4727db_test --appointment=' . $id);
        assert_true($exit === 0 && $report === "Database: ie4727db_test\nAppointment: {$id}\n" . format_remarks_inspection($expected), "fixture {$index}: CLI readable report");
        assert_true($fingerprint() === $before, "fixture {$index}: inspection writes zero rows across all tables");
        assert_true(decode_visit_remarks(encode_visit_remarks(...array_values($parts)), $status) === $parts, "fixture {$index}: lossless round trip");
        assert_true(save_visit_notes($id, $doctor, ['Remarks' => 'Synthetic edit']), "fixture {$index}: editable past visit");
        $edited = inspect_appointment_remarks($id, 'ie4727db_test');
        assert_true($edited === ['reason' => $expected['reason'], 'doctor_remarks' => 'Synthetic edit', 'legacy' => $expected['legacy']], "fixture {$index}: edit preserves reason and earlier text");
    }
    assert_true(format_remarks_inspection($empty) === "Patient reason:\n  (empty)\nDoctor remarks:\n  (empty)\nEarlier text (author unknown):\n  (empty)\n", 'empty fields labelled deterministically');
    assert_true(format_remarks_inspection(['reason' => "你好\nline", 'doctor_remarks' => "\x1B[31m", 'legacy' => "\x1Ebroken"]) === "Patient reason:\n  你好\n  line\nDoctor remarks:\n  \\x1B[31m\nEarlier text (author unknown):\n  \\x1Ebroken\n", 'Unicode/newlines readable and terminal controls escaped');
    $before = $fingerprint();
    foreach (['', '--database=ie4727db_test --appointment=0', '--database=ie4727db_test --appointment=1 --write=yes', '--database=ie4727db --appointment=1', '--database=ie4727db_test --appointment=1 --appointment=2'] as $arguments) {
        [$exit] = $command($arguments);
        assert_true($exit === 2, 'invalid/mismatched options refused before database access');
    }
    [$exit, $report] = $command('--database=ie4727db_test --appointment=' . PHP_INT_MAX);
    assert_true($exit === 1 && $report === "Appointment not found.\n", 'missing record fails without data');
    assert_true($fingerprint() === $before, 'refusal/missing paths do not write');
} finally {
    q('DELETE FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]);
}
});

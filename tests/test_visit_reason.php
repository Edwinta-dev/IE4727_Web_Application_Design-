<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';

$doctorId = (int) q_val('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
$patientId = (int) q_val('SELECT `PatientID` FROM `patient` ORDER BY `PatientID` LIMIT 1');
$when = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+' . random_int(2000, 3000) . ' days')->format('Y-m-d H:i:s');
q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`) VALUES (:doctor_id, :when)', ['doctor_id' => $doctorId, 'when' => $when]);
$slotId = (int) db()->lastInsertId();
$reason = "Headache, then nausea.\nText resembling a record: \x1Eclinic-remarks:1:{} <check>";
$booking = book_appointment($patientId, $slotId, $reason);
assert_true($booking['ok'], 'book with a distinct multiline reason');
$id = (int) $booking['appointment_id'];
$stored = q_one('SELECT `Remarks`, `Status` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]);
assert_eq(decode_visit_remarks($stored['Remarks'], $stored['Status'])['reason'], $reason, 'booked reason round trips');

// Move only this isolated fixture to a past appointment; the server time gate remains active.
$past = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('-1 day')->format('Y-m-d H:i:s');
q('UPDATE `appointment` SET `appointmentDateTime` = :past WHERE `appointmentID` = :id', ['past' => $past, 'id' => $id]);
$fields = ['Diagnosis' => 'Migraine', 'Prescription' => 'Rest', 'Treatment' => 'Assessment', 'FollowUp' => 1, 'Remarks' => "First note,\n<private>"];
assert_true(save_visit_notes($id, $doctorId, $fields), 'first visit save');
foreach (["Second note: ;,\nline", 'Third note & final'] as $note) {
    $fields['Remarks'] = $note;
    assert_true(save_visit_notes($id, $doctorId, $fields), 'completed visit remains editable');
    $row = completed_appointment_for_patient($patientId, $id);
    assert_true($row !== null, 'patient can read completed visit');
    $parts = decode_visit_remarks($row['Remarks'], $row['Status']);
    assert_eq($parts['reason'], $reason, 'each edit preserves booking reason');
    assert_eq($parts['doctor_remarks'], $note, 'each edit stores doctor remarks');
    assert_eq([$row['Diagnosis'], $row['Prescription'], $row['Treatment'], (int) $row['FollowUp']], ['Migraine', 'Rest', 'Assessment', 1], 'clinical fields persist');
}
assert_eq(completed_appointment_for_patient(PHP_INT_MAX, $id), null, 'foreign patient cannot read completed visit');
$visitBefore = q_one('SELECT `Status`, `Diagnosis`, `Prescription`, `Treatment`, `FollowUp`, `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]);
$noticeCount = (int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $id]);
assert_eq(save_visit_notes($id, $doctorId + 99999, ['Remarks' => 'foreign']), false, 'foreign doctor denied');
q('UPDATE `appointment` SET `appointmentDateTime` = :when WHERE `appointmentID` = :id', ['when' => $when, 'id' => $id]);
assert_eq(save_visit_notes($id, $doctorId, ['Remarks' => 'future']), false, 'future completed visit denied');
q('UPDATE `appointment` SET `appointmentDateTime` = :past WHERE `appointmentID` = :id', ['past' => $past, 'id' => $id]);
assert_eq(q_one('SELECT `Status`, `Diagnosis`, `Prescription`, `Treatment`, `FollowUp`, `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]), $visitBefore, 'rejected writes change no visit fields');
assert_eq((int) q_val('SELECT COUNT(*) FROM `notifications` WHERE `appointmentID` = :id', ['id' => $id]), $noticeCount, 'rejected writes send no notifications');

assert_eq(decode_visit_remarks('Plain, <old>\ntext', 'Completed')['legacy'], 'Plain, <old>\ntext', 'completed legacy remains unattributed');
assert_eq(decode_visit_remarks('Plain reason', 'Future')['reason'], 'Plain reason', 'future legacy is booking reason');
assert_eq(decode_visit_remarks('', 'Completed')['legacy'], null, 'blank legacy');
assert_eq(decode_visit_remarks(VISIT_REMARKS_PREFIX . '{"reason":"fake"}', 'Completed')['legacy'], VISIT_REMARKS_PREFIX . '{"reason":"fake"}', 'serialization-looking legacy remains intact');
assert_eq(decode_visit_remarks(encode_visit_remarks('', ''), 'Completed')['reason'], '', 'blank encoded values');
$legacyText = "Old note; author unknown\n<not a boundary>";
q('UPDATE `appointment` SET `Remarks` = :legacy WHERE `appointmentID` = :id', ['legacy' => $legacyText, 'id' => $id]);
assert_true(save_visit_notes($id, $doctorId, ['Remarks' => 'New doctor note']), 'legacy completed row remains editable');
$legacyParts = decode_visit_remarks((string) q_val('SELECT `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]), 'Completed');
assert_eq([$legacyParts['reason'], $legacyParts['legacy'], $legacyParts['doctor_remarks']], ['', $legacyText, 'New doctor note'], 'legacy text survives save without invented provenance');
$max = 65535;
$fits = str_repeat('x', $max - strlen(encode_visit_remarks('')));
assert_eq(strlen(encode_visit_remarks($fits)), $max, 'TEXT byte boundary accepted');
assert_true(strlen(encode_visit_remarks('é')) > strlen(encode_visit_remarks('e')), 'capacity counts UTF-8 bytes');
try {
    encode_visit_remarks($fits . 'x');
    throw new RuntimeException('over-capacity record was accepted');
} catch (InvalidArgumentException $exception) {
    assert_contains($exception->getMessage(), 'too long', 'capacity error visible');
}
$remarksBefore = q_val('SELECT `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]);
try {
    save_visit_notes($id, $doctorId, ['Remarks' => str_repeat('z', 65535)]);
    throw new RuntimeException('over-capacity save was accepted');
} catch (InvalidArgumentException $exception) {
    assert_eq(q_val('SELECT `Remarks` FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]), $remarksBefore, 'over-capacity save is atomic');
}

q('DELETE FROM `notifications` WHERE `appointmentID` = :id', ['id' => $id]);
q('DELETE FROM `appointment` WHERE `appointmentID` = :id', ['id' => $id]);
q('DELETE FROM `slots` WHERE `slotID` = :id', ['id' => $slotId]);

echo "PASS: visit reason and remarks persistence\n";

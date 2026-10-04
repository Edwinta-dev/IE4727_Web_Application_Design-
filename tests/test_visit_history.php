<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/visit-history-fixture.php';

$historySeed = seed_visit_history();
try {
    $doctor = $historySeed['accounts']['doctor']['own']['id'];
    $patient = $historySeed['accounts']['patient']['own']['id'];
    $visits = $historySeed['visits'];
    $expected = [$visits['earlier'], $visits['earliest']];
    $historyIds = static fn (): array => array_map(static fn (array $row): int => (int) $row['appointmentID'], patient_history($patient, $doctor, $visits['current']));
    assert_eq($historyIds(), $expected, 'only earlier completed encounters, newest first, before save');
    assert_true(save_visit_notes($visits['current'], $doctor, [
        'Diagnosis' => 'Saved diagnosis', 'Treatment' => 'Saved treatment', 'Prescription' => 'Saved prescription',
        'FollowUp' => 1, 'Remarks' => 'Saved doctor remarks',
    ]), 'current visit saved');
    assert_eq($historyIds(), $expected, 'current and later visits excluded after save and repeat read');
    assert_eq($historyIds(), $expected, 'refresh preserves history');
    assert_eq(patient_history($patient, $doctor, $visits['earliest']), [], 'first encounter has no previous history');
    foreach ([$visits['foreign_doctor'], $visits['foreign_patient'], 0] as $foreign) {
        assert_eq(patient_history($patient, $doctor, $foreign), [], 'foreign or missing history anchor denied');
    }
    assert_eq(patient_history($historySeed['accounts']['patient']['foreign']['id'], $doctor, $visits['current']), [], 'foreign patient scope denied');
    assert_eq(patient_history($patient, $historySeed['accounts']['doctor']['foreign']['id'], $visits['current']), [], 'foreign doctor scope denied');
    $notes = completed_appointment_for_patient($patient, $visits['current']);
    foreach (['Diagnosis', 'Treatment', 'Prescription'] as $field) {
        assert_eq($notes[$field], 'Saved ' . strtolower($field), 'saved current notes retained for patient');
    }
    assert_eq((int) $notes['FollowUp'], 1, 'follow-up retained');
    assert_eq(decode_visit_remarks($notes['Remarks'], $notes['Status']), [
        'reason' => 'Reason current', 'doctor_remarks' => 'Saved doctor remarks', 'legacy' => null,
    ], 'patient reason and doctor remarks stay separate');
    assert_eq(completed_appointment_for_patient($historySeed['accounts']['patient']['foreign']['id'], $visits['current']), null, 'foreign patient notes denied');
} finally {
    cleanup_visit_history($historySeed);
}

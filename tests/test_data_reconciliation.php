<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/data_reconciliation.php';

test('reconciliation previews dependencies, preserves unrelated data and restores exact rows', static function (): void {
    reconciliation_test_guard();
    db()->beginTransaction();
    try {
        q("INSERT INTO patient (FullName,User,HashPass,Email,Allergies) VALUES ('Fixture','ui_patient_js_manual','unused','manual-reconcile@example.local','[]')");
        $original = reconciliation_snapshot(); // Explicit manual sentinel with a synthetic-looking name.
        q("INSERT INTO doctor (FullName,User,HashPass,Email) VALUES ('Fixture','reconcile_fixture','unused','reconcile@example.local')");
        $doctor = (int) db()->lastInsertId();
        q("INSERT INTO patient (FullName,User,HashPass,Email,Allergies) VALUES ('Fixture','reconcile_patient','unused','reconcile-p@example.local','[]')");
        $patient = (int) db()->lastInsertId();
        q("INSERT INTO slots (DoctorID,SlotDateTime,Status) VALUES (:doctor,DATE_ADD(NOW(),INTERVAL 100 DAY),'Booked')", ['doctor' => $doctor]);
        $slot = (int) db()->lastInsertId();
        q("INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status) VALUES (:doctor,:patient,:slot,DATE_ADD(NOW(),INTERVAL 100 DAY),'Future')", ['doctor' => $doctor, 'patient' => $patient, 'slot' => $slot]);
        $appointment = (int) db()->lastInsertId();
        q("INSERT INTO notifications (sender,recipient,Subject,Body,appointmentID) VALUES ('clinic@example.local','reconcile-p@example.local','Fixture','Fixture',:id)", ['id' => $appointment]);
        q("INSERT INTO notifications (sender,recipient,Subject,Body) VALUES ('clinic@example.local','reconcile@example.local','Unlinked fixture','Fixture')");
        q("INSERT INTO notifications (sender,recipient,Subject,Body) VALUES ('RECONCILE@EXAMPLE.LOCAL','clinic@example.local','Case variant fixture','Fixture')");
        $rows = reconciliation_snapshot();
        $fixtures = array_diff_key($rows, $original);
        $manifest = [];
        foreach ($fixtures as $ref => $row) $manifest[$ref] = ['category' => 'synthetic', 'sha256' => reconciliation_fingerprint($row), 'evidence' => 'test_data_reconciliation exact inserted ID'];
        $plan = reconciliation_plan($rows, $manifest, ['doctor:' . $doctor, 'patient:' . $patient]);
        assert_true($plan['allowed'], 'all confirmed dependencies');
        assert_eq($plan['counts'], ['doctor' => 1, 'patient' => 1, 'slots' => 1, 'appointment' => 1, 'notifications' => 3], 'includes FK and unlinked email notices, including case variants');
        assert_eq(reconciliation_snapshot(), $rows, 'dry run writes nothing');
        $bad = $manifest;
        $bad['slots:' . $slot]['category'] = 'manual';
        assert_true(!reconciliation_plan($rows, $bad, ['patient:' . $patient])['allowed'], 'manual shared slot fails closed');
        $bad = $manifest;
        $bad['doctor:' . $doctor]['sha256'] = 'stale';
        assert_true(!reconciliation_plan($rows, $bad, ['doctor:' . $doctor])['allowed'], 'changed fingerprint refuses');
        assert_eq(reconciliation_inventory($rows, [])['counts']['synthetic'], 0, 'synthetic-looking names establish no provenance');
        reconciliation_delete($plan);
        assert_eq(reconciliation_snapshot(), $original, 'manual sentinel and all unrelated seed rows remain byte identical including Future statuses');
        reconciliation_restore($fixtures);
        assert_eq(reconciliation_snapshot(), $rows, 'restore original IDs, FKs and full row content');
    } finally { db()->rollBack(); }
});

test('unattended reset and cleanup refuse live or unexpected targets before connection', static function (): void {
    // -n removes the MySQL driver: refusal must succeed before any connection attempt.
    $root = dirname(__DIR__);
    foreach (['--production', '--test extra'] as $args) {
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg($root . '/tools/db_reset.php') . ' ' . $args . ' 2>&1', $output, $status);
        assert_true($status !== 0 && str_contains(implode("\n", $output), 'only ie4727db_test'), 'unsupported reset rejected');
    }
    foreach (['apply', 'restore'] as $action) {
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg($root . '/tools/reconcile_data.php') . ' ' . $action . ' --database=ie4727db 2>&1', $output, $status);
        assert_true($status !== 0 && str_contains(implode("\n", $output), 'Refusing before connection'), 'live ' . $action . ' rejected before config/DB include');
    }
    foreach (['ie4727db', 'other_test'] as $name) {
        putenv('CLINIC_DB_NAME=' . $name);
        try {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg($root . '/tools/db_reset.php') . ' --test 2>&1', $output, $status);
            assert_true($status !== 0 && str_contains(implode("\n", $output), 'only ie4727db_test'), 'unexpected reset target rejected');
        } finally { putenv('CLINIC_DB_NAME=ie4727db_test'); }
    }
});

test('CLI preview, exclusive backup, targeted apply and confirmed restore round trip', static function (): void {
    reconciliation_test_guard();
    $before = reconciliation_snapshot();
    q("INSERT INTO patient (FullName,User,HashPass,Email,Allergies) VALUES ('CLI fixture','reconcile_cli','unused','reconcile-cli@example.local','[]')");
    $id = (int) db()->lastInsertId();
    $ref = 'patient:' . $id;
    $rows = reconciliation_snapshot();
    $directory = dirname(__DIR__) . '/UIPROBLEMS/private';
    if (!is_dir($directory)) mkdir($directory, 0700, true);
    $manifest = tempnam($directory, 'manifest-');
    $backup = $manifest . '.backup';
    file_put_contents($manifest, json_encode([$ref => ['category' => 'synthetic', 'sha256' => reconciliation_fingerprint($rows[$ref]), 'evidence' => 'Exact insert ID recorded by CLI regression']], JSON_THROW_ON_ERROR));
    $run = static function (string $action, array $options, bool $success = true): array {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/reconcile_data.php') . ' ' . escapeshellarg($action);
        foreach ($options as $key => $value) $command .= ' ' . escapeshellarg('--' . $key . '=' . $value);
        exec($command . ' 2>&1', $output, $status);
        if (!$success) {
            assert_true($status !== 0, 'CLI ' . $action . ' refuses unsafe operation');
            return [];
        }
        assert_eq($status, 0, 'CLI ' . $action . ' succeeds: ' . implode(' ', $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    };
    try {
        $options = ['database' => 'ie4727db_test', 'manifest' => $manifest, 'targets' => $ref];
        $plan = $run('preview', $options);
        assert_eq(reconciliation_snapshot(), $rows, 'CLI dry run writes nothing');
        $run('apply', $options + ['token' => 'stale', 'backup' => $backup], false);
        assert_eq(reconciliation_snapshot(), $rows, 'wrong token writes nothing');
        assert_true(!is_file($backup), 'wrong token creates no backup');
        $run('apply', $options + ['token' => $plan['token'], 'backup' => $backup]);
        assert_eq(reconciliation_snapshot(), $before, 'CLI cleanup preserves every unrelated record');
        assert_true(is_file($backup), 'private backup written before delete');
        $run('restore', ['database' => 'ie4727db_test', 'token' => $plan['token'], 'backup' => $backup]);
        assert_eq(reconciliation_snapshot(), $rows, 'documented CLI restore route works');
        $run('restore', ['database' => 'ie4727db_test', 'token' => $plan['token'], 'backup' => $backup], false);
        assert_eq(reconciliation_snapshot(), $rows, 'restore conflicts never overwrite existing rows');
        $run('apply', $options + ['token' => $plan['token'], 'backup' => $backup], false);
        assert_eq(reconciliation_snapshot(), $rows, 'existing backup is never overwritten and blocks deletion');
    } finally {
        q('DELETE FROM patient WHERE PatientID = :id', ['id' => $id]);
        if (is_file($manifest)) unlink($manifest);
        if (is_file($backup)) unlink($backup);
    }
});

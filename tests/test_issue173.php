<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';

test('footer hours use weekday minima and daily finishing times', static function (): void {
    // One row per doctor and day, as clinic_schedule_days() returns them.
    $rows = [
        ['SlotDate' => '2026-10-19', 'FirstTime' => '09:00:00', 'LastTime' => '09:30:00', 'Step' => 30],
        ['SlotDate' => '2026-10-19', 'FirstTime' => '08:00:00', 'LastTime' => '18:00:00', 'Step' => 15],
        ['SlotDate' => '2026-10-20', 'FirstTime' => '09:00:00', 'LastTime' => '09:30:00', 'Step' => '30'],
        ['SlotDate' => '2026-10-24', 'FirstTime' => '10:00:00', 'LastTime' => '10:00:00', 'Step' => null],
    ];
    assert_eq(
        clinic_opening_hours($rows),
        'Mon 08:00-18:15, Tue 09:00-10:00, Wed-Fri closed, Sat 10:00-10:30, Sun closed',
        'weekday hours combine all doctors and include final slot duration'
    );
    assert_eq(clinic_opening_hours([]), 'Mon-Sun closed', 'empty schedule');
});

test('footer reflects a changed doctor schedule in the test database', static function (): void {
    $nextMonday = new DateTimeImmutable('next monday');
    $date = $nextMonday->format('Y-m-d');
    $id = 1;
    $times = [$date . ' 06:00:00', $date . ' 06:30:00'];
    try {
        foreach ($times as $time) {
            q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`) VALUES (:doctor, :time)',
                ['doctor' => $id, 'time' => $time]);
        }
        // The footer caches its query per request. Render in a fresh request so
        // other tests' footer reads cannot hide this database change.
        $footerPath = dirname(__DIR__) . '/clinic-base/partials/footer.php';
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export($footerPath, true) . ';') . ' 2>&1', $output, $status);
        assert_eq($status, 0, 'footer render exits successfully');
        $footer = implode("\n", $output);
        assert_contains($footer, 'NTU South Spine S2-B3A-06', 'address');
        assert_contains($footer, 'href="tel:999"', 'telephone link');
        assert_contains($footer, 'Mon 06:00-', 'changed hours are rendered');
    } finally {
        foreach ($times as $time) {
            q('DELETE FROM `slots` WHERE `DoctorID` = :doctor AND `SlotDateTime` = :time',
                ['doctor' => $id, 'time' => $time]);
        }
    }
});

test('footer day summaries come from one row per doctor and day', static function (): void {
    $date = (new DateTimeImmutable('next tuesday'))->format('Y-m-d');
    $times = ['05:00:00', '05:20:00', '05:40:00', '07:00:00'];
    try {
        foreach ($times as $time) {
            q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`) VALUES (1, :time)', ['time' => $date . ' ' . $time]);
        }
        // clinic_schedule_days() caches per request; read it in a fresh one.
        $modelPath = dirname(__DIR__) . '/clinic-base/models/doctors.php';
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export($modelPath, true)
            . '; echo json_encode(clinic_schedule_days());') . ' 2>&1', $output, $status);
        assert_eq($status, 0, 'summary query runs in a fresh request');
        $rows = json_decode(implode('', $output), true);
        assert_true(is_array($rows), 'summary rows decode: ' . substr(implode('', $output), 0, 200));
        $day = array_values(array_filter($rows, static fn (array $row): bool =>
            (int) $row['DoctorID'] === 1 && $row['SlotDate'] === $date));
        assert_count($day, 1, 'one summary row per doctor and day');
        assert_eq(substr((string) $day[0]['FirstTime'], 0, 5), '05:00', 'first start');
        assert_eq((int) $day[0]['Step'], 20, 'smallest gap of at most 60 minutes is the slot length');
    } finally {
        foreach ($times as $time) {
            q('DELETE FROM `slots` WHERE `DoctorID` = 1 AND `SlotDateTime` = :time', ['time' => $date . ' ' . $time]);
        }
    }
});

test('footer still renders when the database is unavailable', static function (): void {
    $footerPath = dirname(__DIR__) . '/clinic-base/partials/footer.php';
    $command = escapeshellarg(PHP_BINARY) . ' -d log_errors=0 -r ' . escapeshellarg(
        "putenv('CLINIC_DB_NAME=ie4727db_missing_issue197'); require " . var_export($footerPath, true) . ';') . ' 2>&1';
    exec($command, $output, $status);
    $footer = implode("
", $output);
    assert_eq($status, 0, 'footer render exits successfully without a database');
    assert_contains($footer, 'See Find a Doctor for available times', 'static fallback line');
    assert_contains($footer, 'NTU South Spine S2-B3A-06', 'the rest of the footer still renders');
});

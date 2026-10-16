<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';

test('footer hours use weekday minima and daily finishing times', static function (): void {
    $rows = [
        ['DoctorID' => 1, 'SlotDate' => '2026-10-19', 'SlotTime' => '09:00:00'],
        ['DoctorID' => 1, 'SlotDate' => '2026-10-19', 'SlotTime' => '09:30:00'],
        ['DoctorID' => 2, 'SlotDate' => '2026-10-19', 'SlotTime' => '08:00:00'],
        ['DoctorID' => 2, 'SlotDate' => '2026-10-19', 'SlotTime' => '08:15:00'],
        ['DoctorID' => 2, 'SlotDate' => '2026-10-19', 'SlotTime' => '18:00:00'],
        ['DoctorID' => 1, 'SlotDate' => '2026-10-20', 'SlotTime' => '09:00:00'],
        ['DoctorID' => 1, 'SlotDate' => '2026-10-20', 'SlotTime' => '09:30:00'],
        ['DoctorID' => 1, 'SlotDate' => '2026-10-24', 'SlotTime' => '10:00:00'],
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

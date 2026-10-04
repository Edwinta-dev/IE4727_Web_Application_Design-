<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/stats.php';

// Browser acceptance: node tests/ui_admin_console_153.mjs
test('issue153 displayed week matches weekly aggregation on Sunday and year boundaries', static function (): void {
    db()->beginTransaction();
    try {
        q('INSERT INTO doctor (FullName, User, HashPass, Email) VALUES (:name,:user,:hash,:email)',
            ['name'=>'Week range test','user'=>'issue153-week','hash'=>'fixture','email'=>'issue153@example.local']);
        $doctor = (int) db()->lastInsertId();
        foreach (['2026-10-04'=>['2026-09-28','2026-10-04'], '2026-10-05'=>['2026-10-05','2026-10-11'],
                  '2027-01-01'=>['2026-12-28','2027-01-03']] as $today => [$start,$end]) {
            q('SET timestamp = CAST(:clock AS UNSIGNED)', ['clock'=>(new DateTimeImmutable($today . ' 12:00:00', new DateTimeZone('Asia/Singapore')))->getTimestamp()]);
            assert_eq(appointment_week_range(), ['start'=>$start,'end'=>$end], 'Monday to Sunday range');
            foreach ([(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d'), $start, $end,
                      (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d')] as $date) {
                q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (:doctor,1,:time,:status)',
                    ['doctor'=>$doctor,'time'=>$date.' 09:00:00','status'=>'Future']);
            }
            $rows = array_values(array_filter(appointments_per_doctor_this_week(), static fn(array $row): bool => (int)$row['DoctorID'] === $doctor));
            assert_eq((int)$rows[0]['Bookings'], 2, 'both endpoints included and adjacent weeks excluded');
            q('DELETE FROM appointment WHERE DoctorID=:doctor', ['doctor'=>$doctor]);
        }
    } finally {
        q('SET timestamp = 0');
        db()->rollBack();
    }
});

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';

// Browser acceptance: node tests/ui_doctor_dayboard_152.mjs
test('issue152 next booked day is ordered, doctor scoped and excludes inactive visits', static function (): void {
    db()->beginTransaction();
    try {
        q('INSERT INTO doctor (FullName, User, HashPass, Email) VALUES (:name,:user,:hash,:email)',
            ['name' => 'Day lookup test', 'user' => 'issue152-model', 'hash' => password_hash('Password123', PASSWORD_DEFAULT), 'email' => 'issue152-model@example.local']);
        $doctorId = (int) db()->lastInsertId();
        assert_eq(next_appointment_day_for_doctor($doctorId, '2040-01-01'), null, 'empty schedule');
        foreach ([['2040-01-05', 'Rescheduled'], ['2040-01-03', 'Future'], ['2040-01-02', 'Cancelled'], ['2040-01-01', 'Completed'], ['2040-01-01', 'No show']] as [$date, $status]) {
            q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (:doctor,1,:time,:status)',
                ['doctor' => $doctorId, 'time' => $date . ' 09:00:00', 'status' => $status]);
        }
        q('INSERT INTO appointment (DoctorID,PatientID,appointmentDateTime,Status) VALUES (1,1,:time,:status)',
            ['time' => '2040-01-01 09:00:00', 'status' => 'Future']);
        assert_eq(next_appointment_day_for_doctor($doctorId, '2040-01-01'), '2040-01-03', 'first active day for this doctor');
        assert_eq(next_appointment_day_for_doctor($doctorId, '2040-01-03'), '2040-01-03', 'inclusive date boundary');
        assert_eq(next_appointment_day_for_doctor($doctorId, '2040-01-04'), '2040-01-05', 'rescheduled visit included');
        assert_eq(next_appointment_day_for_doctor($doctorId, '2040-01-06'), null, 'no later booking');
    } finally {
        db()->rollBack();
    }
});

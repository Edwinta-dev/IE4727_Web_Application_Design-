<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/slots.php';

test('reschedule landing uses controlled late-day availability without mutations', static function (): void {
    $now = (new DateTimeImmutable('today', new DateTimeZone('Asia/Singapore')))->setTime(18, 0);
    $today = $now->format('Y-m-d');
    $day = static fn (int $offset): string => $now->modify('+' . $offset . ' days')->format('Y-m-d');
    db()->beginTransaction();
    try {
        q('INSERT INTO doctor (FullName, User, HashPass, Email) VALUES (?, ?, ?, ?)',
            ['Landing fixture', 'landing_fixture', 'unused', 'landing@example.test']);
        $doctorId = (int) db()->lastInsertId();
        foreach ([[0, '09:00', 'Available'], [0, '18:00', 'Available'],
            [1, '10:00', 'Booked'], [1, '11:00', 'Blocked'],
            [3, '09:00', 'Available'], [4, '11:00', 'Available'],
            [6, '12:00', 'Available'], [7, '10:00', 'Available']] as [$offset, $time, $status]) {
            q('INSERT INTO slots (DoctorID, SlotDateTime, Status) VALUES (?, ?, ?)',
                [$doctorId, $day($offset) . ' ' . $time . ':00', $status]);
        }
        $before = q_all('SELECT slotID, Status FROM slots WHERE DoctorID = ? ORDER BY slotID', [$doctorId]);
        $availability = booking_day_availability($doctorId, $now, BROWSE_DAYS, '00:00', '23:59');
        assert_count($availability, 7, 'seven permitted days including empty days');
        assert_eq($availability[$today]['available'], 0, 'elapsed and equal-now slots are not selectable');
        assert_eq(reschedule_landing_date($today, $availability, $today), $day(3), 'late-day landing skips elapsed, booked, blocked and empty days');
        assert_eq(reschedule_landing_date($day(4), $availability, $today), $day(4), 'prefer selectable original day');
        assert_eq(reschedule_landing_date($day(10), $availability, $today), $day(3), 'original outside window falls back to earliest selectable day');
        assert_eq(booking_day_empty_message($availability[$today]), 'All appointment times on this day have passed.');
        assert_eq(booking_day_empty_message($availability[$day(1)]), 'The remaining times on this day are booked or unavailable.');
        assert_eq(booking_day_empty_message($availability[$day(2)]), 'No appointment times are scheduled for this day.');
        assert_eq(booking_day_empty_message(['total' => 2, 'matching' => 1, 'future' => 0, 'available' => 0]),
            'All appointment times matching these time filters have passed.', 'filtered elapsed copy does not claim every time has passed');
        $filtered = booking_day_availability($doctorId, $now, BROWSE_DAYS, '12:00', '12:00');
        assert_eq(reschedule_landing_date($day(4), $filtered, $today), $day(6), 'time filter and final window boundary respected');
        assert_eq(booking_day_empty_message($filtered[$day(4)]), 'No appointment times match these time filters.');
        $none = booking_day_availability($doctorId, $now, BROWSE_DAYS, '15:00', '16:00');
        assert_eq(reschedule_landing_date($day(7), $none, $today), $today, 'no free permitted day keeps navigable fallback');
        assert_eq(q_all('SELECT slotID, Status FROM slots WHERE DoctorID = ? ORDER BY slotID', [$doctorId]), $before, 'availability lookup never claims or releases slots');
    } finally {
        db()->rollBack();
    }
});

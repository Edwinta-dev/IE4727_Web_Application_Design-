<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
require_once dirname(__DIR__) . '/clinic-base/models/slots.php';

$today = new DateTimeImmutable('today');
$date = static fn (int $offset): string => $today->modify(($offset < 0 ? '' : '+') . $offset . ' days')->format('Y-m-d');
assert_eq(booking_date_error($date(-1), $today, BROWSE_DAYS), 'Past dates cannot be booked.', 'past date reason');
assert_eq(booking_date_error('2026-99-99', $today, BROWSE_DAYS), 'Enter a valid date in YYYY-MM-DD format.', 'malformed date reason');
assert_eq(booking_date_error("2026-10-01\0", $today, BROWSE_DAYS), 'Enter a valid date in YYYY-MM-DD format.', 'null byte date reason');
assert_eq(booking_date_error(['invalid'], $today, BROWSE_DAYS), 'Enter a valid date.', 'array date reason');
assert_eq(booking_date_error($date(BROWSE_DAYS), $today, BROWSE_DAYS), 'Choose a date within the next ' . BROWSE_DAYS . ' days.', 'beyond window reason');
assert_eq(booking_date_error($date(0), $today, BROWSE_DAYS), null, 'today boundary');
assert_eq(booking_date_error($date(BROWSE_DAYS - 1), $today, BROWSE_DAYS), null, 'last day boundary');

$doctor = q_one('SELECT `DoctorID` FROM `doctor` ORDER BY `DoctorID` LIMIT 1');
assert_true($doctor !== null, 'seed doctor exists');
$doctorId = (int) $doctor['DoctorID'];
$farDate = $date(BROWSE_DAYS + 40);
q('INSERT INTO `slots` (`DoctorID`, `SlotDateTime`, `Status`) VALUES (:doctor, :slot, \'Available\')',
    ['doctor' => $doctorId, 'slot' => $farDate . ' 09:00:00']);
$farId = (int) db()->lastInsertId();
foreach (next_available($doctorId, 100) as $slot) {
    assert_true((int) $slot['slotID'] !== $farId, 'doctor profile must not link beyond booking window');
    assert_true(substr((string) $slot['SlotDateTime'], 0, 10) <= $date(BROWSE_DAYS - 1), 'profile date must be selectable');
}

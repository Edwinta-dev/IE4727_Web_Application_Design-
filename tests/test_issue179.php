<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/models/doctors.php';

$fixture = require __DIR__ . '/fixtures/booking_179.php';
$date = $fixture['date'];
$dentalIds = array_map(static fn (array $doctor): int => (int) $doctor['DoctorID'], all_doctors('Dental'));
assert_true(array_diff($fixture['doctors'], $dentalIds) === [],
    'a doctor stored under the legacy "Dentistry" value is listed under Dental');
$rows = slots_for_doctors_day($dentalIds, $date, '06:00', '08:00');
$selected = array_values(array_filter($rows, static fn (array $row): bool =>
    in_array((int) $row['DoctorID'], $fixture['doctors'], true)));
assert_true(count($selected) === 5, 'merged query includes both schedules and the blocked period');
assert_true(array_column($selected, 'SlotTime') ===
    ['06:30:00', '07:00:00', '07:00:00', '07:30:00', '07:45:00'],
    'non-overlapping and shared starts remain ordered');
assert_true(count(array_filter($selected, static fn (array $row): bool => $row['Status'] === 'Blocked')) === 1,
    'blocked period is retained as unavailable');
$availability = booking_doctors_day_availability($dentalIds, new DateTimeImmutable(), BROWSE_DAYS, '06:00', '08:00');
assert_true($availability[$date]['available'] === 4, 'only future available rows count, legacy-specialty doctor included');
assert_true(slots_for_doctors_day([], $date, '06:00', '08:00') === [],
    'zero doctors yields an empty result');
$empty = booking_doctors_day_availability([], new DateTimeImmutable(), BROWSE_DAYS, '06:00', '08:00');
assert_true(array_sum(array_column($empty, 'total')) === 0, 'zero doctors yields an empty availability window');
$single = slots_for_day($fixture['doctors'][0], $date, '06:00', '08:00');
assert_true(array_column($single, 'SlotTime') === ['06:30:00', '07:00:00', '07:30:00'],
    'the single-doctor query is the one-id case of the shared query');
echo "OK: #179/#196 merged slots, legacy specialty alias, blocked period, empty specialty\n";

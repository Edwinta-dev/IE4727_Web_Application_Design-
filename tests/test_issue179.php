<?php

declare(strict_types=1);

$fixture = require __DIR__ . '/fixtures/booking_179.php';
$date = $fixture['date'];
$rows = specialty_slots_for_day('Dental', $date, '06:00', '08:00');
$selected = array_values(array_filter($rows, static fn (array $row): bool =>
    in_array((int) $row['DoctorID'], $fixture['doctors'], true)));
assert_true(count($selected) === 5, 'merged query includes both schedules and the blocked period');
assert_true(array_column($selected, 'SlotTime') ===
    ['06:30:00', '07:00:00', '07:00:00', '07:30:00', '07:45:00'],
    'non-overlapping and shared starts remain ordered');
assert_true(count(array_filter($selected, static fn (array $row): bool => $row['Status'] === 'Blocked')) === 1,
    'blocked period is retained as unavailable');
$availability = booking_specialty_day_availability('Dental', new DateTimeImmutable(), BROWSE_DAYS, '06:00', '08:00');
assert_true($availability[$date]['available'] === 4, 'only future available rows count');
assert_true(specialty_slots_for_day('Nonexistent specialty', $date, '06:00', '08:00') === [],
    'zero doctors yields an empty result');
echo "OK: #179 merged non-overlapping and shared slots, blocked period, empty specialty\n";

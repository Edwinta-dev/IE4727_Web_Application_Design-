<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/clinic-base/models/stats.php';

$weekly = appointments_per_doctor_this_week();
assert_true(count($weekly) >= 5, 'weekly doctor aggregation includes seeded doctors');
assert_true(array_key_exists('Bookings', $weekly[0]), 'weekly aggregation has a booking count');

$overall = overall_no_show_rate();
assert_true($overall['total'] > 0, 'overall no-show aggregation has appointments');
assert_true($overall['no_show'] > 0 && $overall['rate'] > 0 && $overall['rate'] <= 100, 'overall no-show rate is sane');

$perDoctor = no_show_rate_per_doctor();
assert_true(count($perDoctor) >= 5, 'per-doctor no-show aggregation includes seeded doctors');
assert_true(array_key_exists('Rate', $perDoctor[0]), 'per-doctor aggregation has a rate');

$hours = peak_booking_hours();
assert_true($hours !== [], 'peak booking hours aggregation is populated');
assert_true(array_key_exists('BookingHour', $hours[0]), 'peak hours include grouped hour');

$seedLead = mean_booking_lead_time();
assert_true($seedLead['valid'] > 0 && $seedLead['invalid'] === 0, 'seed booking times are chronological: ' . var_export($seedLead, true));
assert_true($seedLead['mean_days'] !== null && $seedLead['mean_days'] >= 0, 'seed mean uses valid booking times only');

// Isolate controlled clinic-local DATETIME samples from the rolling seed by date.
$range = ['date_from' => '2040-01-01', 'date_to' => '2040-01-31'];
assert_eq(mean_booking_lead_time($range), ['mean_days' => null, 'valid' => 0, 'invalid' => 0], 'empty selection is unavailable');
$samples = [
    ['2040-01-10 09:00:00', '2040-01-10 09:00:00', 'Future'],
    ['2040-01-11 09:00:00', '2040-01-10 09:00:00', 'Completed'],
    ['2040-01-13 09:00:00', '2040-01-10 09:00:00', 'No show'],
    ['2040-01-14 09:00:00', '2040-01-15 09:00:00', 'Cancelled'],
    ['2040-01-16 09:00:00', null, 'Rescheduled'],
];
foreach ($samples as [$start, $created, $status]) {
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`, `CreatedAt`, `Status`)
       VALUES (1, 1, :start, :created, :status)', ['start' => $start, 'created' => $created, 'status' => $status]);
}
$measured = mean_booking_lead_time($range);
assert_true(abs($measured['mean_days'] - 4 / 3) < 0.000001, '0, 1 and 3 elapsed days average to 4/3');
assert_eq([$measured['valid'], $measured['invalid']], [3, 2], 'invalid timestamps are counted, not averaged');
assert_eq(mean_booking_lead_time($range + ['status' => 'Completed']), ['mean_days' => 1.0, 'valid' => 1, 'invalid' => 0], 'status filter selects completed booking');
assert_eq(mean_booking_lead_time($range + ['status' => 'Cancelled']), ['mean_days' => null, 'valid' => 0, 'invalid' => 1], 'all-invalid selection is unavailable');
assert_eq(mean_booking_lead_time($range + ['doctor' => '2']), ['mean_days' => null, 'valid' => 0, 'invalid' => 0], 'doctor filter is applied');

// Crossing midnight measures elapsed hours, not calendar date boundaries.
q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`, `CreatedAt`, `Status`)
   VALUES (1, 1, :start, :created, \'Future\')', ['start' => '2040-02-01 00:30:00', 'created' => '2040-01-31 23:30:00']);
$midnight = mean_booking_lead_time(['date_from' => '2040-02-01', 'date_to' => '2040-02-01']);
assert_true(abs($midnight['mean_days'] - 1 / 24) < 0.000001, 'one hour across Singapore midnight is 1/24 day');
q("SET time_zone = '+00:00'");
assert_true(abs(mean_booking_lead_time(['date_from' => '2040-02-01', 'date_to' => '2040-02-01'])['mean_days'] - 1 / 24) < 0.000001, 'DATETIME lead is independent of database session timezone');
q("SET time_zone = '+08:00'");
$source = file_get_contents($root . '/clinic-base/models/stats.php');
assert_true($source !== false && strpos($source, 'GROUP BY') !== false, 'stats model uses GROUP BY');

echo "PASS: stats checks\n";

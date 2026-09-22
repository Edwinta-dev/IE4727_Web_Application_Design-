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

assert_true(mean_booking_lead_time() >= 0, 'mean booking lead time is non-negative');
$source = file_get_contents($root . '/clinic-base/models/stats.php');
assert_true($source !== false && strpos($source, 'GROUP BY') !== false, 'stats model uses GROUP BY');

echo "PASS: stats checks\n";

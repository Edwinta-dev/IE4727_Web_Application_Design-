<?php

declare(strict_types=1);

// Usability fixes: issues 223 (booking opens on a day with slots), 225 (hero
// buttons go to different pages) and 229 (all seven "Days to skip" visible).
require_once dirname(__DIR__) . '/clinic-base/lib/slots.php';

$counts = static fn (int $available): array => ['total' => 14, 'matching' => 14, 'future' => $available, 'available' => $available];

// 223: today with times stays today; an empty today moves to the first day that has times.
$week = ['2026-11-04' => $counts(3), '2026-11-05' => $counts(5)];
assert_eq(booking_default_date('2026-11-04', $week), '2026-11-04', 'today has times, so it stays');
$week['2026-11-04'] = $counts(0);
assert_eq(booking_default_date('2026-11-04', $week), '2026-11-05', 'empty today moves to the next day with times');
$sparse = ['2026-11-04' => $counts(0), '2026-11-05' => $counts(0), '2026-11-06' => $counts(2), '2026-11-07' => $counts(9)];
assert_eq(booking_default_date('2026-11-04', $sparse), '2026-11-06', 'first day with times, not the busiest');
$none = ['2026-11-04' => $counts(0), '2026-11-05' => $counts(0)];
assert_eq(booking_default_date('2026-11-04', $none), '2026-11-04', 'no day has times, so it stays on today');

$root = dirname(__DIR__) . '/clinic-base/';
$book = (string) file_get_contents($root . 'screens/book.php');
assert_true(preg_match('/\$reschedule === null && !\$dateRequested\)\s*\{\s*\$selectedDate = booking_default_date\(/', $book) === 1,
    'book.php applies the default only when no date was requested and it is not a reschedule');

// 225: hero buttons have different destinations.
$index = (string) file_get_contents($root . 'screens/index.php');
assert_true(preg_match('~href="<\?= e\(url\(\'/screens/doctors\.php\'\)\) \?>">Find a doctor</a>~', $index) === 1, 'Find a doctor goes to the doctor list');
assert_true(preg_match('~href="<\?= e\(url\(\'/screens/book\.php\'\)\) \?>">Book an appointment</a>~', $index) === 1, 'Book an appointment goes to the booking page');

// 229: the day checkboxes wrap instead of scrolling sideways.
$css = (string) file_get_contents($root . 'assets/style.css');
assert_true(preg_match('/days-to-skip fieldset\s*\{[^}]*nowrap/', $css) === 0, 'Days to skip no longer forced onto one row');
assert_true(preg_match('/days-to-skip\s*\{[^}]*overflow:\s*hidden/', $css) === 0, 'Days to skip is not clipped');
$schedule = (string) file_get_contents($root . 'screens/doctor/schedule.php');
assert_true(strpos($schedule, 'Scroll for Sunday') === false, 'scroll hint removed');
assert_true(substr_count($schedule, 'name="skip_days[]"') === 1 && strpos($schedule, "0 => 'Sunday'") !== false, 'all seven days are still offered');

echo "OK: booking default day, hero buttons and Days to skip\n";

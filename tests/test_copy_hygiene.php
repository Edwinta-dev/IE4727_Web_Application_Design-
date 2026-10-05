<?php

declare(strict_types=1);

$base = dirname(__DIR__) . '/clinic-base/';
$booking = (string) file_get_contents($base . 'book.php');
assert_eq(substr_count($booking, 'name="reason"'), 1, 'One shared reason field');
assert_eq(substr_count($booking, 'class="booking-confirmation"'), 1, 'One confirmation panel');
assert_contains($booking, 'type="radio" name="slot_id"', 'Native exclusive time selection');
assert_true(!str_contains($booking, 'Select time'), 'No repeated slot instruction');
assert_true(!str_contains($booking, 'Confirm this appointment with'), 'No repeated confirmation sentence');

foreach (['doctor/schedule.php', 'doctor/home.php', 'doctor/visit.php', 'patient/home.php', 'admin/console.php', 'admin/outbox.php'] as $path) {
    $page = (string) file_get_contents($base . $path);
    assert_true(!str_contains($page, 'page-intro-mark'), $path . ': headings omit duplicate logos');
    assert_contains($page, "'partials' . DIRECTORY_SEPARATOR . 'header.php'", $path . ': shared header remains');
}
$schedule = (string) file_get_contents($base . 'doctor/schedule.php');
assert_contains($schedule, "\$totalSlots === 0 ? 'No slots' : \$totalSlots . ' slots'", 'Calendar totals label empty days without numeric zero counts');
assert_contains($schedule, 'class="schedule-day-counts"', 'Selected-day breakdown remains available');
$outbox = (string) file_get_contents($base . 'admin/outbox.php');
assert_contains($outbox, 'aria-labelledby="outbox-list-heading"', 'Table uses existing message heading');
assert_true(!str_contains($outbox, 'Scroll sideways'), 'No compensating layout instruction');
assert_true(!str_contains($outbox, '<caption>'), 'No duplicate message heading');
$patient = (string) file_get_contents($base . 'patient/home.php');
assert_true(!str_contains($patient, 'Not available'), 'Absent notes have a compact placeholder');
echo "OK: #146 shared confirmation and copy hygiene\n";

<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/doctor/schedule.php');
$model = file_get_contents(dirname(__DIR__) . '/clinic-base/models/slots.php');
$booking = file_get_contents(dirname(__DIR__) . '/clinic-base/models/booking.php');
if ($page === false || $model === false || $booking === false) {
    throw new RuntimeException('schedule files could not be read');
}

foreach ([
    'require_doctor();', 'regenerate_schedule(', 'slot_counts_for_range(', 'slots_for_day(',
    'name="start_date"', 'name="days"', 'name="start_time"', 'name="end_time"',
    'name="slot_length"', 'name="skip_days[]"', 'class="schedule-month-grid"', 'name="date"',
    'class="slot <?=', "'free'", "'taken'", "'blocked'", "'past'", 'csrf_field()',
    'confirm(', 'name="confirm_booking"', "(\$_POST['confirm_booking'] ?? '') !== '1'", 'No new slots were created',
] as $needle) {
    assert_contains($page, $needle);
}
foreach (['INSERT IGNORE INTO `slots`', 'slot_counts_for_range('] as $needle) {
    assert_contains($model, $needle);
}
foreach (['function block_slot(', "'Cancelled'", 'notify_cancelled_appointment('] as $needle) {
    assert_contains($booking, $needle);
}
assert_true(strpos($page, 'SELECT ') === false, 'schedule page must not contain SQL');
assert_true(strpos($page, 'fetch(') === false, 'schedule page must not use AJAX');

echo "PASS: schedule page markup checks\n";

// Issue #148: calendar first, one shared date selection, and consistent state copy.
assert_true(strpos($page, 'class="month-grid"') < strpos($page, 'class="day-view"'), 'calendar precedes day slots');
assert_true(strpos($page, 'class="day-view"') < strpos($page, 'class="schedule-generator"'), 'generator follows the schedule');
assert_true(substr_count($page, 'type="date"') === 1, 'schedule uses one date picker');
assert_contains($page, 'id="start-date" name="start_date" type="hidden"');
assert_contains($page, "['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']");
assert_contains($page, "\$gridStart->format('N')");
assert_contains($page, 'schedule-calendar-gap');
assert_contains($page, 'aria-current="date"');
assert_contains($page, "' no-slots'");
assert_true(strpos($page, 'Unavailable') === false, 'blocked state uses database terminology');

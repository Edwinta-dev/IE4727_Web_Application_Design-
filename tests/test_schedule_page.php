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
    'name="slot_length"', 'name="skip_days[]"', 'Next 30 days', 'class="schedule-month-grid"',
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

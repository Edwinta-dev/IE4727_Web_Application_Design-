<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/book.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/book.php');
if ($page === false || $action === false) {
    throw new RuntimeException('booking page or action could not be read');
}

foreach ([
    'method="get"',
    'name="doctor"',
    'name="date"',
    'name="from"',
    'name="to"',
    'class="day-tab',
    'class="booking-results"',
    'class="booking-doctor"',
    'class="booking-slots"',
    'class="slot <?= e($stateClass) ?>"',
    '<details class="slot-booking"><summary>Select time</summary>',
    "? 'Unavailable' : 'Past'",
    "'free'",
    "'taken'",
    "'blocked'",
    "'past'",
    'name="slot_id"',
    'name="reason"',
    'csrf_field()',
    'slots_for_day($doctorId, $selectedDate, $fromTime, $toTime)',
] as $needle) {
    assert_contains($page, $needle);
}

foreach (['csrf_check()', 'book_appointment(', 'redirect('] as $needle) {
    assert_contains($action, $needle);
}

assert_true(strpos($page, 'fetch(') === false, 'booking page must not use AJAX');
assert_true(strpos($page, 'SELECT ') === false, 'booking page must not contain SQL');

echo "PASS: booking page markup checks\n";

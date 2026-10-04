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
    'class="slot-choice"',
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
foreach (['$rescheduleRequested', 'name="reschedule" value="<?= e((string) $rescheduleId)',
    '[\'reschedule\' => $rescheduleId]', 'Current appointment:',
    'Your current booking stays in place until the replacement is confirmed.',
    'Back to appointments', 'Confirm replacement'] as $needle) {
    assert_contains($page, $needle, 'rescheduling context and filter routing');
}
$tokens = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/tokens.css');
$css = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
assert_true($tokens !== false && $css !== false, 'button styles should be readable');
assert_contains($tokens, '--c-button-foreground: #ffffff;');
assert_contains($css, '.appointment-action:focus');
assert_contains($css, 'color: var(--c-button-foreground);');

echo "PASS: booking page markup checks\n";

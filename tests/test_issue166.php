<?php

declare(strict_types=1);

$schedule = file_get_contents(dirname(__DIR__) . '/clinic-base/doctor/schedule.php');
$booking = file_get_contents(dirname(__DIR__) . '/clinic-base/book.php');
if ($schedule === false || $booking === false) {
    throw new RuntimeException('schedule pages could not be read');
}

assert_true(strpos($schedule, 'class="schedule-generator"') < strpos($schedule, 'class="day-view"'), 'generation form leads the schedule page');
assert_true(strpos($schedule, 'class="day-view"') < strpos($schedule, 'class="month-grid"'), 'selected-day availability leads the calendar');
assert_contains($schedule, '$currentSlots');
assert_contains($schedule, '$elapsedSlots');
assert_contains($schedule, '<details class="elapsed-slots">');
assert_contains($schedule, 'These slots have started; their availability can no longer be changed.');
assert_true(substr_count($schedule, 'This slot has started; availability can no longer be changed.') === 0, 'elapsed-slot explanation is not repeated on each row');

assert_contains($booking, '$bookableSlots');
assert_contains($booking, '$elapsedSlots');
assert_contains($booking, '$slots = array_merge($bookableSlots, $otherFutureSlots);');
assert_contains($booking, '<details class="elapsed-slots">');
assert_contains($booking, 'These appointment times have passed and cannot be booked.');
assert_true(substr_count($booking, 'These appointment times have passed and cannot be booked.') === 1, 'booking explains elapsed times once');

echo "OK: issue #166 prioritizes generation and bookable slots and discloses elapsed slots once\n";

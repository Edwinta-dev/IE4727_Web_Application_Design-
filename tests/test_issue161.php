<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/book.php');
if ($page === false) {
    throw new RuntimeException('booking page could not be read');
}
// Windows checkouts (core.autocrlf) store CRLF; the multi-line needle below uses LF.
$page = str_replace("\r\n", "\n", $page);

assert_contains($page, '$rescheduleReason = $rescheduleRemarks[\'reason\'];', 'existing visit reason is decoded for reschedule');
assert_contains($page, 'if ($reschedule !== null): ?>' . "\n" . '                <p class="booking-reason">Reason for visit: <?= e($rescheduleReason', 'reschedule shows escaped read-only reason');
assert_contains($page, 'else: ?>' . "\n" . '                <label for="reason">Reason for visit <span class="required-mark" aria-hidden="true">*</span></label>' . "\n" . '                <input id="reason" name="reason" type="text" maxlength="255" required aria-required="true">', 'new booking retains marked and required reason input');

$confirmationStart = strpos($page, '<section class="booking-confirmation"');
$confirmationEnd = strpos($page, '</section>', $confirmationStart === false ? 0 : $confirmationStart);
assert_true($confirmationStart !== false && $confirmationEnd !== false, 'booking confirmation section exists');
$confirmation = substr($page, $confirmationStart, $confirmationEnd - $confirmationStart);
assert_true(strpos($confirmation, 'name="reason"') !== false, 'booking reason field remains available');
assert_contains($confirmation, 'if ($reschedule !== null): ?>', 'confirmation branches between booking and reschedule');
assert_true(strpos($confirmation, 'booking-reason') !== false, 'reschedule renders its existing reason');

echo "PASS: issue 161 reschedule reason checks\n";

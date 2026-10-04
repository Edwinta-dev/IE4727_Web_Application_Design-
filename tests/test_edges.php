<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        throw new RuntimeException('could not read ' . $relative);
    }

    return $contents;
};

$helpers = $read('clinic-base/lib/helpers.php');
$csrf = $read('clinic-base/lib/csrf.php');
$config = $read('clinic-base/lib/config.php');
$book = $read('clinic-base/book.php');
$bookAction = $read('clinic-base/actions/book.php');
$doctor = $read('clinic-base/doctor.php');
$visit = $read('clinic-base/doctor/visit.php');
$console = $read('clinic-base/admin/console.php');

foreach (['render_status_page', 'render_not_found', 'class="status-page"', 'clinic-logo.svg'] as $needle) {
    assert_contains($helpers, $needle, 'shared error page is missing ' . $needle);
}
foreach (['http_response_code(419)', 'render_status_page(419'] as $needle) {
    assert_contains($csrf, $needle, 'CSRF failure page is missing ' . $needle);
}
foreach (["ini_set('display_errors', '0')", "ini_set('log_errors', '1')"] as $needle) {
    assert_contains($config, $needle, 'production error setting is missing');
}
foreach (['booking_date_error($dateInput', 'role="alert"', 'Showing', 'booking_day_empty_message(', '<?= e($dayEmptyMessage) ?>'] as $needle) {
    assert_contains($book, $needle, 'rejected/empty schedule handling is missing ' . $needle);
}
assert_contains($read('clinic-base/lib/slots.php'), 'No appointment times are scheduled for this day.', 'ungenerated days need a specific reason');
assert_contains($helpers, 'Past dates cannot be booked.', 'past booking dates need a specific reason');
assert_contains($bookAction, "strtotime((string) \$slot['SlotDateTime']) <= time()", 'booking action must refuse past slots');
assert_contains($doctor, 'render_not_found(', 'doctor profile must handle invalid ids');
assert_contains($visit, 'render_not_found(', 'visit page must handle invalid appointments');
foreach (['No patients match these filters.', 'No doctors match these filters.', 'No appointments match these filters.'] as $needle) {
    assert_contains($console, $needle, 'admin empty state is missing ' . $needle);
}

echo "PASS: edge-case markup and failure paths\n";

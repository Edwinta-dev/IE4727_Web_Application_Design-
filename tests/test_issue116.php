<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';

assert_eq(date_default_timezone_get(), 'Asia/Singapore', 'clinic display timezone');

$samples = [
    '2041-01-02 00:00:00' => '02 Jan 2041 at 12:00 AM',
    '2041-01-02 09:30:00' => '02 Jan 2041 at 9:30 AM',
    '2041-01-02 18:45:00' => '02 Jan 2041 at 6:45 PM',
    '2041-01-02 23:59:00' => '02 Jan 2041 at 11:59 PM',
];
foreach ($samples as $localDateTime => $display) {
    q('INSERT INTO `appointment` (`DoctorID`, `PatientID`, `appointmentDateTime`, `Status`)
       VALUES (1, 1, :time, \'Future\')', ['time' => $localDateTime]);
    assert_eq(fmt_date($localDateTime) . ' at ' . fmt_time($localDateTime), $display, 'local appointment display ' . $localDateTime);
}

$rows = admin_search(['date_from' => '2041-01-02', 'date_to' => '2041-01-02']);
assert_eq(array_column($rows, 'appointmentDateTime'), array_reverse(array_keys($samples)), 'admin model retains fixture wall times without a date shift');

$page = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/admin/console.php');
$styles = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
assert_contains($page, '<time datetime="<?= e(str_replace(', 'appointment cells expose a machine-readable local time');
assert_contains($page, 'e(fmt_date((string) $appointment[\'appointmentDateTime\']))', 'appointment date uses shared formatter');
assert_contains($page, 'e(fmt_time((string) $appointment[\'appointmentDateTime\']))', 'appointment time uses shared formatter');
assert_true(!str_contains($page, 'e($appointment[\'appointmentDateTime\'])'), 'raw appointment timestamps are not printed');
assert_eq(substr_count($page, '<button class="destructive-action" type="submit">Delete account</button>'), 2, 'both account tables distinguish delete');
foreach (['onsubmit="return confirm(this.dataset.confirmation);"', 'method="post"', 'csrf_field()', 'csrf_check()', 'redirect(\'/admin/console.php?'] as $needle) {
    assert_contains($page, $needle, 'delete confirmation and POST safeguards');
}
assert_contains($styles, '.destructive-action:hover, .destructive-action:focus-visible', 'shared destructive hover and focus style');

echo "PASS: admin table times and destructive controls\n";

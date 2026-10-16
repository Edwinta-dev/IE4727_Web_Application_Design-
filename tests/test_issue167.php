<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$action = (string) file_get_contents($root . '/clinic-base/actions/book.php');
$page = (string) file_get_contents($root . '/clinic-base/book.php');
$dashboard = (string) file_get_contents($root . '/clinic-base/patient/home.php');
$helpers = (string) file_get_contents($root . '/clinic-base/lib/helpers.php');

assert_contains($action, 'flash_booking_confirmation(');
assert_contains($action, "fmt_date((string) \$slot['SlotDateTime'])");
assert_contains($action, "fmt_time((string) \$slot['SlotDateTime'])");
assert_contains($helpers, "e((string) (\$booking['doctor'] ?? ''))");
assert_contains($helpers, "e((string) (\$booking['date'] ?? ''))");
assert_contains($helpers, "e((string) (\$booking['time'] ?? ''))");
assert_contains($helpers, 'View my appointment');
assert_contains($helpers, "'/patient/home.php#appointment-'");
assert_contains($dashboard, 'id="appointment-<?= e((string) $appointment[\'appointmentID\']) ?>"');
assert_contains($page, '$bookingConfirmed = flash_render();');
assert_contains($page, 'if (!$bookingConfirmed && $slots !== [])');

echo "OK: issue #167 booking confirmation names the appointment, links to its dashboard row, and hides the booking form\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$home = file_get_contents($root . '/clinic-base/doctor/home.php');
$schedule = file_get_contents($root . '/clinic-base/doctor/schedule.php');
$visit = file_get_contents($root . '/clinic-base/doctor/visit.php');
$console = file_get_contents($root . '/clinic-base/admin/console.php');
$outbox = file_get_contents($root . '/clinic-base/admin/outbox.php');
$styles = file_get_contents($root . '/clinic-base/assets/css/style.css');

foreach (['doctor day board' => $home, 'doctor schedule' => $schedule, 'doctor visit' => $visit, 'admin console' => $console, 'admin outbox' => $outbox, 'staff styles' => $styles] as $label => $source) {
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $label . ' source');
    }
}

if (!str_contains($home, 'day-board-heading') || !str_contains($home, 'class="day-board-filter"')) {
    throw new RuntimeException('The doctor day filter must stay with its heading');
}
if (!str_contains($schedule, 'class="schedule-month-grid"') || !str_contains($styles, '.schedule-month-grid { display: grid')) {
    throw new RuntimeException('The doctor schedule dates must use the full-width grid');
}
if (!str_contains($visit, 'class="visit-layout"') || !str_contains($visit, 'class="visit-form-actions"')) {
    throw new RuntimeException('Visit context and notes must use the side-by-side layout and shared action row');
}
if (!str_contains($console, 'class="console-filters"') || !str_contains($console, 'class="status-label')) {
    throw new RuntimeException('Admin filters and appointment states must use the shared staff treatment');
}
if (!str_contains($outbox, 'class="status-label status-')) {
    throw new RuntimeException('Outbox delivery state must be visible as a text label');
}

echo "PASS: doctor and admin work surfaces retain the issue 88 layouts and states\n";

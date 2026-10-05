<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/patient/home.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/appointment.php');
if ($page === false || $action === false) {
    throw new RuntimeException('patient appointment page or action could not be read');
}

foreach ([
    'require_login();',
    'appointments_for_patient($patientId, \'upcoming\')',
    'appointments_for_patient($patientId, \'past\')',
    'appointments_for_patient($patientId, \'cancelled\')',
    'Cancelled appointments',
    'Specialty',
    'Reschedule',
    'appointment-action',
    'appointment-cancel',
    'name="cancel"',
    'csrf_field()',
    'confirm(',
    'empty-state',
    'Status\'] === \'Completed\'',
    'completed_appointment_for_patient',
] as $needle) {
    assert_contains($page, $needle);
}

foreach (['require_login();', 'csrf_check();', 'PatientID', 'cancel_appointment(', 'reschedule_appointment(', 'redirect('] as $needle) {
    assert_contains($action, $needle);
}

assert_true(strpos($page, 'SELECT ') === false, 'patient page must not contain SQL');
assert_true(strpos($action, 'fetch(') === false, 'appointment action must not use AJAX');

// Both data tables retain six semantic columns. Their empty row must span all six,
// while the populated table scrolls inside a labelled, keyboard-focusable region.
foreach (['upcoming', 'past'] as $section) {
    assert_true(preg_match('/<section class="appointments ' . $section . '-appointments">(.*?)<\/section>/s', $page, $match) === 1, $section . ' section exists');
    assert_eq(substr_count($match[1], '<th scope="col">'), 6, $section . ' table has six column headers');
    assert_contains($match[1], '<td colspan="6">', $section . ' empty row spans all headers');
    assert_contains($match[1], 'role="region" aria-labelledby="' . $section . '-heading"', $section . ' scroller has an accessible name');
    assert_contains($match[1], 'tabindex="0"', $section . ' populated scroller accepts keyboard focus');
}
$style = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
assert_true($style !== false, 'shared stylesheet exists');
assert_contains($style, '.appointment-table-scroll { max-width: 100%; overflow-x: auto; }', 'horizontal scroll stays inside the table');
assert_contains($style, '.appointment-table-scroll.is-empty table { width: 100%; table-layout: fixed; }', 'empty table fits its container');
assert_contains($style, '.appointment-table-scroll:not(.is-empty) :is(th, td) { overflow-wrap: normal; white-space: nowrap; }', 'populated columns stay readable while scrolling');
assert_contains($style, '.appointment-action:link, .appointment-action:visited', 'reschedule text remains legible on its button');
assert_contains($page, 'class="empty-state appointment-empty-state"', 'upcoming empty state has one container');
assert_contains($page, 'e(url(\'/book.php\'))', 'empty state links to the existing booking page');

$book = file_get_contents(dirname(__DIR__) . '/clinic-base/book.php');
assert_true($book !== false && strpos($book, '/actions/appointment.php') !== false, 'reschedule uses appointment action');

echo "PASS: patient appointment page markup checks\n";

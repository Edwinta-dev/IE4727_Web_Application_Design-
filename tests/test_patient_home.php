<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/patient/home.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/appointment.php');
if ($page === false || $action === false) {
    throw new RuntimeException('patient appointment page or action could not be read');
}

foreach ([
    'require_patient();',
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

// Issue #213: Printable consultation summary and @media print styling
foreach ([
    'class="button print-slip-button"',
    'onclick="window.print()"',
    'Print consultation summary',
    'class="medical-slip"',
    'assets/img/clinic-logo.svg',
    'Consultation summary',
    'Consultation date:',
    'Printed on:',
    'Patient Name:',
    'Patient ID:',
    'Attending Doctor:',
    'Medical Specialty:',
    'Diagnosis:',
    'Prescribed Medications:',
    'Treatment Plan:',
    'Follow-up instructions:',
    'Attending Physician Signature',
    'Clinic stamp',
] as $slipNeedle) {
    assert_contains($page, $slipNeedle, "patient page must contain {$slipNeedle}");
}
assert_true(strpos($page, "\$visit['Specialty'] ?? 'General Practice'") === false, 'consultation summary does not invent a default specialty');
assert_contains($page, "if (trim((string) (\$visit['Specialty'] ?? '')) !== '')", 'consultation summary omits an empty specialty');
assert_true(strpos($page, 'Consultation summary</h3>') !== false, 'consultation summary has its accurate title');
assert_true(strpos($page, 'Print consultation summary</button>') !== false, 'print button names the consultation summary');

$css = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
assert_true($css !== false, 'app stylesheet exists');
assert_contains($css, '@media print', 'print media query exists');
assert_contains($css, 'margin: 1.5cm;', 'print page margin is set to 1.5cm');
assert_contains($css, '.medical-slip', 'medical slip styles exist in stylesheet');
assert_contains($css, '.print-slip-button', 'print slip button styles exist in stylesheet');
assert_contains($css, 'body:has(.medical-slip) .upcoming-appointments', 'upcoming appointments hide only when a summary is rendered');
assert_contains($css, 'body:has(.medical-slip) .past-appointments', 'past appointments hide only when a summary is rendered');
assert_true(preg_match('/(?m)^\s*\.upcoming-appointments\s*,?\s*$/', $css) !== 1, 'upcoming appointments are not hidden by an unscoped print selector');
assert_true(preg_match('/(?m)^\s*\.past-appointments\s*,?\s*$/', $css) !== 1, 'past appointments are not hidden by an unscoped print selector');
assert_true(preg_match('/\.slip-title\s*\{\s*font-family:\s*var\(--font-display\);/', $css) === 1, 'slip title uses the display font token');
assert_true(preg_match('/\.slip-section-heading\s*\{\s*font-family:\s*var\(--font-display\);/', $css) === 1, 'slip section headings use the display font token');
assert_true(strpos($css, 'Iowan') === false, 'slip rules do not hard-code the display font stack');
foreach ([
    '.site-header',
    '.site-nav',
    '.site-footer',
    '.page-intro',
    '.upcoming-appointments',
    '.past-appointments',
    '.cancelled-appointments',
    '.print-slip-button',
    '.table-scroll-hint',
] as $hiddenSelector) {
    assert_contains($css, $hiddenSelector, "print styles hide {$hiddenSelector}");
}

echo "PASS: patient appointment page markup checks\n";

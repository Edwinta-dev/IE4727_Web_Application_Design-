<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/assert.php';

// Ensure test database is selected
if (getenv('CLINIC_DB_NAME') === false || getenv('CLINIC_DB_NAME') === '') {
    putenv('CLINIC_DB_NAME=ie4727db_test');
}

// =========================================================================
// 1. Feature 1: Doctor directory live client-side search (#209)
// =========================================================================

// A. Markup verification in clinic-base/doctors.php
$doctorsPage = file_get_contents(dirname(__DIR__) . '/clinic-base/doctors.php');
if ($doctorsPage === false) {
    throw new RuntimeException('clinic-base/doctors.php could not be read');
}

foreach ([
    'class="doctor-search-box"',
    '<label for="doctor-search">Search doctors</label>',
    '<input type="search" id="doctor-search" name="q" placeholder="Search by doctor name, specialty, or language..." autocomplete="off">',
    '<p id="doctor-search-empty" class="empty-state" hidden>No doctors match your search query. Try another keyword or clear the search.</p>',
    '<table class="doctor-directory">',
    'class="specialty-filter"',
    '<noscript>',
] as $needle) {
    assert_contains($doctorsPage, $needle, "doctors.php must contain {$needle}");
}

// Verify zero network transport in doctors page (no AJAX / fetch)
assert_true(strpos($doctorsPage, 'fetch(') === false, 'doctors page must not use fetch');
assert_true(strpos($doctorsPage, 'XMLHttpRequest') === false, 'doctors page must not use XMLHttpRequest');
assert_true(strpos($doctorsPage, '$.ajax') === false, 'doctors page must not use jQuery ajax');

// B. Script verification in clinic-base/assets/app.js
$appJs = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/app.js');
if ($appJs === false) {
    throw new RuntimeException('clinic-base/assets/app.js could not be read');
}

foreach ([
    'function initialiseDoctorDirectorySearch',
    'doctor-search',
    'doctor-search-empty',
    '.doctor-directory tbody tr',
    'row.hidden = !matched',
    'emptyMessage.hidden = matchedCount > 0',
    "event.key === 'Enter'",
    'event.preventDefault()',
    "document.addEventListener('DOMContentLoaded', initialiseDoctorDirectorySearch)",
] as $needle) {
    assert_contains($appJs, $needle, "app.js search logic must contain {$needle}");
}

// Zero network transport in app.js search logic
assert_true(strpos($appJs, 'fetch(') === false, 'app.js must not contain fetch');
assert_true(strpos($appJs, 'XMLHttpRequest') === false, 'app.js must not contain XMLHttpRequest');

// C. Search CSS verification in clinic-base/assets/css/style.css
$styleCss = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
if ($styleCss === false) {
    throw new RuntimeException('clinic-base/assets/css/style.css could not be read');
}

foreach ([
    '.doctor-search-box',
    '.doctor-search-box input[type="search"]:focus-visible',
    '.doctor-directory tbody tr[hidden]',
    'display: none !important',
    '#doctor-search-empty',
] as $needle) {
    assert_contains($styleCss, $needle, "style.css must contain {$needle}");
}

// =========================================================================
// 2. Feature 2: Printable patient consultation slip & @media print (#210)
// =========================================================================

// A. Markup verification in clinic-base/patient/home.php
$patientHomePage = file_get_contents(dirname(__DIR__) . '/clinic-base/patient/home.php');
if ($patientHomePage === false) {
    throw new RuntimeException('clinic-base/patient/home.php could not be read');
}

foreach ([
    'require_login();',
    'class="button print-slip-button"',
    'onclick="window.print()"',
    'Print consultation slip',
    'class="medical-slip"',
    'assets/img/clinic-logo.svg',
    'Clinic Appointment Portal',
    'NTU South Spine S2-B3A-06',
    'Tel: 999',
    'Medical Consultation Summary & Certificate',
    'Issue date/time:',
    'Patient Name:',
    'Patient ID:',
    'Attending Doctor:',
    'Medical Specialty:',
    'Clinical findings',
    'Diagnosis:',
    'Prescribed Medications:',
    'Treatment Plan:',
    'Follow-up instructions:',
    'Attending Physician Signature',
    'Clinic Stamp',
    'completed_appointment_for_patient',
    'patient/home.php?view=',
] as $needle) {
    assert_contains($patientHomePage, $needle, "patient/home.php must contain {$needle}");
}

// B. Print CSS rules in clinic-base/assets/css/style.css
foreach ([
    '.print-slip-button',
    '.medical-slip',
    '@media print',
    '@page {',
    'margin: 1.5cm;',
    '.site-header,',
    '.site-nav,',
    '.site-footer,',
    '.page-intro,',
    '.upcoming-appointments,',
    '.past-appointments,',
    '.cancelled-appointments,',
    '.patient-notifications,',
    '.print-slip-button,',
    '.table-scroll-hint,',
    '.visit-notes-header',
    'display: block !important;',
    'break-inside: avoid;',
    'filter: grayscale(100%);',
] as $needle) {
    assert_contains($styleCss, $needle, "style.css print styles must contain {$needle}");
}

// =========================================================================
// 3. Database model & isolation verification (ie4727db_test)
// =========================================================================

require_once dirname(__DIR__) . '/clinic-base/lib/db.php';
require_once dirname(__DIR__) . '/clinic-base/models/appointments.php';
require_once dirname(__DIR__) . '/clinic-base/models/visit_remarks.php';

// Valid completed appointment for patient 1 (alextan)
$visit = completed_appointment_for_patient(1, 2);
assert_true($visit !== null, 'Patient 1 completed appointment 2 should be returned');
assert_eq((int) $visit['appointmentID'], 2, 'appointmentID matches');
assert_eq((int) $visit['PatientID'], 1, 'PatientID matches');
assert_eq($visit['Status'], 'Completed', 'Status is Completed');
assert_true(is_string($visit['DoctorName']) && $visit['DoctorName'] !== '', 'DoctorName is populated');
assert_true(is_string($visit['Specialty']) && $visit['Specialty'] !== '', 'Specialty is populated');
assert_true(isset($visit['Diagnosis']), 'Diagnosis exists');
assert_true(isset($visit['Prescription']), 'Prescription exists');
assert_true(isset($visit['Treatment']), 'Treatment exists');
assert_true(isset($visit['FollowUp']), 'FollowUp exists');

// Patient isolation: patient 2 querying patient 1's appointment must return null
$unauthorized = completed_appointment_for_patient(2, 2);
assert_true($unauthorized === null, 'Patient 2 cannot view Patient 1 completed appointment');

// Ineligible / non-completed appointment lookup returns null
$nonCompleted = completed_appointment_for_patient(1, 999999);
assert_true($nonCompleted === null, 'Non-existent appointment returns null');

// Remarks decoding for completed visit
$remarks = decode_visit_remarks($visit['Remarks'], (string) $visit['Status']);
assert_true(is_array($remarks), 'decoded remarks is an array');
assert_true(array_key_exists('doctor_remarks', $remarks), 'remarks contains doctor_remarks');
assert_true(array_key_exists('reason', $remarks), 'remarks contains reason');
assert_true(array_key_exists('legacy', $remarks), 'remarks contains legacy');

echo "PASS: test_standout_features\n";

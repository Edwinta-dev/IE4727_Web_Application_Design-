<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/assert.php';

// Ensure test database is selected
if (getenv('CLINIC_DB_NAME') === false || getenv('CLINIC_DB_NAME') === '') {
    putenv('CLINIC_DB_NAME=ie4727db_test');
}

// =========================================================================
// Structural checks for the browser-tested search and print features
// =========================================================================

$doctorsPage = file_get_contents(dirname(__DIR__) . '/clinic-base/screens/doctors.php');
if ($doctorsPage === false) {
    throw new RuntimeException('clinic-base/screens/doctors.php could not be read');
}

$inputExists = preg_match('/<input\b(?=[^>]*\bid="doctor-search")(?=[^>]*\btype="search")[^>]*>/i', $doctorsPage) === 1;
$messageExists = preg_match('/<[^>]+\bid="doctor-search-empty"[^>]*>/i', $doctorsPage) === 1;
assert_true($inputExists, 'doctor search input exists with its id');
assert_true($messageExists, 'doctor search result message exists with its id');

$appJs = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/app.js');
if ($appJs === false) {
    throw new RuntimeException('clinic-base/assets/app.js could not be read');
}

assert_true(strpos($appJs, 'fetch(') === false, 'app.js must not contain fetch');
assert_true(strpos($appJs, 'XMLHttpRequest') === false, 'app.js must not contain XMLHttpRequest');

$patientHomePage = file_get_contents(dirname(__DIR__) . '/clinic-base/screens/patient/home.php');
if ($patientHomePage === false) {
    throw new RuntimeException('clinic-base/screens/patient/home.php could not be read');
}

$summaryExists = preg_match('/class="[^"]*\bmedical-slip\b[^"]*"/', $patientHomePage) === 1;
$printButtonExists = preg_match('/class="[^"]*\bprint-slip-button\b[^"]*"/', $patientHomePage) === 1;
assert_true($summaryExists, 'consultation summary block exists');
assert_true($printButtonExists, 'consultation summary print button exists');

$styleCss = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
if ($styleCss === false) {
    throw new RuntimeException('clinic-base/assets/css/style.css could not be read');
}
assert_true(preg_match('/@media\s+print\b/i', $styleCss) === 1, 'print media block exists');

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

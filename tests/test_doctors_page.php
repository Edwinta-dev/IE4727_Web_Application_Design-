<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/doctors.php');
if ($page === false) {
    throw new RuntimeException('doctors page could not be read');
}

foreach (['<table', '<caption>', '<thead>', '<tbody>', 'all_specialties()', 'name="specialty"',
    'method="get"', 'doctor.php?id=', 'book.php?doctor_id=', 'No doctors match that specialty',
    'class="doctor-photo"', 'csrf_field()', '<colgroup>', 'doctor-column-qualifications',
    'class="active-doctor-filter"', 'class="clear-doctor-filter"', 'class="doctor-directory-book"',
    'aria-label="Doctor directory" tabindex="0"', 'doctor-next-available'] as $needle) {
    assert_contains($page, $needle);
}

echo "PASS: doctors page markup checks\n";

$styles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
if ($styles === false) {
    throw new RuntimeException('doctor directory stylesheet could not be read');
}
assert_contains($styles, '.doctor-directory tbody td:nth-child(3),');
assert_contains($styles, '.doctor-directory tbody td:nth-child(7) { line-height: 4.75rem; }');

echo "PASS: doctor directory row rhythm checks\n";

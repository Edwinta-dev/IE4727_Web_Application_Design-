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
foreach (['class="photo-banner doctor-directory-banner"', 'class="photo-scrim"', 'class="hero-image"',
    'class="photo-caption"', 'Find a doctor</h1>', 'class="doctor-directory-subtitle"',
    '/assets/img/Clinic_Assisting_Elderly_woman.jpg', 'class="specialty-filter"'] as $needle) {
    assert_contains($page, $needle);
}
assert_true(!str_contains($page, 'class="page-intro-image"'), 'directory uses its photo banner instead of the floating intro image');

echo "PASS: doctors page markup checks\n";

$styles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
$sharedStyles = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
$tokens = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/tokens.css');
if ($styles === false) {
    throw new RuntimeException('doctor directory stylesheet could not be read');
}
if ($sharedStyles === false || $tokens === false) {
    throw new RuntimeException('shared page-width styles could not be read');
}
assert_contains($styles, '.doctor-directory tbody td:nth-child(3),');
assert_contains($styles, '.doctor-directory tbody td:nth-child(7) { line-height: 4.75rem; }');
assert_contains($styles, '.doctor-directory { width: 100%; table-layout: auto; }');
assert_contains($styles, 'width: 4.5rem; height: 4.5rem;');
assert_contains($tokens, '--page-width: 80vw;');
assert_contains($tokens, '--page-width: calc(100% - 2rem);');
assert_contains($tokens, '@media (min-width: 64rem)');
assert_contains($sharedStyles, 'width: var(--page-width);');
assert_contains($sharedStyles, 'main p { max-width: 70ch; }');
assert_contains($sharedStyles, '.featured-doctors > h2,');
assert_true(!str_contains($sharedStyles, 'max-width: 76rem'), 'registration must use the shared page width');

echo "PASS: doctor directory row rhythm checks\n";

<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/screens/doctors.php');
if ($page === false) {
    throw new RuntimeException('doctors page could not be read');
}

foreach (['<table', '<caption>', '<thead>', '<tbody>', 'all_specialties()', 'name="specialty"',
    'method="get"', 'doctor.php?id=', 'book.php?doctor=', 'No doctors match that specialty',
    'class="doctor-photo"', '<colgroup>', 'doctor-column-qualifications',
    'class="active-doctor-filter"', 'class="clear-doctor-filter"', 'class="doctor-directory-book"',
    'aria-label="Doctor directory" tabindex="0"', 'doctor-next-available'] as $needle) {
    assert_contains($page, $needle);
}
foreach (['class="photo-banner doctor-directory-banner"', 'class="photo-scrim"', 'class="hero-image"',
    'class="photo-caption"', 'Find a doctor</h1>', 'class="doctor-directory-subtitle"',
    '/assets/img/Clinic_Assisting_Elderly_woman.jpg', 'class="specialty-filter"'] as $needle) {
    assert_contains($page, $needle);
}
foreach ([
    'class="doctor-search-box"',
    '<label for="doctor-search">Search doctors</label>',
    '<div class="doctor-search-box" hidden>',
    '<input type="search" id="doctor-search" placeholder="Search by doctor name, specialty, or language..." autocomplete="off">',
    '<p id="doctor-search-empty" class="empty-state" aria-live="polite" hidden>No doctors match your search query. Try another keyword or clear the search.</p>',
    'data-search="<?= e(implode(\' \', array_map',
] as $needle) {
    assert_contains($page, $needle, 'issue #209 doctor live search markup');
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

assert_contains($styles, '.doctor-search-box', 'doctor search box styling');
assert_contains($styles, '.doctor-search-box input[type="search"]:focus-visible', 'search focus visible');
assert_contains($styles, 'outline: 0.2rem solid var(--c-primary);', 'search focus uses primary token');
assert_true(!str_contains($styles, 'c-action'), 'legacy action color alias is removed');
assert_contains($styles, '#doctor-search-empty', 'search empty state styling');

$script = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/app.js');
if ($script === false) {
    throw new RuntimeException('app.js could not be read');
}
assert_contains($script, 'initialiseDoctorDirectorySearch', 'live search initialization function');
assert_contains($script, 'doctor-search', 'search input id lookup');
assert_contains($script, 'doctor-search-empty', 'search empty message id lookup');
assert_contains($script, '.doctor-directory tbody tr', 'doctor rows selection');
assert_contains($script, 'row.hidden = !matched', 'row hidden toggle');
assert_contains($script, "row.getAttribute('data-search')", 'search uses the server-marked fields only');
assert_contains($script, 'rows.length === 0', 'search is skipped without doctor rows');
assert_contains($script, 'matchedCount +', 'result count is announced');

echo "PASS: doctor live search tests\n";

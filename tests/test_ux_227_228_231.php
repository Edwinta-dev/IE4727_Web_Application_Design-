<?php

declare(strict_types=1);

// #227 shared title spacing, #228 admin date range in the site date format, #231 "Awaiting outcome" help.
$root = dirname(__DIR__) . '/clinic-base/';
$css = (string) file_get_contents($root . 'assets/css/style.css');
assert_true(preg_match('/^main > \.page-intro:first-child\s*\{\s*padding-top:\s*var\(--space-4\)/m', $css) === 1, 'every first page intro gets the same top gap');
assert_true(strpos($css, '.page-intro-spaced') === false, 'per-page spacing class removed');
foreach (['screens/book.php', 'screens/register.php'] as $file) {
    assert_true(strpos((string) file_get_contents($root . $file), 'page-intro-spaced') === false, "$file uses the shared rule");
}

// #231: the explanation is on the page (not a tooltip) and only appears when a row needs it.
$home = (string) file_get_contents($root . 'screens/patient/home.php');
assert_true(strpos($home, 'Awaiting outcome</strong> means the visit time has passed') !== false, 'Awaiting outcome is explained in text');
assert_true(preg_match('/if \(\$awaitingOutcome !== \[\]\):\s*\?>\s*<p class="status-help">/', $home) === 1, 'help shows only when a past row is awaiting an outcome');

// #228: the chosen range is repeated in "04 Nov 2026" form.
require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';
$console = (string) file_get_contents($root . 'screens/admin/console.php');
foreach (['Showing appointments from', 'onwards.', 'Showing appointments up to'] as $needle) {
    assert_true(strpos($console, $needle) !== false, "console summary wording: $needle");
}
assert_true(strpos($console, 'class="filter-summary"') !== false && strpos($console, "fmt_date(\$filters['date_from'])") !== false, 'summary is built with fmt_date()');
assert_eq(fmt_date('2026-11-04'), '04 Nov 2026', 'site date format');
echo "OK: title spacing, admin date range summary, Awaiting outcome help\n";

<?php

declare(strict_types=1);

// Stylesheets and scripts carry ?v=<last-modified> so browsers never keep serving a stale copy
// after a change (users kept seeing the old "Days to skip" layout after it was fixed).
require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';

$mtime = filemtime(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
assert_eq(asset_url('/assets/css/style.css'), '/assets/css/style.css?v=' . $mtime, 'existing file gets its modified time');
assert_eq(asset_url('/assets/css/missing-file.css'), '/assets/css/missing-file.css', 'missing file gets a plain URL, never an error');

$GLOBALS['_auth_current_user_cache'] = null;
$pageTitle = 'Asset version test';
ob_start();
require dirname(__DIR__) . '/clinic-base/partials/header.php';
$html = (string) ob_get_clean();
preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $html, $links);
assert_eq(count($links[1]), 3, 'three stylesheets are linked directly');
foreach ($links[1] as $href) {
    assert_true(preg_match('/\?v=\d+$/', $href) === 1, "stylesheet $href is versioned");
}
assert_true(strpos($links[1][0], 'tokens.css') !== false && strpos($links[1][1], 'assets/style.css') !== false && strpos($links[1][2], 'css/style.css') !== false,
    'load order is tokens, base stylesheet, then the newer rules');
$css = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/css/style.css');
assert_true(strpos($css, '@import') === false, 'no @import, which could not be versioned');

foreach (['partials/nav.php', 'screens/register.php', 'screens/doctor/schedule.php', 'screens/doctors.php', 'screens/index.php', 'screens/book.php'] as $file) {
    $source = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/' . $file);
    assert_true(preg_match("/<script src=\"<\\?= e\\(url\\('\\/assets/", $source) === 0, "$file loads its script through asset_url()");
}
echo "OK: stylesheets and scripts are versioned\n";

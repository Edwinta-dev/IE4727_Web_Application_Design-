<?php

declare(strict_types=1);

// Browser geometry and keyboard acceptance: node tests/ui_header.mjs
$root = dirname(__DIR__) . '/clinic-base/';
$navigation = (string) file_get_contents($root . 'partials/nav.php');
assert_contains($navigation, 'type="button" aria-controls="site-navigation" aria-expanded="false" hidden');
assert_contains($navigation, '<nav id="site-navigation" class="site-nav" aria-label="Main navigation">');
assert_contains($navigation, "e(url('/assets/nav.js'))");
assert_true(is_file($root . 'assets/nav.js'), 'shared navigation script ships locally');
echo "OK: #145 shared menu control and local script contract\n";

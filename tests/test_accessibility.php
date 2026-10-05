<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'clinic-base/partials/header.php',
    'clinic-base/partials/nav.php',
    'clinic-base/doctor.php',
    'clinic-base/admin/outbox.php',
    'clinic-base/assets/style.css',
    'docs/ACCESSIBILITY_CHECKLIST.md',
];
foreach ($files as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        fwrite(STDERR, "FAIL: missing accessibility audit file: {$file}\n");
        exit(1);
    }
}
$read = static fn (string $file): string => (string) file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
$checks = [
    [$read('clinic-base/partials/header.php'), 'skip-link'],
    [$read('clinic-base/doctor.php'), 'id="main-content"'],
    [$read('clinic-base/partials/nav.php'), 'aria-label="Main navigation"'],
    [$read('clinic-base/doctor.php'), 'aria-label="Book an appointment with'],
    [$read('clinic-base/admin/outbox.php'), '<table aria-labelledby="outbox-list-heading">'],
    [$read('clinic-base/admin/outbox.php'), '<h2 id="outbox-list-heading">Message log</h2>'],
    [$read('clinic-base/admin/outbox.php'), 'scope="col"'],
    [$read('clinic-base/assets/style.css'), ':focus-visible'],
    [$read('clinic-base/assets/style.css'), 'prefers-reduced-motion'],
    [$read('docs/ACCESSIBILITY_CHECKLIST.md'), '200% zoom'],
];
foreach ($checks as [$contents, $needle]) {
    if (strpos($contents, $needle) === false) {
        fwrite(STDERR, "FAIL: missing accessibility requirement: {$needle}\n");
        exit(1);
    }
}
echo "OK: issue #68 accessibility audit and checklist\n";

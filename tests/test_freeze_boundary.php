<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$boundary = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'BASE_RELEASE_BOUNDARY.md');
if ($boundary === false) {
    fwrite(STDERR, "FAIL: release boundary document is missing or unreadable\n");
    exit(1);
}

foreach ([
    'exactly the eleven entries',
    'no AJAX/fetch/XMLHttpRequest',
    'no custom deployment',
    'fixed PascalCase MariaDB schema',
    'JSON `Allergies`',
    '`recipient`',
    'local-only',
    'XAMPP Apache/MariaDB',
    'working tree is clean',
] as $phrase) {
    if (strpos($boundary, $phrase) === false) {
        fwrite(STDERR, "FAIL: release boundary is missing: {$phrase}\n");
        exit(1);
    }
}

$pages = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'PAGES.md');
if ($pages === false || substr_count($pages, '| `') !== 11) {
    fwrite(STDERR, "FAIL: baseline page budget is not exactly eleven pages\n");
    exit(1);
}

echo "OK: issue #73 clinic-base boundary is documented\n";


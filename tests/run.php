<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pages = [
    'index.php',
    'doctors.php',
    'doctor.php',
    'book.php',
    'register.php',
    'patient/home.php',
    'doctor/home.php',
    'doctor/schedule.php',
    'doctor/visit.php',
    'admin/console.php',
    'admin/outbox.php',
];

$contents = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs/PAGES.md');
if ($contents === false) {
    fwrite(STDERR, "FAIL: docs/PAGES.md is unreadable\n");
    exit(1);
}

foreach ($pages as $page) {
    if (strpos($contents, '| `' . $page . '` |') === false) {
        fwrite(STDERR, "FAIL: missing page budget entry: {$page}\n");
        exit(1);
    }
}

if (substr_count($contents, "| `") !== count($pages)) {
    fwrite(STDERR, "FAIL: page budget contains an unexpected page count\n");
    exit(1);
}

$decisions = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs/DECISIONS.md');
$required = [
    'doctor` and `patient` tables',
    'no `users` table',
    '`SlotDateTime`',
    '`appointmentDateTime`',
    'materialised rows in `slots`',
    '`ADMIN_USER` and `ADMIN_HASH`',
    '`002_migrate.sql` is additive-only',
];
foreach ($required as $phrase) {
    if ($decisions === false || strpos($decisions, $phrase) === false) {
        fwrite(STDERR, "FAIL: missing decision: {$phrase}\n");
        exit(1);
    }
}

echo "PASS: issue #1 scaffold checks\n";

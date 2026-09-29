<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$matrix = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs/ACCEPTANCE_MATRIX.md');
$pages = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs/PAGES.md');
$evidence = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs/GRADER_EVIDENCE.md');

if ($matrix === false || $pages === false || $evidence === false) {
    fwrite(STDERR, "FAIL: issue #59 evidence files are unreadable\n");
    exit(1);
}

$criteria = [];
if (preg_match_all('/\| (C\d{2}) \|/', $matrix, $matches) !== 32) {
    fwrite(STDERR, "FAIL: acceptance matrix must contain 32 criteria\n");
    exit(1);
}
$criteria = array_unique($matches[1]);
if (count($criteria) !== 32 || $criteria !== array_map(static fn (int $n): string => 'C' . str_pad((string) $n, 2, '0', STR_PAD_LEFT), range(1, 32))) {
    fwrite(STDERR, "FAIL: acceptance criteria are not contiguous\n");
    exit(1);
}

$requiredPages = [
    'index.php', 'doctors.php', 'doctor.php', 'book.php', 'register.php',
    'patient/home.php', 'doctor/home.php', 'doctor/schedule.php',
    'doctor/visit.php', 'admin/console.php', 'admin/outbox.php',
];
foreach ($requiredPages as $page) {
    if (strpos($matrix, '`' . $page . '`') === false || strpos($pages, '`' . $page . '`') === false) {
        fwrite(STDERR, "FAIL: page is not mapped in the evidence pack: {$page}\n");
        exit(1);
    }
}

foreach (['php tests/run.php', 'php tools/db_reset.php', 'doctor', 'patient', 'admin'] as $phrase) {
    if (strpos($evidence, $phrase) === false) {
        fwrite(STDERR, "FAIL: missing evidence item: {$phrase}\n");
        exit(1);
    }
}

echo "OK: issue #59 acceptance matrix covers all criteria and pages\n";

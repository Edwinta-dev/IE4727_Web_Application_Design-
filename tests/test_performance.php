<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$budgetPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'PERFORMANCE_BUDGET.md';
$asset = $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'clinic-logo.svg';
if (!is_file($budgetPath) || !is_file($asset)) {
    fwrite(STDERR, "FAIL: performance budget or asset inventory is missing\n");
    exit(1);
}
$budget = (string) file_get_contents($budgetPath);
foreach (['Rendered PHP page source', 'Images per page', 'Total local image bytes', 'SQL calls per request', 'Slow query threshold', 'clinic-logo.svg', '160 × 100', '282'] as $needle) {
    if (strpos($budget, $needle) === false) {
        fwrite(STDERR, "FAIL: performance budget is missing {$needle}\n");
        exit(1);
    }
}
$bytes = filesize($asset);
if ($bytes !== 282) {
    fwrite(STDERR, "FAIL: clinic-logo.svg byte inventory is stale\n");
    exit(1);
}
$files = [
    $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'doctor.php',
    $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'outbox.php',
    $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php',
];
foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    if (stripos($source, '<img') === false) {
        continue;
    }
    foreach (['width=', 'height=', 'alt=', 'loading=', 'decoding='] as $attribute) {
        if (stripos($source, $attribute) === false) {
            fwrite(STDERR, "FAIL: image missing {$attribute} in {$file}\n");
            exit(1);
        }
    }
}
echo "OK: issue #69 performance budgets and image asset audit\n";

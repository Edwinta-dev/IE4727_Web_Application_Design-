<?php

declare(strict_types=1);

// The regression test rebuilds and writes only ie4727db_test. Keep this
// acceptance probe on the same isolated test database as the normal suite.
putenv('CLINIC_DB_NAME=ie4727db_test');

$command = escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run.php')
    . ' test_slot_reuse';
$output = [];
$status = 0;
exec($command . ' 2>&1', $output, $status);
foreach ($output as $line) {
    echo $line . PHP_EOL;
}

if ($status !== 0) {
    exit($status);
}

echo "OK: availability notices went to the active patient and doctor; the historical patient received none.\n";

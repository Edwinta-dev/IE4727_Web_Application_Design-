<?php

declare(strict_types=1);

// The historical probe referenced by the audit report was not present in this
// checkout. Keep its documented acceptance command backed by the committed
// two-process regression, which resets and writes only ie4727db_test.
putenv('CLINIC_DB_NAME=ie4727db_test');
putenv('MAIL_DELIVERY=off');
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tests/run.php')
    . ' test_concurrency_reschedule';
passthru($command, $status);
if ($status !== 0) exit($status);
echo "OK: one racing mutation succeeds per conflict; no booked slot is stranded\n";

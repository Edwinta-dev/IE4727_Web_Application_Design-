<?php

declare(strict_types=1);

// Separate processes exercise immutable config constants without changing the
// parent suite's delivery setting. The built-in mail() is disabled in each.
$previousMailDelivery = getenv('MAIL_DELIVERY');
putenv('MAIL_DELIVERY');
try {
    foreach ([['off', 'logged'], ['on', 'failed'], ['on', 'sent'], ['default', 'failed']] as [$mode, $status]) {
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -d disable_functions=mail '
            . escapeshellarg(__DIR__ . '/fixtures/mail_delivery.php') . ' '
            . escapeshellarg($mode) . ' ' . escapeshellarg($status) . ' 2>&1', $output, $exit);
        assert_eq($exit, 0, $mode . ' mail regression: ' . implode("\n", $output));
        assert_contains(implode("\n", $output), 'OK: ' . $mode . ' booking', 'child acceptance');
    }
} finally {
    putenv($previousMailDelivery === false ? 'MAIL_DELIVERY' : 'MAIL_DELIVERY=' . $previousMailDelivery);
}
echo "OK: delivery-off booking under 1s, no mail attempts, logged pair; delivery-on row-first sent/failed\n";

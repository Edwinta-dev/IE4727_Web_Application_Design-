<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'DEMO_SCRIPT.md');
if ($script === false) {
    fwrite(STDERR, "FAIL: final demo script is missing or unreadable\n");
    exit(1);
}

$required = [
    '0:00–1:30', 'registration and login', 'doctors and slots',
    'booking and double-book protection', 'cancellation and rescheduling',
    'doctor visit record', 'notifications', 'admin console and outbox',
    'release gate and evidence handoff', 'Password123', 'ADMIN_USER',
    'php tools/db_reset.php', 'php tests/run.php', 'verify_clean.ps1',
    'recipient', 'deliveryStatus', 'ACCEPTANCE_MATRIX.md',
];
foreach ($required as $phrase) {
    if (strpos($script, $phrase) === false) {
        fwrite(STDERR, "FAIL: demo script is missing: {$phrase}\n");
        exit(1);
    }
}

if (preg_match('/password\s*[:=]\s*[^\s`]+/i', $script) === 1) {
    fwrite(STDERR, "FAIL: demo script appears to disclose a password assignment\n");
    exit(1);
}

echo "OK: issue #72 release handoff evidence is complete\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$auth = file_get_contents($root . DIRECTORY_SEPARATOR . 'clinic-base/lib/auth.php');
$config = file_get_contents($root . DIRECTORY_SEPARATOR . 'clinic-base/config.php');
if ($auth === false || $config === false) {
    fwrite(STDERR, "FAIL: authentication files are unreadable\n");
    exit(1);
}

foreach ([
    "'httponly' => true",
    "'samesite' => 'Lax'",
    'session_regenerate_id(true)',
    'session_destroy()',
    'AUTH_MAX_FAILURES = 5',
    'Invalid username or password.',
    'ADMIN_USER',
    'ADMIN_HASH',
] as $required) {
    if (strpos($auth . $config, $required) === false) {
        fwrite(STDERR, "FAIL: missing auth hardening: {$required}\n");
        exit(1);
    }
}

echo "OK: issue #62 authentication hardening\n";

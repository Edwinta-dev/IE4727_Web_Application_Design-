<?php

declare(strict_types=1);

// Local base setup only: no database access and no shared/default password.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$user = trim($argv[1] ?? 'admin');
$target = dirname(__DIR__) . '/clinic-base/config.local.php';
if (count($argv) > 2 || $user === '' || strlen($user) > 100) {
    fwrite(STDERR, "Usage: php tools/setup_admin.php [username] (password on stdin)\n");
    exit(1);
}
if (file_exists($target)) {
    fwrite(STDERR, "config.local.php already exists; preserve its settings and configure ADMIN_USER/ADMIN_HASH manually.\n");
    exit(1);
}
fwrite(STDERR, "Read local admin password from stdin (8-72 bytes, one line).\n");
$plain = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($plain) < 8 || strlen($plain) > 72 || str_contains($plain, "\0")) {
    fwrite(STDERR, "Password must contain 8-72 bytes and no null bytes.\n");
    exit(1);
}
$hash = password_hash($plain, PASSWORD_DEFAULT);
unset($plain);
$contents = "<?php\n\n// Local admin credentials; never commit this file.\n"
    . "define('ADMIN_USER', " . var_export($user, true) . ");\n"
    . "define('ADMIN_HASH', " . var_export($hash, true) . ");\n";
// Exclusive creation also prevents overwriting a config created concurrently.
$handle = @fopen($target, 'x');
if ($handle === false) {
    fwrite(STDERR, "Could not create clinic-base/config.local.php; no existing configuration was changed.\n");
    exit(1);
}
$written = fwrite($handle, $contents);
fclose($handle);
if ($written !== strlen($contents)) {
    unlink($target);
    fwrite(STDERR, "Could not write local configuration.\n");
    exit(1);
}
echo "OK: created clinic-base/config.local.php with a hashed local admin password.\n";

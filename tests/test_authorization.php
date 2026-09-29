<?php

declare(strict_types=1);

/**
 * Source-level authorization contract for issue #63.
 *
 * The page work is delivered incrementally, so this test audits every PHP
 * handler that exists rather than maintaining a second, easily-stale list of
 * mutation routes. A handler is a mutation handler when it reads POST data or
 * checks the request method. Models are checked for the ownership predicates
 * required by their mutation entry points.
 */
$root = dirname(__DIR__);
$base = $root . DIRECTORY_SEPARATOR . 'clinic-base';
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $files[] = $file->getPathname();
    }
}

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$mutationCount = 0;
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        $fail('could not read ' . $file);
    }

    $isHandler = preg_match('/\$_POST\b|REQUEST_METHOD\s*\].*POST|REQUEST_METHOD\s*===\s*[\'\"]POST/i', $source) === 1;
    if (!$isHandler) {
        continue;
    }
    $mutationCount++;
    $relative = str_replace($base . DIRECTORY_SEPARATOR, '', $file);
    $postAt = min(array_filter([
        strpos($source, '$_POST'),
        strpos($source, "REQUEST_METHOD"),
    ], static fn ($position): bool => $position !== false));
    $csrfAt = strpos($source, 'csrf_check(');
    if ($csrfAt === false || $csrfAt > $postAt) {
        $fail("{$relative}: csrf_check() must precede POST data use");
    }
    if (strpos($source, 'header(') === false || !preg_match('/Location\s*:/i', $source)) {
        $fail("{$relative}: mutation must use POST-Redirect-GET");
    }

    $guarded = preg_match('/require_(?:login|doctor|admin|role)\s*\(/', $source) === 1;
    if (!$guarded) {
        $fail("{$relative}: mutation handler has no role guard");
    }
}

// Mutation model SQL must scope tenant-owned records, not trust forged IDs.
$modelDir = $base . DIRECTORY_SEPARATOR . 'models';
if (is_dir($modelDir)) {
    foreach (glob($modelDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
        $source = file_get_contents($file);
        if ($source === false || !preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $source)) {
            continue;
        }
        $relative = str_replace($base . DIRECTORY_SEPARATOR, '', $file);
        if (!preg_match('/DoctorID|PatientID|appointmentID|slotID/i', $source)) {
            $fail("{$relative}: mutation has no tenant/ownership key");
        }
    }
}

// Keep the shared guards and CSRF implementation part of this contract.
$auth = file_get_contents($base . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'auth.php');
$helpers = file_get_contents($base . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php');
if ($auth === false || $helpers === false) {
    $fail('shared authorization files are unreadable');
}
foreach (['function require_login', 'function require_role', 'http_response_code(401)', 'http_response_code(403)'] as $needle) {
    if (strpos($auth, $needle) === false) {
        $fail('missing authorization primitive: ' . $needle);
    }
}

echo "OK: issue #63 authorization and tenant-isolation audit ({$mutationCount} mutation handlers)\n";

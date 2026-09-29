<?php

declare(strict_types=1);

$base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic-base';
$violations = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }

    $relative = str_replace('\\', '/', substr($path, strlen($base) + 1));
    if (preg_match('/->\s*(?:query|exec)\s*\(|\bmysqli_[A-Za-z_]+\b/', $source, $match) === 1) {
        $violations[] = "{$relative}: forbidden database API {$match[0]}";
    }

    $lines = preg_split('/\R/', $source) ?: [];
    foreach ($lines as $lineNumber => $line) {
        if (preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE)\b.*\$_(?:GET|POST)|\$_(?:GET|POST).*\b(?:SELECT|INSERT|UPDATE|DELETE)\b/', $line) === 1) {
            $violations[] = "{$relative}: request data is adjacent to SQL on line " . ($lineNumber + 1);
        }

        // SQL must use named parameters; do not interpolate a PHP variable into
        // a query string. Dynamic SQL in models is assembled from fixed clauses.
        if (preg_match('/\"[^\"\r\n]*\b(?:SELECT|INSERT|UPDATE|DELETE)\b[^\"\r\n]*\$[A-Za-z_]/', $line) === 1) {
            $violations[] = "{$relative}: PHP interpolation is adjacent to SQL on line " . ($lineNumber + 1);
        }
    }

    $isModel = str_starts_with($relative, 'models/');
    $isPage = preg_match('~^(?:[^/]+\.php|(?:admin|doctor|patient)/[^/]+\.php)$~', $relative) === 1;
    if (!$isModel && preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/', $source) === 1) {
        $violations[] = "{$relative}: SQL is outside models/";
    }

    if ($isPage && preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/', $source) === 1) {
        $violations[] = "{$relative}: page contains SQL";
    }
}

if ($violations !== []) {
    throw new RuntimeException("SQL audit failed:\n- " . implode("\n- ", $violations));
}

echo "PASS: SQL audit\n";

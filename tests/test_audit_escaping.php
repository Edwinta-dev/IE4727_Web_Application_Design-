<?php

declare(strict_types=1);

$base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic-base';
$violations = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }

    $relative = str_replace('\\', '/', substr($path, strlen($base) + 1));
    foreach (preg_split('/\R/', $source) ?: [] as $lineNumber => $line) {
        // The audit intentionally targets the common direct-variable forms.
        // Calls such as csrf_field() are helper-generated trusted markup, not
        // dynamic values, and are covered by the whitelist in DECISIONS.md.
        if (preg_match('/<\?=\s*\$|\becho\s+\$/', $line) === 1) {
            $violations[] = "{$relative}: unescaped output on line " . ($lineNumber + 1);
        }
    }
}

if ($violations !== []) {
    throw new RuntimeException("escaping audit failed:\n- " . implode("\n- ", $violations));
}

echo "PASS: escaping audit\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'verify_clean.ps1';
if (!is_file($script)) {
    fwrite(STDERR, "FAIL: tools/verify_clean.ps1 is missing\n");
    exit(1);
}

$contents = file_get_contents($script);
$required = ['PHP and extensions', 'Secrets and local-only files', 'Forbidden dependencies', 'PHP lint', 'Isolated database reset', 'Full test suite', 'Route smoke tests'];
foreach ($required as $stage) {
    if ($contents === false || strpos($contents, $stage) === false) {
        fwrite(STDERR, "FAIL: verifier is missing stage: {$stage}\n");
        exit(1);
    }
}

echo "PASS: issue #58 verifier checks\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$reset = file_get_contents($root . '/tools/db_reset.php');
$serve = file_get_contents($root . '/tools/serve.sh');

if ($reset === false || $serve === false) {
    throw new RuntimeException('tool files are unreadable');
}

foreach (['001_schema.sql', '002_migrate.sql', '003_seed.sql'] as $schema) {
    if (strpos($reset, "schema/{$schema}") === false) {
        throw new RuntimeException('db_reset is missing ' . $schema);
    }
}

// Live mode has been removed by #131. Prove refusal instead of requiring its old implementation.
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/db_reset.php') . ' --production 2>&1', $refusal, $status);
if ($status === 0 || !str_contains(implode("\n", $refusal), 'only ie4727db_test')
    || strpos($reset, "['doctor', 'patient', 'slots', 'appointment', 'notifications']") === false) {
    throw new RuntimeException('db_reset is missing its live refusal or table summaries');
}

if (strpos($serve, 'php -S localhost:8000 -t clinic-base') === false
    || strpos(strtolower($serve), 'testing only') === false
    || strpos($serve, 'XAMPP/Apache') === false) {
    throw new RuntimeException('serve.sh does not match the testing contract');
}

echo "PASS: issue #6 tool checks\n";

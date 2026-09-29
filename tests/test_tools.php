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

if (strpos($reset, "stripos(\$name, 'clinic')") === false
    || strpos($reset, "['doctor', 'patient', 'slots', 'appointment', 'notifications']") === false) {
    throw new RuntimeException('db_reset is missing its safety guard or table summaries');
}

if (strpos($serve, 'php -S localhost:8000 -t clinic-base') === false
    || strpos(strtolower($serve), 'testing only') === false
    || strpos($serve, 'XAMPP/Apache') === false) {
    throw new RuntimeException('serve.sh does not match the testing contract');
}

echo "PASS: issue #6 tool checks\n";

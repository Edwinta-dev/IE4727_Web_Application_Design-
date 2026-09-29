<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seed = file_get_contents($root . '/schema/003_seed.sql');
if ($seed === false) {
    throw new RuntimeException('seed file is unreadable');
}

foreach (['INSERT INTO `doctor`', 'INSERT INTO `patient`', 'INSERT INTO `slots`', 'INSERT INTO `appointment`', 'Password123', '10:30:00'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('seed is missing ' . $needle);
    }
}

if (substr_count($seed, "'Completed'") < 2 || substr_count($seed, "'No show'") < 1 || substr_count($seed, "'Cancelled'") < 1 || substr_count($seed, "'Future'") < 1) {
    throw new RuntimeException('seed does not cover appointment states');
}

$runbook = file_get_contents($root . '/docs/RUNBOOK.md');
if ($runbook === false || strpos($runbook, "password_hash('Password123', PASSWORD_DEFAULT)") === false) {
    throw new RuntimeException('runbook does not document demo hashes');
}

echo "PASS: seed checks\n";

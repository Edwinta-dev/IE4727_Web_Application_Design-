<?php

declare(strict_types=1);

$testMode = in_array('--test', $argv, true);
$productionMode = in_array('--production', $argv, true);
$hasUnknownOption = count(array_filter(array_slice($argv, 1), static function (string $argument): bool {
    return !in_array($argument, ['--test', '--production'], true);
})) > 0;

// A bare reset is deliberately safe: it targets the isolated test database.
// Production always requires the explicit --production switch.
if (!$testMode && !$productionMode && !$hasUnknownOption) {
    $testMode = true;
}
if ($hasUnknownOption || ($testMode && $productionMode)) {
    fwrite(STDERR, "Usage: php tools/db_reset.php [--test]|--production\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'config.php';
$database = $testMode ? 'ie4727db_test' : DB_NAME;
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE ' . $quotedDatabase);

// The existing full dump is the repository's 001 schema plus relative seed.
$schema = $root . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . '001_schema.sql';
$source = is_file($schema) ? $schema : $root . DIRECTORY_SEPARATOR . 'ie4727db_fixed.sql';
$sql = file_get_contents($source);
if ($sql === false) {
    throw new RuntimeException('Schema source is unreadable: ' . $source);
}
$sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `[^`]+`[^;]*;\s*/i', '', $sql);
$sql = preg_replace('/USE `[^`]+`\s*;/i', '', $sql);
$sql = preg_replace('/^\s*(?:--|#).*$/m', '', (string) $sql);
foreach (preg_split('/;\s*(?:\r?\n|$)/', (string) $sql) as $statement) {
    $statement = trim($statement);
    if ($statement !== '' && !preg_match('/^(--|#)/', $statement)) {
        $pdo->exec($statement);
    }
}

$migration = $root . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . '002_migrate.sql';
$migrationSql = is_file($migration) ? file_get_contents($migration) : '';
$migrationSql = preg_replace('/^\s*(?:--|#).*$/m', '', (string) $migrationSql);
foreach (preg_split('/;\s*(?:\r?\n|$)/', (string) $migrationSql) as $statement) {
    $statement = trim($statement);
    if ($statement !== '' && !preg_match('/^(--|#)/', $statement)) {
        $pdo->exec($statement);
    }
}
echo 'OK: reset ' . ($testMode ? 'isolated test database ' : 'production database ') . $database . "\n";

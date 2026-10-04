<?php

declare(strict_types=1);

// Rebuild a database from the repository schema and seed (001 + 002 + 003).
//
//   php tools/db_reset.php               isolated test database (default)
//   php tools/db_reset.php --test        same, explicit
// Live resets are deliberately unsupported by this unattended entry point.
//
// Credentials come from clinic-base/config.php (and config.local.php).
$mode = $argv[1] ?? '--test';
if (!in_array($mode, ['--test'], true) || count($argv) > 2) {
    fwrite(STDERR, "Refusing reset: only ie4727db_test is supported. Usage: php tools/db_reset.php [--test]\n");
    exit(2);
}

if ($mode === '--test') {
    // The suite runner sets CLINIC_DB_NAME to the test database; this default
    // matches it so a bare reset never touches the application database.
    $name = getenv('CLINIC_DB_NAME') ?: 'ie4727db_test';
    if ($name !== 'ie4727db_test') {
        fwrite(STDERR, "Refusing reset: only ie4727db_test is supported.\n");
        exit(1);
    }
    putenv('CLINIC_DB_NAME=' . $name);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic-base' . DIRECTORY_SEPARATOR . 'config.php';

$name = DB_NAME;
if ($name !== 'ie4727db_test') {
    fwrite(STDERR, "Refusing reset: only ie4727db_test is supported.\n");
    exit(1);
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

$identifier = '`' . str_replace('`', '``', $name) . '`';
$pdo->exec('DROP DATABASE IF EXISTS ' . $identifier);
$pdo->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE ' . $identifier);

foreach ([__DIR__ . '/../schema/001_schema.sql', __DIR__ . '/../schema/002_migrate.sql', __DIR__ . '/../schema/003_seed.sql'] as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }
    // The repository SQL files are also directly importable and contain their
    // historical database-selection statements. Keep this reset on the
    // guarded database selected above when applying them here.
    $sql = preg_replace('/^\s*CREATE DATABASE IF NOT EXISTS `[^`]+`[^;]*;\s*$/mi', '', $sql);
    $sql = preg_replace('/^\s*USE `[^`]+`;\s*$/mi', '', $sql);
    if ($sql === null) {
        throw new RuntimeException('Unable to prepare ' . $file);
    }
    $pdo->exec($sql);
}

echo "Reset {$name} ({$mode})\n";
foreach (['doctor', 'patient', 'slots', 'appointment', 'notifications'] as $table) {
    $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    echo $table . ': ' . $count . " row(s)\n";
}

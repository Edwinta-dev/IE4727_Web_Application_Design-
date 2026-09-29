<?php

declare(strict_types=1);

// Rebuild the configured local database from the repository schema and seed.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'clinic_ie4727db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

if (stripos($name, 'clinic') === false) {
    fwrite(STDERR, "Refusing to reset database '{$name}': DB_NAME must contain 'clinic'.\n");
    exit(1);
}

$pdo = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $pass, [
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

foreach (['doctor', 'patient', 'slots', 'appointment', 'notifications'] as $table) {
    $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    echo $table . ': ' . $count . " row(s)\n";
}

<?php

declare(strict_types=1);

// Rebuild the configured local database from the two repository migrations.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'ie4727db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$pdo = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

$identifier = '`' . str_replace('`', '``', $name) . '`';
$pdo->exec('DROP DATABASE IF EXISTS ' . $identifier);
$pdo->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE ' . $identifier);

foreach ([__DIR__ . '/../schema/001_schema.sql', __DIR__ . '/../schema/002_migrate.sql'] as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }
    $pdo->exec($sql);
}

echo "Database reset complete\n";

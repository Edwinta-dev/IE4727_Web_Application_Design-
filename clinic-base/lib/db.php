<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

/**
 * Return the single lazily-created database connection for this request.
 */
function db(): PDO
{
    static $connection;

    if (!$connection instanceof PDO) {
        $dsn = 'mysql:host=' . DB_HOST
            . ';dbname=' . DB_NAME
            . ';charset=utf8mb4';

        $connection = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $connection;
}

/**
 * Prepare and execute a parameterised query.
 *
 * This is the only database entry point used by the application.
 */
function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();

    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = []): mixed
{
    $value = q($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}

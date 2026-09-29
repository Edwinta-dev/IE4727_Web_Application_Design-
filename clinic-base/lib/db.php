<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'errors.php';
install_error_handling();

function db(): PDO
{
    static $connection;
    if (!$connection instanceof PDO) {
        $connection = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $connection;
}

function q(string $sql, array $params = []): PDOStatement
{
    try {
        $statement = db()->prepare($sql);
        $statement->execute($params);
        return $statement;
    } catch (Throwable $exception) {
        app_log('database failure', ['exception' => $exception, 'sql' => $sql]);
        throw $exception;
    }
}

/** Run a write atomically; failed writes always roll back before the error propagates. */
function db_transaction(callable $operation): mixed
{
    $connection = db();
    $connection->beginTransaction();
    try {
        $result = $operation();
        $connection->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        app_log('transaction rolled back', ['exception' => $exception]);
        throw $exception;
    }
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

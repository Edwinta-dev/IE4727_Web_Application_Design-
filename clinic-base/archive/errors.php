<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';

function correlation_id(): string
{
    static $id;
    if (!isset($id)) {
        $id = bin2hex(random_bytes(8));
        if (!headers_sent()) {
            header('X-Correlation-ID: ' . $id);
        }
    }
    return $id;
}

function app_log(string $message, array $context = []): void
{
    $safe = [];
    foreach ($context as $key => $value) {
        if ($value instanceof Throwable) {
            $safe[$key] = ['class' => get_class($value), 'message' => $value->getMessage(), 'file' => $value->getFile(), 'line' => $value->getLine()];
        } elseif (is_scalar($value) || $value === null) {
            $safe[$key] = $value;
        } else {
            $safe[$key] = get_debug_type($value);
        }
    }
    $entry = ['timestamp' => gmdate('c'), 'correlation_id' => correlation_id(), 'message' => $message, 'context' => $safe];
    error_log(json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, APP_LOG_FILE);
}

function production_error_handler(int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }
    app_log('PHP runtime warning', ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line]);
    return true;
}

function install_error_handling(): void
{
    set_error_handler('production_error_handler');
    set_exception_handler(static function (Throwable $exception): void {
        app_log('uncaught exception', ['exception' => $exception]);
        http_response_code(500);
        $id = correlation_id();
        $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        echo APP_VERBOSE_ERRORS ? 'Unexpected error (' . $safeId . ').' : 'Something went wrong. Reference: ' . $safeId . '.';
    });
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            app_log('fatal PHP error', $error);
        }
    });
}

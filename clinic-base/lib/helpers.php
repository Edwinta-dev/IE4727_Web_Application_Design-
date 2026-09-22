<?php

declare(strict_types=1);

/**
 * Escape a value for use in HTML.
 */
function e(mixed $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Start the session once so library includes can safely be repeated.
 */
function start_session_once(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Redirect and stop processing the current request.
 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Return an escaped value saved for repopulating a form.
 */
function old(string $key, mixed $default = ''): mixed
{
    start_session_once();

    return $_SESSION['old'][$key] ?? $default;
}

/**
 * Save a one-time message for the next response.
 */
function flash(string $msg, string $type = 'info'): void
{
    start_session_once();
    $_SESSION['flash'] = [
        'message' => $msg,
        'type' => $type,
    ];
}

/**
 * Render and consume the saved one-time message.
 */
function flash_render(): void
{
    start_session_once();
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    if (!is_array($message) || !isset($message['message'])) {
        return;
    }

    $type = (string) ($message['type'] ?? 'info');
    echo '<div class="flash flash-' . e($type) . '" role="alert">'
        . e($message['message'])
        . '</div>';
}

/**
 * Return the validation error saved for one field.
 */
function errors_for(string $field): string
{
    start_session_once();
    $error = $_SESSION['errors'][$field] ?? '';

    return is_scalar($error) ? (string) $error : '';
}

/**
 * Format a date value for display.
 */
function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('d M Y', $timestamp);
}

/**
 * Format a time value for display.
 */
function fmt_time(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('g:i A', $timestamp);
}

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
 * Render a user-facing error page with the normal site chrome.
 */
function render_status_page(int $status, string $title, string $message): never
{
    http_response_code($status);
    $pageTitle = $title . ' - ' . (defined('APP_NAME') ? APP_NAME : 'Clinic Appointment Portal');
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'nav.php';
    echo '<main class="status-page">'
        . '<section class="page-intro">'
        . '<h1>' . e($title) . '</h1>'
        . '<p>' . e($message) . '</p>'
        . '<img src="/assets/img/clinic-logo.svg" alt="Clinic Appointment Portal" class="page-intro-image">'
        . '<p><a href="/index.php">Return to the home page</a></p>'
        . '</section></main>';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
    exit;
}

function render_not_found(string $message = 'The page or record you requested could not be found.'): never
{
    render_status_page(404, 'Page not found', $message);
}

/**
 * Return an escaped value saved for repopulating a form.
 */
function old(string $key, mixed $default = ''): mixed
{
    start_session_once();
    $value = $_SESSION['old'][$key] ?? $default;
    unset($_SESSION['old'][$key]);
    if (isset($_SESSION['old']) && $_SESSION['old'] === []) {
        unset($_SESSION['old']);
    }

    return $value;
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
    unset($_SESSION['errors'][$field]);
    if (isset($_SESSION['errors']) && $_SESSION['errors'] === []) {
        unset($_SESSION['errors']);
    }

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

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
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * URL prefix of the app folder: "/IE4727_Web_Application_Design-/clinic-base" when served from a
 * subfolder of XAMPP htdocs, "" when the app folder is the web root. Worked out per request from
 * the running script, so a fresh XAMPP install needs no configuration.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $base = '';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $file = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = realpath(dirname(__DIR__));
    if ($script === '' || $file === false || $root === false || !str_starts_with($file, $root)) {
        return $base;
    }

    // e.g. "/doctor/home.php": the script's path inside the app folder.
    $inApp = str_replace('\\', '/', substr($file, strlen($root)));
    if ($inApp !== '' && strcasecmp(substr($script, -strlen($inApp)), $inApp) === 0) {
        $base = rtrim(substr($script, 0, -strlen($inApp)), '/');
    }

    return $base;
}

/**
 * Turn an app path such as "/book.php?doctor=1" into a URL that works under any base folder.
 */
function url(string $path): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return $path;
    }

    return base_path() . $path;
}

/**
 * The current request URI as an app path, with the base folder removed.
 */
function app_request_uri(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $base = base_path();
    if ($base !== '' && strncasecmp($uri, $base, strlen($base)) === 0) {
        $uri = substr($uri, strlen($base));
    }

    return $uri === '' ? '/' : $uri;
}

/**
 * Practice photo for a doctor's specialty, shown on their profile page.
 */
function specialty_image(string $specialty): string
{
    $images = [
        'General Practice' => 'General_Practice_in_Action.jpg',
        'Dental' => 'Dentist_in_action.jpg',
        'Paediatrics' => 'Paediatrics_in_Action.jpg',
        'Dermatology' => 'Dermatologist_in_Action.jpg',
        'Physiotherapy' => 'Physiotherapist_in_Action.jpg',
    ];

    return '/assets/img/' . ($images[$specialty] ?? 'Clinic_Assisting_Elderly_woman.jpg');
}

/**
 * Redirect to an app path and stop processing the current request.
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
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
        . '<img src="' . e(url('/assets/img/clinic-logo.svg')) . '" width="96" height="96" loading="eager" decoding="async" alt="Clinic Appointment Portal" class="page-intro-image">'
        . '<p><a href="' . e(url('/index.php')) . '">Return to the home page</a></p>'
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
